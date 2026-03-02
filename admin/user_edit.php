<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '添加新用户';
$user = ['id' => null, 'username' => '', 'email' => '', 'status' => 'active', 'balance' => '0.00'];
$edit_mode = isset($_GET['id']);
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($edit_mode) {
        $page_title = '编辑用户';
        $id_to_edit = intval($_GET['id']);
        $stmt_get = $pdo->prepare("SELECT * FROM sl_users WHERE id = ?"); $stmt_get->execute([$id_to_edit]);
        $user = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$user) { header('Location: user_list.php'); exit; }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $pdo->beginTransaction();
        try {
            if (isset($_POST['update_profile'])) {
                $new_username = trim($_POST['username']); $new_email = trim($_POST['email']); $new_password = $_POST['password']; $new_status = $_POST['status'];
                if (empty($new_username) || empty($new_email)) throw new Exception('用户名和邮箱不能为空。');
                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) throw new Exception('邮箱格式不正确。');
                $stmt_check_user = $pdo->prepare("SELECT id FROM sl_users WHERE username = ? AND id != ?"); $stmt_check_user->execute([$new_username, $user_id ?: 0]);
                if ($stmt_check_user->fetch()) throw new Exception('该用户名已被使用。');
                $stmt_check_email = $pdo->prepare("SELECT id FROM sl_users WHERE email = ? AND id != ?"); $stmt_check_email->execute([$new_email, $user_id ?: 0]);
                if ($stmt_check_email->fetch()) throw new Exception('该邮箱已被使用。');
                if ($user_id) {
                    $sql = "UPDATE sl_users SET username = ?, email = ?, status = ?"; $params = [$new_username, $new_email, $new_status];
                    if (!empty($new_password)) { $sql .= ", password = ?"; $params[] = $new_password; }
                    $sql .= " WHERE id = ?"; $params[] = $user_id;
                    $stmt = $pdo->prepare($sql); $stmt->execute($params);
                    $_SESSION['feedback_msg'] = '用户资料已成功更新。';
                } else {
                    if (empty($new_password)) throw new Exception('添加新用户时必须设置密码。');
                    $api_key = bin2hex(random_bytes(32));
                    $sql = "INSERT INTO sl_users (username, email, password, api_key, status) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$new_username, $new_email, $new_password, $api_key, $new_status]);
                    $_SESSION['feedback_msg'] = '新用户已成功添加。';
                }
            } elseif (isset($_POST['adjust_balance'])) {
                if(!$user_id) throw new Exception('无法为新用户调整余额。');
                $adjustment_type = $_POST['adjustment_type']; $amount = floatval($_POST['adjustment_amount']);
                if ($amount <= 0) throw new Exception('调整金额必须大于0。');
                if ($adjustment_type === 'deduct') {
                    $stmt_balance = $pdo->prepare("SELECT balance FROM sl_users WHERE id = ?"); $stmt_balance->execute([$user_id]);
                    $current_balance = $stmt_balance->fetchColumn();
                    if ($current_balance < $amount) throw new Exception('用户余额不足，无法完成扣款。');
                    $sql = "UPDATE sl_users SET balance = balance - ? WHERE id = ?";
                } else { $sql = "UPDATE sl_users SET balance = balance + ? WHERE id = ?"; }
                $stmt = $pdo->prepare($sql); $stmt->execute([$amount, $user_id]);
                $_SESSION['feedback_msg'] = '用户余额已成功调整。';
            }
            $pdo->commit();
            $_SESSION['feedback_type'] = 'success';
            header('Location: user_list.php'); exit;
        } catch (Exception $e) { $pdo->rollBack(); throw $e; }
    }
} catch (Exception $e) { $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error'; }
$current_page_script = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <style>
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #eef2ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .balance-display {
            font-size: 18px;
            font-weight: 600;
            color: #4f46e5;
        }
        .section-divider {
            font-size: 18px;
            font-weight: 600;
            margin-top: 32px;
            margin-bottom: 16px;
            border-top: 1px solid #e5e7eb;
            padding-top: 24px;
        }
        .feedback-alert.error {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
    </style>
</head>
  
<body>
<div class="container-fluid">
  
  <div class="row">
    
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
            <div class="card-title"><?php echo $page_title; ?></div>
            <small>在此处管理用户的详细信息和账户余额</small>
        </header>
        <div class="card-body">
          
          <?php if ($feedback_msg): ?>
          <div class="feedback-alert error"><?php echo htmlspecialchars($feedback_msg); ?></div>
          <?php endif; ?>
          
          <form method="POST" action="user_edit.php<?php echo $edit_mode ? '?id='.$user['id'] : ''; ?>" class="row">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            
            <div class="mb-3 col-md-6">
              <label for="username" class="form-label">用户名</label>
              <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" placeholder="请输入用户名" required>
            </div>
            
            <div class="mb-3 col-md-6">
              <label for="email" class="form-label">邮箱</label>
              <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="请输入邮箱" required>
            </div>
            
            <div class="mb-3 col-md-6">
              <label for="password" class="form-label">密码</label>
              <input type="password" class="form-control" id="password" name="password" placeholder="<?php echo $edit_mode ? '留空则不修改密码' : '设置初始密码'; ?>" <?php echo !$edit_mode ? 'required' : ''; ?>>
            </div>
            
            <div class="mb-3 col-md-6">
              <label for="status" class="form-label">账户状态</label>
              <select id="status" name="status" class="form-select">
                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>正常</option>
                <option value="banned" <?php echo $user['status'] === 'banned' ? 'selected' : ''; ?>>封禁</option>
                <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>待审核</option>
              </select>
            </div>
            
            <div class="mb-3 col-md-12">
              <button type="submit" name="update_profile" class="btn btn-primary">保存基本信息</button>
            </div>
          </form>
          
          <?php if ($edit_mode): ?>
          <h3 class="section-divider">账户余额调整</h3>
          
          <form method="POST" action="user_edit.php?id=<?php echo $user['id']; ?>" class="row">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            
            <div class="mb-3 col-md-6">
              <label class="form-label">当前余额</label>
              <div class="balance-display">¥ <?php echo number_format($user['balance'], 2); ?></div>
            </div>
            
            <div class="mb-3 col-md-6">
              <label for="adjustment_type" class="form-label">操作类型</label>
              <select id="adjustment_type" name="adjustment_type" class="form-select">
                <option value="add">增加余额</option>
                <option value="deduct">扣除余额</option>
              </select>
            </div>
            
            <div class="mb-3 col-md-6">
              <label for="adjustment_amount" class="form-label">调整金额</label>
              <input type="number" step="0.01" class="form-control" id="adjustment_amount" name="adjustment_amount" placeholder="请输入要调整的金额" required>
            </div>
            
            <div class="mb-3 col-md-12">
              <button type="submit" name="adjust_balance" class="btn btn-primary">确认调整</button>
              <button type="button" class="btn btn-default" onclick="javascript:history.back(-1);return false;">返回</button>
            </div>
          </form>
          <?php endif; ?>
          
        </div>
      </div>
    </div>
        
  </div>
  
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
</body>
</html>