<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
ob_start();

function api_error_exit($code, $message) {
    ob_end_clean(); 
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    exit; 
}

if (!file_exists(__DIR__ . '/../../config.php')) {
    api_error_exit(500, '内部服务器错误: 配置文件丢失');
}
require_once __DIR__ . '/../../config.php';

$endpoint = rawurlencode(basename($_SERVER['SCRIPT_FILENAME'], '.php'));
$auth_user_id = null;
$is_success = false;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_api = $pdo->prepare("SELECT * FROM sl_apis WHERE endpoint = ? LIMIT 1");
    $stmt_api->execute([$endpoint]);
    $api = $stmt_api->fetch(PDO::FETCH_ASSOC);

    if (!$api || $api['status'] !== 'normal') {
        api_error_exit(404, '接口不存在或已暂停服务');
    }

    $log_api_id = $api['id'];
    $log_user_id = null;
    $log_ip_address = $_SERVER['REMOTE_ADDR'];

    if ($api['visibility'] === 'public') {
        $is_success = true; 
    } else {
        $api_key = $_REQUEST['apikey'] ?? null;
        if (!$api_key) {
            api_error_exit(403, '访问被拒绝: 缺少必要的apikey参数');
        }

        $stmt_user = $pdo->prepare("SELECT id, status, balance FROM sl_users WHERE api_key = ? LIMIT 1");
        $stmt_user->execute([$api_key]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            api_error_exit(403, '访问被拒绝: 无效的apikey');
        }
        if ($user['status'] !== 'active') {
            api_error_exit(403, '访问被拒绝: 您的账户已被禁用');
        }

        $log_user_id = $user['id'];
        $auth_user_id = $user['id']; 

        if ($api['is_billable'] == 1) {
            if ($user['balance'] < $api['price_per_call']) {
                api_error_exit(402, '余额不足，请充值后重试');
            }
        }
        $is_success = true;
    }

    $pdo->beginTransaction();
    try {
        $stmt_update_api_calls = $pdo->prepare("UPDATE sl_apis SET total_calls = total_calls + 1 WHERE id = ?");
        $stmt_update_api_calls->execute([$log_api_id]);

        if ($log_user_id) {
            $stmt_update_user_calls = $pdo->prepare("UPDATE sl_users SET call_count = call_count + 1 WHERE id = ?");
            $stmt_update_user_calls->execute([$log_user_id]);

            if ($is_success && $api['is_billable'] == 1) {
                $stmt_deduct_balance = $pdo->prepare("UPDATE sl_users SET balance = balance - ? WHERE id = ?");
                $stmt_deduct_balance->execute([$api['price_per_call'], $log_user_id]);
            }
        }
        
        $stmt_log = $pdo->prepare("INSERT INTO sl_api_logs (api_id, user_id, ip_address, response_code, is_success) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$log_api_id, $log_user_id, $log_ip_address, 200, $is_success ? 1:0]);
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }

} catch (PDOException $e) {
    api_error_exit(500, '内部服务器错误: 数据库连接失败');
}

ob_end_clean();
