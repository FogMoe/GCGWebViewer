<?php
/**
 * 卡片数据库更新管理器
 * 功能：
 * 1. 定期检查 GitHub 仓库是否有新的提交
 * 2. 自动下载最新的 cards.cdb 文件
 * 3. 如果本地文件不存在，自动下载
 * 4. 使用文件锁防止并发下载
 */
class UpdateManager {

    private $dbPath = 'protectedFolder/cards.cdb';
    private $tempPath = 'protectedFolder/cards.cdb.tmp';
    private $lockPath = 'protectedFolder/update.lock';
    private $configPath = 'protectedFolder/update.json';

    // GitHub 相关配置
    private $githubRepo = 'FogMoe/galaxycardgame';
    private $githubBranch = 'master';
    private $githubFilePath = 'cards.cdb';

    // 多个下载源（按优先级尝试）
    private $downloadUrls = [
        'https://raw.githubusercontent.com/FogMoe/galaxycardgame/master/cards.cdb',  // GitHub Raw CDN (最快: 278ms)
        'https://github.com/FogMoe/galaxycardgame/raw/refs/heads/master/cards.cdb'  // GitHub 直接链接 (备用: 1077ms)
    ];

    // 检查间隔（秒）
    private $checkInterval = 3600; // 1小时

    /**
     * 构造函数 - 检查目录权限
     */
    public function __construct() {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            throw new Exception("目录不存在: {$dir}");
        }
        if (!is_writable($dir)) {
            throw new Exception("目录不可写: {$dir}，请检查权限");
        }
    }
    
    /**
     * 检查是否需要更新
     * 自动下载模式 - 数据库很小，下载很快
     * @return bool 是否触发了更新检查
     */
    public function checkForUpdates() {
        // 如果本地文件不存在，强制下载
        if (!file_exists($this->dbPath)) {
            $this->log("本地 cards.cdb 不存在，开始下载...");
            return $this->downloadUpdate();
        }
        
        // 如果正在更新中，跳过（避免并发）
        if (file_exists($this->lockPath)) {
            // 检查锁文件是否过期（超过5分钟）
            if (time() - filemtime($this->lockPath) > 300) {
                $this->log("检测到过期的锁文件，删除");
                @unlink($this->lockPath);
            } else {
                // 正在更新中，跳过
                return false;
            }
        }
        
        // 读取配置
        $config = $this->loadConfig();
        
        // 检查是否需要检查更新
        $now = time();
        if ($now - $config['last_check'] < $this->checkInterval) {
            // 还未到检查时间
            return false;
        }
        
        // 更新最后检查时间
        $config['last_check'] = $now;
        $this->saveConfig($config);
        
        // 检查 GitHub 是否有新提交
        $latestCommit = $this->getLatestCommitSHA();
        if ($latestCommit === false) {
            $this->log("无法获取 GitHub 最新提交信息");
            return false;
        }
        
        // 对比 SHA，如果不同则立即下载
        if ($latestCommit !== $config['current_sha']) {
            $this->log("发现新版本: {$latestCommit}，开始自动下载");
            if ($this->downloadUpdate()) {
                $config['current_sha'] = $latestCommit;
                $config['last_update'] = $now;
                $this->saveConfig($config);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 强制更新（用于手动触发）
     * @return array 包含状态和消息的数组
     */
    public function forceUpdate() {
        $this->log("手动触发更新");
        
        // 检查是否正在更新
        if (file_exists($this->lockPath)) {
            if (time() - filemtime($this->lockPath) < 300) {
                return ['success' => false, 'message' => '正在更新中，请稍后再试'];
            }
            // 锁文件过期，删除
            @unlink($this->lockPath);
        }
        
        $latestCommit = $this->getLatestCommitSHA();
        if ($latestCommit === false) {
            return ['success' => false, 'message' => '无法获取 GitHub 最新提交信息'];
        }
        
        // 检查是否已是最新版本
        $config = $this->loadConfig();
        if ($latestCommit === $config['current_sha']) {
            return ['success' => true, 'message' => '已是最新版本: ' . substr($latestCommit, 0, 7)];
        }
        
        if ($this->downloadUpdate()) {
            $config['current_sha'] = $latestCommit;
            $config['last_update'] = time();
            $config['last_check'] = time();
            $this->saveConfig($config);
            
            return ['success' => true, 'message' => '更新成功！新版本: ' . substr($latestCommit, 0, 7)];
        }
        
        return ['success' => false, 'message' => '下载失败，请查看日志'];
    }
    
    /**
     * 获取 GitHub 最新提交的 SHA
     * @return string|false 返回 SHA 或 false
     */
    private function getLatestCommitSHA() {
        $apiUrl = "https://api.github.com/repos/{$this->githubRepo}/commits?path={$this->githubFilePath}&page=1&per_page=1";
        
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: GCGWebViewer-UpdateManager'
                ],
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($opts);
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        $data = json_decode($response, true);
        if (isset($data[0]['sha'])) {
            return $data[0]['sha'];
        }
        
        return false;
    }
    
    /**
     * 下载更新文件
     * @return bool 是否成功
     */
    private function downloadUpdate() {
        // 获取文件锁，防止并发下载
        $lockFile = fopen($this->lockPath, 'w');
        if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
            $this->log("另一个更新进程正在运行");
            fclose($lockFile);
            return false;
        }
        
        try {
            // 尝试多个下载源
            $content = false;
            $successUrl = null;
            
            foreach ($this->downloadUrls as $url) {
                $this->log("尝试下载: {$url}");
                
                // 优先使用 curl（更可靠）
                if (function_exists('curl_init')) {
                    $content = $this->downloadWithCurl($url);
                } else {
                    // 备用方案：file_get_contents
                    $content = $this->downloadWithFileGetContents($url);
                }
                
                // 下载成功，跳出循环
                if ($content !== false && !empty($content)) {
                    $successUrl = $url;
                    $this->log("下载成功，大小: " . strlen($content) . " bytes");
                    break;
                }
                
                $this->log("此源下载失败，尝试下一个源...");
            }
            
            if ($content === false || empty($content)) {
                $this->log("所有下载源均失败");
                return false;
            }
            
            // 保存到临时文件
            if (file_put_contents($this->tempPath, $content) === false) {
                $this->log("保存临时文件失败");
                return false;
            }

            // 验证文件完整性
            if (!$this->validateDatabaseFile($this->tempPath)) {
                $this->log("下载的文件验证失败");
                @unlink($this->tempPath);
                return false;
            }
            
            // 原子性替换文件
            if (file_exists($this->dbPath)) {
                // 备份旧文件
                $backupPath = $this->dbPath . '.backup';
                copy($this->dbPath, $backupPath);
            }
            
            if (!rename($this->tempPath, $this->dbPath)) {
                $this->log("替换文件失败");
                return false;
            }
            
            $this->log("更新成功！文件大小: " . filesize($this->dbPath) . " bytes");
            
            // 删除备份文件
            if (isset($backupPath) && file_exists($backupPath)) {
                unlink($backupPath);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->log("更新异常: " . $e->getMessage());
            return false;
        } finally {
            // 释放锁并删除锁文件
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
            @unlink($this->lockPath);
        }
    }
    
    /**
     * 加载配置
     * @return array 配置数组
     */
    private function loadConfig() {
        if (!file_exists($this->configPath)) {
            return [
                'last_check' => 0,
                'last_update' => 0,
                'current_sha' => '',
                'check_interval' => $this->checkInterval
            ];
        }
        
        $content = file_get_contents($this->configPath);
        $config = json_decode($content, true);
        
        if ($config === null) {
            return [
                'last_check' => 0,
                'last_update' => 0,
                'current_sha' => '',
                'check_interval' => $this->checkInterval
            ];
        }
        
        return $config;
    }
    
    /**
     * 保存配置
     * @param array $config 配置数组
     */
    private function saveConfig($config) {
        $content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->configPath, $content);
    }
    
    /**
     * 获取更新状态信息
     * @return array 状态信息
     */
    public function getStatus() {
        $config = $this->loadConfig();
        
        return [
            'db_exists' => file_exists($this->dbPath),
            'db_size' => file_exists($this->dbPath) ? filesize($this->dbPath) : 0,
            'last_check' => $config['last_check'],
            'last_update' => $config['last_update'],
            'current_sha' => $config['current_sha'],
            'next_check' => $config['last_check'] + $this->checkInterval,
            'is_updating' => file_exists($this->lockPath)
        ];
    }
    
    /**
     * 获取系统代理设置
     * @return string|null 代理地址或 null
     */
    private function getSystemProxy() {
        // 检查环境变量中的代理设置
        $proxyVars = ['HTTP_PROXY', 'http_proxy', 'HTTPS_PROXY', 'https_proxy'];
        
        foreach ($proxyVars as $var) {
            $proxy = getenv($var);
            if ($proxy) {
                return $proxy;
            }
        }
        
        // Windows 系统：尝试从注册表读取（需要 COM 支持）
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // 可以通过执行 netsh 命令获取代理
            // 这里简化处理，只检查环境变量
        }
        
        return null;
    }
    
    /**
     * 使用 cURL 下载文件
     * @param string $url 下载地址
     * @return string|false 文件内容或 false
     */
    private function downloadWithCurl($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        // 启用 SSL 证书验证以防止中间人攻击
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // 自动检测并使用系统代理
        $proxy = $this->getSystemProxy();
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            $this->log("使用代理: {$proxy}");
        }
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content === false || ($httpCode !== 200 && $httpCode !== 0)) {
            $this->log("cURL 失败: HTTP {$httpCode}" . ($error ? ", {$error}" : ""));
            return false;
        }
        
        return $content;
    }
    
    /**
     * 使用 file_get_contents 下载文件
     * @param string $url 下载地址
     * @return string|false 文件内容或 false
     */
    private function downloadWithFileGetContents($url) {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: GCGWebViewer-UpdateManager',
                'timeout' => 60,
                'follow_location' => 1,
                'max_redirects' => 5
            ],
            'ssl' => [
                // 启用 SSL 证书验证以防止中间人攻击
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];
        $context = stream_context_create($opts);
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            $error = error_get_last();
            $this->log("file_get_contents 下载失败: " . ($error ? $error['message'] : '未知错误'));
            return false;
        }
        
        return $content;
    }
    
    /**
     * 验证数据库文件完整性
     * @param string $filePath 文件路径
     * @return bool 是否有效
     */
    private function validateDatabaseFile($filePath) {
        // 1. 检查文件大小（至少 1KB）
        $fileSize = filesize($filePath);
        if ($fileSize < 1024) {
            $this->log("文件太小（{$fileSize} bytes），可能不是有效的数据库");
            return false;
        }

        // 2. 检查 SQLite 文件头（前16字节应该是 "SQLite format 3\0"）
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            $this->log("无法打开文件进行验证");
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        if ($header !== "SQLite format 3\0") {
            $this->log("文件头不匹配，不是有效的 SQLite 数据库");
            return false;
        }

        // 3. 尝试打开数据库并验证基本结构
        try {
            $testDb = new SQLite3($filePath, SQLITE3_OPEN_READONLY);

            // 检查必需的表是否存在
            $requiredTables = ['datas', 'texts'];
            foreach ($requiredTables as $table) {
                $result = $testDb->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
                if ($result !== $table) {
                    $this->log("缺少必需的表: {$table}");
                    $testDb->close();
                    return false;
                }
            }

            // 验证数据表结构（检查关键字段）
            $columns = [];
            $result = $testDb->query("PRAGMA table_info(datas)");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row['name'];
            }

            $requiredColumns = ['id', 'type', 'atk', 'def', 'level'];
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $columns)) {
                    $this->log("datas 表缺少必需的字段: {$column}");
                    $testDb->close();
                    return false;
                }
            }

            $testDb->close();
            $this->log("文件验证通过");
            return true;

        } catch (Exception $e) {
            $this->log("数据库验证异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录日志（带自动轮转）
     * @param string $message 日志消息
     */
    private function log($message) {
        $logFile = 'protectedFolder/update.log';
        $maxLogSize = 1048576; // 1MB

        // 如果日志文件超过 1MB，进行轮转
        if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
            $archiveName = $logFile . '.' . date('Y-m-d-His');
            @rename($logFile, $archiveName);

            // 只保留最近 5 个日志文件
            $logFiles = glob('protectedFolder/update.log.*');
            if (count($logFiles) > 5) {
                // 按修改时间排序
                usort($logFiles, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                // 删除最旧的文件
                for ($i = 0; $i < count($logFiles) - 5; $i++) {
                    @unlink($logFiles[$i]);
                }
            }
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
?>
