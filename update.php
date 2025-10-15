<?php
/**
 * 手动更新接口
 * 需要提供正确的密钥才能触发更新
 * 包含防暴力破解机制
 * 用法：update.php?action=update&key=your_secret_key
 *      update.php?action=status&key=your_secret_key
 */

require_once 'protectedFolder/UpdateManager.php';

// 防暴力破解配置
define('MAX_ATTEMPTS', 5); // 最大尝试次数
define('BLOCK_DURATION', 900); // 封禁时长（秒），15分钟
define('ATTEMPT_WINDOW', 300); // 尝试窗口（秒），5分钟内
define('RATE_LIMIT_FILE', 'protectedFolder/rate_limit.json');

// 加载 .env 配置
function loadEnv($path) {
    if (!file_exists($path)) {
        return [];
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        // 跳过注释
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // 解析键值对
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    
    return $env;
}

/**
 * 获取客户端IP（安全版本，防止伪造）
 *
 * 注意：如果您的服务器在反向代理（如 Nginx、Cloudflare）后面，
 * 需要在 .env 文件中配置 TRUSTED_PROXIES，例如：
 * TRUSTED_PROXIES=127.0.0.1,10.0.0.1
 */
function getClientIP() {
    // 默认使用直连 IP，这个无法被伪造
    $ip = $_SERVER['REMOTE_ADDR'];

    // 加载信任的代理列表
    $env = loadEnv('.env');
    $trustedProxies = [];
    if (isset($env['TRUSTED_PROXIES'])) {
        $trustedProxies = array_map('trim', explode(',', $env['TRUSTED_PROXIES']));
    }

    // 只有当请求来自信任的代理时，才使用 X-Forwarded-For
    if (!empty($trustedProxies) && in_array($ip, $trustedProxies)) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            // 取最左边的 IP（真实客户端 IP）
            $ip = trim($forwardedIps[0]);
        }
    }

    return $ip;
}

/**
 * 检查IP是否被封禁（并清理过期记录）
 */
function isBlocked($ip) {
    if (!file_exists(RATE_LIMIT_FILE)) {
        return false;
    }

    $data = json_decode(file_get_contents(RATE_LIMIT_FILE), true);
    if (!isset($data[$ip])) {
        return false;
    }

    // 定期清理过期的封禁记录（10% 概率）
    if (rand(1, 10) === 1) {
        cleanExpiredBlocks($data);
    }

    $record = $data[$ip];

    // 检查是否在封禁期内
    if (isset($record['blocked_until']) && time() < $record['blocked_until']) {
        return true;
    }

    return false;
}

/**
 * 清理过期的封禁记录
 */
function cleanExpiredBlocks(&$data) {
    $now = time();
    $cleaned = false;

    foreach ($data as $ip => $record) {
        // 如果封禁已过期且没有最近的失败尝试，删除此记录
        if (isset($record['blocked_until']) && $record['blocked_until'] < $now) {
            if (empty($record['attempts']) || (count($record['attempts']) === 0)) {
                unset($data[$ip]);
                $cleaned = true;
            }
        }
    }

    if ($cleaned) {
        file_put_contents(RATE_LIMIT_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
}

/**
 * 记录失败尝试
 */
function recordFailedAttempt($ip) {
    $data = [];
    if (file_exists(RATE_LIMIT_FILE)) {
        $data = json_decode(file_get_contents(RATE_LIMIT_FILE), true);
    }
    
    if (!isset($data[$ip])) {
        $data[$ip] = [
            'attempts' => [],
            'blocked_until' => 0
        ];
    }
    
    $now = time();
    
    // 添加本次失败尝试
    $data[$ip]['attempts'][] = $now;
    
    // 清理超出窗口期的尝试记录
    $data[$ip]['attempts'] = array_filter($data[$ip]['attempts'], function($timestamp) use ($now) {
        return ($now - $timestamp) < ATTEMPT_WINDOW;
    });
    
    // 重新索引数组
    $data[$ip]['attempts'] = array_values($data[$ip]['attempts']);
    
    // 检查是否超过最大尝试次数
    if (count($data[$ip]['attempts']) >= MAX_ATTEMPTS) {
        $data[$ip]['blocked_until'] = $now + BLOCK_DURATION;
        $data[$ip]['attempts'] = []; // 清空尝试记录
    }
    
    file_put_contents(RATE_LIMIT_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * 清理成功的记录
 */
function clearAttempts($ip) {
    if (!file_exists(RATE_LIMIT_FILE)) {
        return;
    }
    
    $data = json_decode(file_get_contents(RATE_LIMIT_FILE), true);
    if (isset($data[$ip])) {
        unset($data[$ip]);
        file_put_contents(RATE_LIMIT_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// 验证密钥
function verifyKey($providedKey) {
    $env = loadEnv('.env');
    
    if (!isset($env['UPDATE_SECRET_KEY'])) {
        return false;
    }
    
    $secretKey = $env['UPDATE_SECRET_KEY'];
    
    // 检查是否是默认密钥（未修改）
    if ($secretKey === 'your_secret_key_here_change_me') {
        die(json_encode([
            'success' => false,
            'message' => '错误：请先在 .env 文件中设置您的密钥！'
        ]));
    }
    
    return $providedKey === $secretKey;
}

// 设置 JSON 响应头
header('Content-Type: application/json; charset=utf-8');

// 获取客户端IP
$clientIP = getClientIP();

// 检查IP是否被封禁
if (isBlocked($clientIP)) {
    http_response_code(429); // Too Many Requests
    $rateLimitData = json_decode(file_get_contents(RATE_LIMIT_FILE), true);
    $blockedUntil = isset($rateLimitData[$clientIP]['blocked_until'])
        ? date('Y-m-d H:i:s', $rateLimitData[$clientIP]['blocked_until'])
        : '未知';

    echo json_encode([
        'success' => false,
        'message' => '访问过于频繁，已被临时封禁。请稍后再试。',
        'blocked_until' => $blockedUntil
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取参数
$action = isset($_GET['action']) ? $_GET['action'] : '';
$key = isset($_GET['key']) ? $_GET['key'] : '';

// 验证密钥
if (!verifyKey($key)) {
    // 记录失败尝试
    recordFailedAttempt($clientIP);
    
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => '密钥验证失败！'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证成功，清除失败记录
clearAttempts($clientIP);

// 创建更新管理器实例
$updateManager = new UpdateManager();

// 处理不同的操作
switch ($action) {
    case 'update':
        // 强制更新
        $result = $updateManager->forceUpdate();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    case 'status':
        // 获取状态
        $status = $updateManager->getStatus();
        $status['success'] = true;
        
        // 格式化时间
        if ($status['last_check'] > 0) {
            $status['last_check_formatted'] = date('Y-m-d H:i:s', $status['last_check']);
        }
        if ($status['last_update'] > 0) {
            $status['last_update_formatted'] = date('Y-m-d H:i:s', $status['last_update']);
        }
        if ($status['next_check'] > 0) {
            $status['next_check_formatted'] = date('Y-m-d H:i:s', $status['next_check']);
        }
        
        // 格式化文件大小
        if ($status['db_size'] > 0) {
            $status['db_size_formatted'] = round($status['db_size'] / 1024, 2) . ' KB';
        }
        
        // 短 SHA
        if ($status['current_sha']) {
            $status['current_sha_short'] = substr($status['current_sha'], 0, 7);
        }
        
        echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    case 'check':
        // 检查更新（不强制）
        $updated = $updateManager->checkForUpdates();
        echo json_encode([
            'success' => true,
            'message' => $updated ? '已检查并更新' : '已检查，无需更新'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => '无效的操作。支持的操作: update, status, check',
            'usage' => [
                'update' => 'update.php?action=update&key=YOUR_KEY - 强制更新数据库',
                'status' => 'update.php?action=status&key=YOUR_KEY - 查看当前状态',
                'check' => 'update.php?action=check&key=YOUR_KEY - 检查并更新（如果需要）'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
}
?>
