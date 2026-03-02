<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function json_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(false, '无效的请求方式。'); }
if (!file_exists('../../config.php')) { json_response(false, '系统错误: 配置文件丢失。'); }
require_once '../../config.php';

$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
$captcha = strtolower(trim($_POST['captcha'] ?? ''));

if (!$email) { json_response(false, '请输入有效的邮箱地址。'); }
if (empty($captcha) || !isset($_SESSION['captcha_code']) || $captcha !== $_SESSION['captcha_code']) {
    unset($_SESSION['captcha_code']);
    json_response(false, '人机验证码不正确。');
}
unset($_SESSION['captcha_code']); 

if (isset($_SESSION['last_temp_key_sent']) && time() - $_SESSION['last_temp_key_sent'] < 60) {
    json_response(false, '请求过于频繁，请稍后再试。');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($settings['allow_temp_key'])) { json_response(false, '此功能已暂时关闭。'); }
    if (empty($settings['mail_smtp_host']) || empty($settings['mail_smtp_user']) || empty($settings['mail_smtp_pass'])) {
        json_response(false, '系统邮件服务未配置，无法发送密钥，请联系管理员。');
    }
    
    $stmt_check_email = $pdo->prepare("SELECT id FROM sl_users WHERE email = ?");
    $stmt_check_email->execute([$email]);
    if ($stmt_check_email->fetch()) { json_response(false, '该邮箱已被注册，请直接登录或找回密码。'); }

    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt_ip_check = $pdo->prepare("SELECT COUNT(*) FROM sl_temp_key_logs WHERE ip_address = ? AND created_at >= CURDATE()");
    $stmt_ip_check->execute([$ip_address]);
    if ($stmt_ip_check->fetchColumn() > 0) { json_response(false, '每个IP地址每天只能申请一次。'); }
    
    $pdo->beginTransaction();
    
    $duration_hours = intval($settings['temp_key_duration'] ?? 24);
    $limit_calls = intval($settings['temp_key_limit'] ?? 100);
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));
    
    $temp_username = 'temp_' . bin2hex(random_bytes(8));
    $temp_password = bin2hex(random_bytes(16));
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    $api_key = bin2hex(random_bytes(32));

    $sql = "INSERT INTO sl_users (username, email, password, api_key, status, call_limit, expires_at) VALUES (?, ?, ?, ?, 'active', ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$temp_username, $email, $hashed_password, $api_key, $limit_calls, $expires_at]);
    $last_user_id = $pdo->lastInsertId();

    if(!$last_user_id) { throw new Exception("创建临时用户失败。"); }

    if (!file_exists('../../common/PHPMailer/src/Exception.php')) { throw new Exception("邮件库缺失。"); }
    require '../../common/PHPMailer/src/Exception.php';
    require '../../common/PHPMailer/src/PHPMailer.php';
    require '../../common/PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $settings['mail_smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['mail_smtp_user'];
        $mail->Password   = $settings['mail_smtp_pass'];
        $mail->SMTPSecure = $settings['mail_smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = intval($settings['mail_smtp_port']);
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($settings['mail_smtp_user'], ($settings['site_name']) . ' 临时密钥');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = '您的临时API密钥已生成';
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4285f4;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 25px;
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
        }
        .key-box {
            background-color: #f8f9fa;
            border-left: 4px solid #4285f4;
            padding: 15px;
            margin: 20px 0;
            font-family: "Courier New", monospace;
            word-break: break-all;
            color: #202124;
        }
        .info-item {
            margin-bottom: 15px;
        }
        .info-label {
            font-weight: bold;
            color: #5f6368;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #70757a;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>' . ($settings['site_name'] ?? 'API服务') . ' 临时访问密钥</h2>
    </div>
    
    <div class="content">
        <p>尊敬的客户，您好！</p>
        <p>您已成功申请临时API访问权限，以下是您的凭证信息：</p>
        
        <div class="key-box">
            <div style="margin-bottom: 10px;"><strong>API密钥：</strong></div>
            ' . $api_key . '
        </div>
        
        <div class="info-item">
            <span class="info-label">调用次数限制：</span>
            ' . $limit_calls . ' 次
        </div>
        
        <div class="info-item">
            <span class="info-label">有效期至：</span>
            ' . $expires_at . '
        </div>
        
        <p style="margin-top: 25px;">请注意：</p>
        <ul style="margin-top: 5px; padding-left: 20px;">
            <li>请妥善保管您的API密钥，不要泄露给他人</li>
            <li>此密钥仅用于临时测试用途</li>
            <li>到期后将自动失效</li>
        </ul>
    </div>
    
    <div class="footer">
        <p>© ' . date('Y') . ' ' . ($settings['site_name'] ?? 'API服务') . ' 版权所有</p>
        <p>此为系统自动发送邮件，请勿直接回复</p>
    </div>
</body>
</html>
';
        $mail->send();
    } catch (Exception $e) {
        $pdo->rollBack(); 
        json_response(false, "邮件发送失败，请检查您的邮箱地址或联系管理员。错误: {$mail->ErrorInfo}");
    }

    $stmt_log_ip = $pdo->prepare("INSERT INTO sl_temp_key_logs (ip_address) VALUES (?)");
    $stmt_log_ip->execute([$ip_address]);
    $pdo->commit();
    
    $_SESSION['last_temp_key_sent'] = time();
    json_response(true, '申请成功！临时密钥已发送至您的邮箱，请注意查收。');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    json_response(false, '申请失败，请稍后重试。');
}
?>