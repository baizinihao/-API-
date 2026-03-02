<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '添加新方案';
$plan = ['id' => null, 'name' => '', 'description' => '', 'price' => '', 'balance_to_add' => '', 'is_active' => 1];
$edit_mode = isset($_GET['id']);
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($edit_mode) {
        $page_title = '编辑方案';
        $id_to_edit = intval($_GET['id']);
        $stmt_get = $pdo->prepare("SELECT * FROM sl_billing_plans WHERE id = ?"); $stmt_get->execute([$id_to_edit]);
        $plan = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$plan) { header('Location: billing_plans.php'); exit; }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = trim($_POST['price']);
        $balance_to_add = trim($_POST['balance_to_add']);
        $is_active = intval($_POST['is_active']);
        if (empty($name) || !is_numeric($price) || !is_numeric($balance_to_add)) throw new Exception('方案名称不能为空，价格和余额必须是数字。');
        if ($id) {
            $sql = "UPDATE sl_billing_plans SET name = ?, description = ?, price = ?, balance_to_add = ?, is_active = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql); $stmt->execute([$name, $description, $price, $balance_to_add, $is_active, $id]);
            $_SESSION['feedback_msg'] = '方案已成功更新。';
        } else {
            $sql = "INSERT INTO sl_billing_plans (name, description, price, balance_to_add, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql); $stmt->execute([$name, $description, $price, $balance_to_add, $is_active]);
            $_SESSION['feedback_msg'] = '新方案已成功添加。';
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: billing_plans.php'); exit;
    }
} catch (Exception $e) { $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error'; }
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <style>
        .sidebar-logo {
            width: 36px;
            height: 36px;
            background-color: #111827;
            color: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            margin-right: 12px;
        }
        .nav-item.active {
            background-color: #eef2ff;
            color: #4f46e5;
            font-weight: 600;
        }
        .nav-item.active .nav-icon {
            color: #4f46e5;
        }
        .feedback-alert {
            padding: 16px;
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 24px;
            border: 1px solid transparent;
        }
        .feedback-alert.error {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #991b1b;
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
            <p class="card-subtitle mb-0">在此处创建或编辑您的计费方案。</p>
        </header>
        <div class="card-body">
          
          <?php if ($feedback_msg): ?>
          <div class="feedback-alert error"><?php echo htmlspecialchars($feedback_msg); ?></div>
          <?php endif; ?>
          
          <form method="POST" action="billing_plan_edit.php<?php echo $edit_mode ? '?id='.$plan['id'] : ''; ?>" class="row">
            <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
            <div class="mb-3 col-md-6">
              <label for="name" class="form-label">方案名称</label>
              <input type="text" class="form-control" id="name" name="name" placeholder="例如：新手体验包" value="<?php echo htmlspecialchars($plan['name']); ?>" required>
            </div>
            <div class="mb-3 col-md-6">
              <label for="price" class="form-label">价格 (元)</label>
              <input type="number" step="0.01" class="form-control" id="price" name="price" placeholder="10.00" value="<?php echo htmlspecialchars($plan['price']); ?>" required>
            </div>
            <div class="mb-3 col-md-12">
              <label for="description" class="form-label">方案描述</label>
              <textarea class="form-control" id="description" name="description" rows="3" placeholder="简单描述这个方案包含的内容，如"充值10元，立得10元余额""><?php echo htmlspecialchars($plan['description']); ?></textarea>
            </div>
            <div class="mb-3 col-md-6">
              <label for="balance_to_add" class="form-label">可得余额 (元)</label>
              <input type="number" step="0.01" class="form-control" id="balance_to_add" name="balance_to_add" placeholder="10.00" value="<?php echo htmlspecialchars($plan['balance_to_add']); ?>" required>
            </div>
            <div class="mb-3 col-md-6">
              <label for="is_active" class="form-label">状态</label>
              <select class="form-select" id="is_active" name="is_active">
                <option value="1" <?php echo $plan['is_active'] == 1 ? 'selected' : ''; ?>>上架 (用户可见)</option>
                <option value="0" <?php echo $plan['is_active'] == 0 ? 'selected' : ''; ?>>下架 (仅后台可见)</option>
              </select>
            </div>
            <div class="mb-3 col-md-12">
              <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? '更新方案' : '添加方案'; ?></button>
              <button type="button" class="btn btn-default" onclick="javascript:history.back(-1);return false;">返 回</button>
            </div>
          </form>
          
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