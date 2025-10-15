# Galaxy Card Game Web Viewer

> 🌟 一个优雅、安全的卡牌游戏数据库浏览系统

Galaxy Card Game Web Viewer 是一个基于 PHP 和 SQLite 的轻量级卡牌数据库浏览工具，支持自动更新、智能搜索和完善的安全机制。

## ✨ 功能特性

### 核心功能
- 🎴 **卡片浏览**: 分页展示所有卡片，支持查看详细信息
- 🔍 **智能搜索**: 按卡片 ID 或名称快速搜索
- 📱 **响应式设计**: 完美适配桌面和移动设备
- 🚀 **性能优化**: SQLite 数据库，查询速度快

### 自动更新系统
- ⚡ **自动检查更新**: 每小时自动检查 GitHub 仓库
- 📥 **自动下载**: 发现新版本立即下载更新
- 🔒 **并发保护**: 文件锁机制防止并发下载
- 💾 **安全回滚**: 下载失败时保留旧版本
- 🔄 **首次自动下载**: 数据库不存在时自动下载

### 安全特性
- 🔐 **SSL 证书验证**: 防止中间人攻击
- 🛡️ **SQL 注入防护**: 全部使用参数化查询
- 🚫 **XSS 防护**: 所有输出都经过 htmlspecialchars
- 🔑 **密钥认证**: 更新接口需要密钥验证
- 🚨 **防暴力破解**: IP 封禁机制（5分钟5次，封禁15分钟）
- 📝 **日志轮转**: 自动清理日志，保留最近5个文件
- 🗂️ **目录保护**: .htaccess 禁止直接访问敏感文件
- 🔍 **文件完整性验证**: 验证下载文件的格式和结构

## 🚀 快速开始

### 环境要求

- **PHP**: 7.0 或更高版本
- **扩展**:
  - SQLite3 (必需)
  - cURL (推荐)
  - OpenSSL (推荐)
- **Web 服务器**: Apache/Nginx
- **其他**: 支持 .htaccess (Apache) 或等效配置 (Nginx)

### 安装步骤

#### 1. 克隆项目

```bash
git clone https://github.com/FogMoe/GCGWebViewer.git
cd GCGWebViewer
```

#### 2. 配置环境变量

```bash
# 复制示例配置文件
cp .env.example .env

# 编辑 .env 文件
nano .env
```

**重要**：修改以下配置
```env
# 设置一个强随机密钥（至少32位）
UPDATE_SECRET_KEY=your_random_secret_key_here_change_me

# 如果使用反向代理（可选）
# TRUSTED_PROXIES=127.0.0.1,10.0.0.1
```

#### 3. 设置文件权限

```bash
# protectedFolder 需要写权限
chmod 755 protectedFolder

# .env 文件只有所有者可读
chmod 600 .env

# 确保 .htaccess 存在
ls -la protectedFolder/.htaccess
```

#### 4. 配置 Web 服务器

**Apache** (推荐)
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/GCGWebViewer

    <Directory /path/to/GCGWebViewer>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/GCGWebViewer;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /protectedFolder {
        deny all;
        return 403;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

#### 5. 访问网站

打开浏览器访问: `http://your-domain.com`

首次访问时，系统会自动下载卡片数据库。

## 📖 使用指南

### 浏览卡片

直接访问首页即可浏览所有卡片，支持分页浏览。

### 搜索卡片

在搜索框中输入卡片 ID 或名称，点击"查询"按钮。

**示例**：
- 搜索 ID: `32864`
- 搜索名称: `龙`

### 手动更新数据库

虽然系统会自动更新，但您也可以手动触发更新：

#### 强制更新
```
https://your-domain.com/update.php?action=update&key=YOUR_SECRET_KEY
```

#### 查看更新状态
```
https://your-domain.com/update.php?action=status&key=YOUR_SECRET_KEY
```

#### 检查并更新（如需要）
```
https://your-domain.com/update.php?action=check&key=YOUR_SECRET_KEY
```

**响应示例**：

成功更新：
```json
{
    "success": true,
    "message": "更新成功！新版本: abc1234"
}
```

查看状态：
```json
{
    "success": true,
    "db_exists": true,
    "db_size_formatted": "512.00 KB",
    "last_check_formatted": "2024-10-15 12:00:00",
    "current_sha_short": "abc1234",
    "next_check_formatted": "2024-10-15 13:00:00",
    "is_updating": false
}
```

## 🔧 配置说明

### 环境变量 (.env)

| 变量 | 必需 | 默认值 | 说明 |
|------|------|--------|------|
| `UPDATE_SECRET_KEY` | ✅ | - | 更新接口的密钥，必须修改 |
| `TRUSTED_PROXIES` | ❌ | - | 信任的代理 IP 列表（逗号分隔） |
| `GITHUB_REPO` | ❌ | FogMoe/galaxycardgame | GitHub 仓库 |
| `GITHUB_BRANCH` | ❌ | master | GitHub 分支 |
| `CHECK_INTERVAL` | ❌ | 3600 | 检查间隔（秒） |

### 更新管理器配置

在 `protectedFolder/UpdateManager.php` 中可以调整：

```php
private $checkInterval = 3600; // 检查间隔：1小时
private $downloadUrls = [
    'https://raw.githubusercontent.com/...',  // 主下载源
    'https://github.com/.../raw/...'          // 备用下载源
];
```

### 速率限制配置

在 `update.php` 中调整防暴力破解参数：

```php
define('MAX_ATTEMPTS', 5);      // 最大尝试次数
define('BLOCK_DURATION', 900);  // 封禁时长（秒）
define('ATTEMPT_WINDOW', 300);  // 尝试窗口（秒）
```

## 🔄 自动更新机制

### 工作原理

```
┌─────────────┐
│  用户访问   │
└──────┬──────┘
       │
       ▼
┌──────────────────────────┐
│ Controller 初始化        │
│ ├─ 检查是否需要更新      │
│ │  └─ 距上次检查>1小时？ │
│ └─ 是：检查GitHub更新    │
│    └─ 有新版：自动下载   │
└──────┬───────────────────┘
       │
       ▼
┌──────────────┐
│  加载数据库  │
└──────────────┘
```

### 时间线示例

```
09:00:00 - 用户A访问 → 检查更新（无更新）
09:00:01 - 用户B访问 → 跳过检查（<1小时）
09:30:00 - 用户C访问 → 跳过检查（<1小时）
10:00:01 - 用户D访问 → 检查更新（>1小时）
10:00:02 - 用户E访问 → 跳过检查（<1小时）
```

### 并发保护

- 使用文件锁 (`flock`) 防止并发下载
- 锁文件 5 分钟后自动过期
- 正在更新时，其他请求跳过检查

### 文件验证

下载的文件会经过多重验证：

1. **大小检查**: 至少 1KB
2. **文件头验证**: 检查 SQLite 文件头
3. **结构验证**: 验证必需的表和字段
4. **原子替换**: 使用 `rename()` 原子性替换

## 🛡️ 安全特性详解

### 1. 防止中间人攻击

```php
// SSL 证书验证已启用
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
```

### 2. 防止 IP 伪造

```php
// 只信任配置的代理 IP
// 默认使用 REMOTE_ADDR（不可伪造）
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    // 只有来自信任代理时才使用 X-Forwarded-For
}
```

### 3. SQL 注入防护

```php
// 全部使用参数化查询
$stmt = $db->prepare('SELECT * FROM cards WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
```

### 4. XSS 防护

```php
// 所有输出都经过转义
<?= htmlspecialchars($card['name']) ?>
```

### 5. 目录保护

`.htaccess` 禁止直接访问敏感文件：
- ✅ 数据库文件 (.cdb)
- ✅ 配置文件 (.json)
- ✅ 日志文件 (.log)
- ✅ 锁文件 (.lock)

## 📁 目录结构

```
GCGWebViewer/
├── index.php                      # 主入口文件
├── update.php                     # 手动更新接口
├── styles.css                     # 样式文件
├── .env                          # 环境配置（不提交到 Git）
├── .env.example                  # 环境配置示例
├── .gitignore                    # Git 忽略文件
├── LICENSE                       # 许可证 (GPL-3.0)
├── README.md                     # 项目文档（本文件）
│
└── protectedFolder/              # 受保护的目录
    ├── .htaccess                 # Apache 目录保护配置
    ├── Card.php                  # 卡片模型类
    ├── Controller.php            # 控制器类
    ├── View.php                  # 视图类
    ├── UpdateManager.php         # 更新管理器
    ├── Test.php                  # 测试文件（不应部署）
    │
    ├── cards.cdb                 # 卡片数据库（自动下载）
    ├── update.json               # 更新状态记录
    ├── update.log                # 更新日志
    ├── update.lock               # 更新锁文件
    └── rate_limit.json           # 速率限制记录
```

## 🔍 故障排查

### 问题 1: 数据库未自动下载

**症状**: 首次访问显示"数据库连接失败"

**解决方案**:
1. 检查 `protectedFolder/` 目录权限
   ```bash
   chmod 755 protectedFolder
   ```
2. 检查服务器是否能访问 GitHub
   ```bash
   curl -I https://raw.githubusercontent.com/FogMoe/galaxycardgame/master/cards.cdb
   ```
3. 查看日志
   ```bash
   cat protectedFolder/update.log
   ```

### 问题 2: 更新接口密钥验证失败

**症状**: 访问 update.php 返回"密钥验证失败"

**解决方案**:
1. 确认 `.env` 文件存在
   ```bash
   ls -la .env
   ```
2. 确认已修改默认密钥
   ```bash
   grep UPDATE_SECRET_KEY .env
   ```
3. 确认 URL 中的密钥正确

### 问题 3: 自动更新不工作

**症状**: 数据库长时间未更新

**解决方案**:
1. 检查更新状态
   ```
   update.php?action=status&key=YOUR_KEY
   ```
2. 查看 `next_check` 时间
3. 检查日志是否有错误
   ```bash
   tail -n 50 protectedFolder/update.log
   ```

### 问题 4: 被封禁无法访问更新接口

**症状**: 返回"访问过于频繁，已被临时封禁"

**解决方案**:
1. 等待 15 分钟后重试
2. 或者手动删除封禁记录
   ```bash
   rm protectedFolder/rate_limit.json
   ```

### 问题 5: 图片无法显示

**症状**: 卡片图片显示为叉号

**原因**: 图片托管在 GitHub，可能被墙或网络慢

**解决方案**:
- 使用 CDN 加速（修改 index.php 中的图片 URL）
- 或者下载图片到本地

## 🛠️ 开发指南

### 本地开发

```bash
# 克隆项目
git clone https://github.com/FogMoe/GCGWebViewer.git
cd GCGWebViewer

# 配置环境
cp .env.example .env
nano .env

# 使用 PHP 内置服务器
php -S localhost:8000
```

### 修改卡片显示

编辑 `protectedFolder/View.php` 中的 `toView()` 方法，可以自定义：
- 卡片类型映射
- 种族映射
- 属性映射
- 等级计算

### 修改样式

编辑 `styles.css` 或 `index.php` 中的 `<style>` 标签。

### 测试

```bash
# 测试数据库连接
php protectedFolder/Test.php

# 测试更新功能
curl "http://localhost:8000/update.php?action=status&key=YOUR_KEY"
```

## 🤝 贡献指南

欢迎贡献代码！请遵循以下步骤：

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

### 代码规范

- PHP 代码遵循 PSR-12 标准
- 所有用户输入必须验证和转义
- 使用参数化查询防止 SQL 注入
- 添加必要的注释

## 📝 变更日志

### v2.0.0 (2025-10-15)

**新增**:
- 🔐 完整的安全加固（SSL 验证、IP 防伪、文件验证）
- 🔄 智能自动更新系统
- 🛡️ 防暴力破解机制
- 📝 日志轮转功能
- 🗂️ .htaccess 目录保护

**改进**:
- ⚡ 性能优化（1小时更新间隔）
- 🎨 更好的错误处理
- 📱 响应式设计改进

**修复**:
- 🐛 修复等级计算逻辑
- 🐛 修复空搜索问题
- 🐛 修复并发下载问题

## 📄 许可证

本项目采用 [GPL-3.0 License](LICENSE) 开源。

## 🙏 致谢

- [FogMoe/galaxycardgame](https://github.com/FogMoe/galaxycardgame) - 卡片数据源
- [SQLite](https://www.sqlite.org/) - 轻量级数据库
- 所有贡献者

## 📞 联系方式

- **作者**: FOGMOE
- **网站**: [https://fog.moe/](https://fog.moe/)
- **GitHub**: [https://github.com/FogMoe/GCGWebViewer](https://github.com/FogMoe/GCGWebViewer)

## ⭐ Star History

如果这个项目对您有帮助，请给个 Star ⭐️

---

**Made with ❤️ by FOGMOE**
