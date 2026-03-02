    <?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');

function json_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(false, '无效的请求方式。'); }
if (!isset($_SESSION['user_id'])) { json_response(false, '请先登录。'); }
if (!file_exists('../../config.php')) { json_response(false, '系统错误: 配置文件丢失。'); }
require_once '../../config.php';

$cdkey = trim($_POST['cdkey'] ?? '');
$user_id = $_SESSION['user_id'];

if (empty($cdkey)) { json_response(false, '请输入卡密。'); }

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();
    
    $stmt_cdkey = $pdo->prepare("SELECT * FROM sl_cdkeys WHERE cdkey = ? AND status = 'unused' FOR UPDATE");
    $stmt_cdkey->execute([$cdkey]);
    $key_data = $stmt_cdkey->fetch(PDO::FETCH_ASSOC);

    if (!$key_data) {
        $pdo->rollBack();
        json_response(false, '卡密无效或已被使用。');
    }

    $balance_to_add = $key_data['balance'];

    $stmt_update_user = $pdo->prepare("UPDATE sl_users SET balance = balance + ? WHERE id = ?");
    $stmt_update_user->execute([$balance_to_add, $user_id]);

    $stmt_update_cdkey = $pdo->prepare("UPDATE sl_cdkeys SET status = 'used', used_by_user_id = ?, used_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt_update_cdkey->execute([$user_id, $key_data['id']]);

    $pdo->commit();

    json_response(true, "兑换成功！您的账户已增加 ¥" . number_format($balance_to_add, 2) . " 余额。");

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(false, '兑换失败，请稍后重试。');
}
?>