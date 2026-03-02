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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, '无效的请求方式。');
}
if (!file_exists('../../config.php')) {
    json_response(false, '系统错误: 配置文件丢失。');
}
require_once '../../config.php';
$captcha = strtolower(trim($_POST['captcha'] ?? ''));
$email_code = trim($_POST['email_code'] ?? '');
$type = $_POST['type'] ?? '';
$api_id = isset($_POST['api_id']) && $_POST['api_id'] !== '' ? intval($_POST['api_id']) : null;
$content = trim($_POST['content'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['user_email'] ?? null;
if (empty($captcha)) {
    json_response(false, '请输入图形验证码');
} elseif (!isset($_SESSION['captcha_code']) || $captcha !== $_SESSION['captcha_code']) {
    json_response(false, '图形验证码错误或已过期，请刷新后重新输入');
}
if (empty($email_code) || empty($_SESSION['friend_link_code']) || $email_code !== $_SESSION['friend_link_code']) {
    json_response(false, '邮箱验证码错误，请重新输入');
}
if (empty($_SESSION['friend_link_email']) || $_SESSION['friend_link_email'] !== $contact || $_SESSION['friend_link_email'] !== $user_email) {
    json_response(false, '验证邮箱与登录账号不一致，请刷新页面重试');
}
if (empty($type) || empty($content)) {
    json_response(false, '反馈类型和内容不能为空。');
}
if ($type === 'api' && $api_id === null) {
    json_response(false, '请选择一个需要反馈的接口。');
}
if (empty($user_id) || empty($user_email)) {
    json_response(false, '登录状态失效，请重新登录后尝试');
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $api_name = '';
    if ($type === 'api' && $api_id) {
        $stmt_api = $pdo->prepare("SELECT name FROM sl_apis WHERE id = ? LIMIT 1");
        $stmt_api->execute([$api_id]);
        $api_name = $stmt_api->fetchColumn() ?: '未知接口';
    }
    $sql = "INSERT INTO sl_feedback (user_id, api_id, type, content, contact) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $api_id, $type, $content, $contact]);
    $feedback_id = $pdo->lastInsertId();
    if(!$feedback_id) {
        throw new Exception("无法将反馈存入数据库。");
    }
    try {
        $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
        $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
        $admin_email_stmt = $pdo->query("SELECT email FROM sl_admins ORDER BY id ASC LIMIT 1");
        $admin_email = $admin_email_stmt->fetchColumn();
        if ($admin_email && !empty($settings['mail_smtp_host']) && !empty($settings['mail_smtp_user']) && !empty($settings['mail_smtp_pass'])) {
            if (file_exists('../../common/PHPMailer/src/Exception.php')) {
                require '../../common/PHPMailer/src/Exception.php';
                require '../../common/PHPMailer/src/PHPMailer.php';
                require '../../common/PHPMailer/src/SMTP.php';
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $settings['mail_smtp_host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $settings['mail_smtp_user'];
                $mail->Password   = $settings['mail_smtp_pass'];
                $mail->SMTPSecure = $settings['mail_smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = intval($settings['mail_smtp_port']);
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom($settings['mail_smtp_user'], ($settings['site_name'] ?? '白子API') . ' 反馈通知');
                $mail->addAddress($admin_email);
                $mail->isHTML(true);
                $mail->Subject = '【' . ($settings['site_name'] ?? '系统') . '通知】新用户反馈 - ' . ($type === 'api' ? '接口问题' : '意见建议');
$mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {font-family: "Helvetica Neue", Arial, sans-serif;line-height: 1.6;color: #333;max-width: 700px;margin: 0 auto;padding: 0;background-color: #f5f7fa;}
        .container {background: #fff;border-radius: 8px;box-shadow: 0 3px 10px rgba(0,0,0,0.08);overflow: hidden;margin: 20px auto;}
        .header {background-color: #4096ff;color: white;padding: 25px 30px;font-size: 18px;border-bottom: 4px solid #337ecc;}
        .content {padding: 30px;}
        .info-card {background: #f9f9f9;border-radius: 6px;padding: 20px;margin-bottom: 25px;border-left: 4px solid #54a0ff;}
        .info-label {display: inline-block;min-width: 80px;color: #666;font-weight: bold;vertical-align: top;}
        .info-value {display: inline-block;width: calc(100% - 100px);}
        .content-text {background: #fff;border: 1px solid #eee;padding: 15px;border-radius: 4px;margin: 15px 0;line-height: 1.7;}
        .footer {padding: 20px;text-align: center;color: #777;font-size: 13px;border-top: 1px solid #eee;background: #fafafa;}
        .btn {display: inline-block;background: #4096ff;color: white !important;text-decoration: none;padding: 10px 20px;border-radius: 4px;margin: 20px 0;font-weight: bold;}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">📩 新的用户反馈通知</div>
        <div class="content">
            <p>尊敬的管理员：</p>
            <p>系统收到一条新的用户反馈，请及时处理：</p>
            <div class="info-card">
                <div style="margin-bottom: 15px;"><span class="info-label">反馈类型</span><span class="info-value"><strong>' . ($type === 'api' ? '接口问题' : '意见建议') . '</strong></span></div>
                <div style="margin-bottom: 15px;"><span class="info-label">用户邮箱</span><span class="info-value">' . htmlspecialchars($contact) . '</span></div>
                <div style="margin-bottom: 15px;"><span class="info-label">提交时间</span><span class="info-value">' . date('Y-m-d H:i:s') . '</span></div>
                ' . ($type === 'api' ? '<div><span class="info-label">关联接口</span><span class="info-value">ID：' . $api_id . ' | 名称：' . htmlspecialchars($api_name) . '</span></div>' : '') . '
            </div>
            <div>
                <div class="info-label" style="margin-bottom: 10px;">反馈内容</div>
                <div class="content-text">' . nl2br(htmlspecialchars($content)) . '</div>
            </div>
            <center><a href="' . ($settings['admin_url'] ?? '#') . '" class="btn">立即登录后台处理</a></center>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' ' . ($settings['site_name'] ?? '系统') . ' 管理后台</p>
            <p>此邮件为系统自动发送，请勿直接回复</p>
        </div>
    </div>
</body>
</html>
';
                $mail->send();
            }
        }
    } catch (Exception $mail_error) {}
    unset($_SESSION['captcha_code'], $_SESSION['friend_link_code'], $_SESSION['friend_link_email']);
    json_response(true, '您的反馈已成功提交，我们会尽快处理并回复！');
} catch (Exception $e) {
    json_response(false, '提交失败，请稍后重试。');
}
?>