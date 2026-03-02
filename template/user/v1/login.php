<?php
@session_start([
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);
@error_reporting(0);
@ini_set('display_errors', 'Off');
$rootPath = dirname(dirname(dirname(__DIR__)));
define('ROOT_PATH', $rootPath . '/');
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ROOT_PATH);
    exit;
}
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。");
}
require_once ROOT_PATH . 'config.php';

$error_msg = '';

try {
    $pdo_check = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $stmt_settings = $pdo_check->prepare(
        "SELECT setting_value FROM sl_settings WHERE setting_key = ? LIMIT 1"
    );
    $stmt_settings->execute(['mail_forgot_enabled']);
    $mail_forgot_enabled = ($stmt_settings->fetchColumn() == 1);
} catch(Exception $e) {
    $mail_forgot_enabled = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(mb_substr($_POST['username'], 0, 20));
    $password = $_POST['password'];
    $captcha = trim(strtolower($_POST['captcha']));

    $is_valid = true;
    if (empty($username) || empty($password) || empty($captcha)) {
        $is_valid = false;
    } elseif (!isset($_SESSION['captcha']) || $captcha != $_SESSION['captcha']) {
        $is_valid = false;
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare(
                "SELECT id, username, password, status
                 FROM sl_users
                 WHERE username = ?
                 LIMIT 1"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || ($user && !password_verify($password, $user['password']) && $password !== $user['password']) || $user['status'] !== 'active') {
                $is_valid = false;
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                unset($_SESSION['captcha']);
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $is_valid = false;
        }
    }

    if (!$is_valid) {
        $error_msg = '请检查用户名密码验证码是否正确';
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>用户登录</title>
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
<style>
.signin-form .has-feedback {
    position: relative;
}
.signin-form .has-feedback .form-control {
    padding-left: 36px;
}
.signin-form .has-feedback .mdi {
    position: absolute;
    top: 0;
    left: 0;
    width: 36px;
    height: 36px;
    line-height: 36px;
    color: #dcdcdc;
    text-align: center;
    pointer-events: none;
}
.form-link {
    font-size: 13px;
    color: #4a69bd;
    text-decoration: none;
    font-weight: 500;
}
.captcha-wrap {
    display: flex;
    gap: 10px;
    align-items: center;
}
#captcha-img {
    cursor: pointer;
    height: 36px;
    border-radius: 4px;
    border: 1px solid #eee;
}
.alert-danger {
    background-color: #fff1f0;
    border-color: #ffccc7;
    color: #dc3545;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 6px;
    text-align: center;
}
</style>
</head>
<body class="center-vh" style="background-image: url(../../../assets/images/login-bg-2.jpg); background-size: cover;">
<div class="card card-shadowed p-5 mb-0 mr-2 ml-2">
  <div class="text-center mb-3">
    <a href="../"> <img alt="logo" src="../../../assets/images/logo-sidebar.png"> </a>
  </div>

  <h4 class="text-center mb-3">欢迎回来</h4>
  <p class="text-center text-muted mb-4">登录以继续使用我们的服务</p>
  
  <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
  <?php endif; ?>

  <?php
  if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
      echo '<div class="alert alert-warning text-center">服务器未开启GD扩展，验证码无法显示！请联系管理员开启GD库。</div>';
  }
  ?>

  <form method="POST" action="login.php" class="signin-form">
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-account" aria-hidden="true"></span>
      <input type="text" id="username" name="username" class="form-control" placeholder="用户名" required>
    </div>

    <div class="mb-3 has-feedback">
      <span class="mdi mdi-lock" aria-hidden="true"></span>
      <input type="password" id="password" name="password" class="form-control" placeholder="密码" required>
      <?php if ($mail_forgot_enabled): ?>
      <div class="text-right mt-2">
        <a href="forgot_password.php" class="form-link">忘记密码？</a>
      </div>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <div class="captcha-wrap">
        <input type="text" name="captcha" class="form-control" placeholder="请输入验证码" maxlength="4" required>
        <img src="captcha.php" alt="验证码（点击刷新）" id="captcha-img" title="点击刷新验证码">
      </div>
    </div>

    <div class="mb-3 d-grid">
      <button class="btn btn-primary" type="submit">登 录</button>
    </div>
  </form>
  
  <p class="text-center text-muted mb-0">还没有账户？ <a href="register.php">立即注册</a></p>
</div>

<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script>
document.getElementById('captcha-img').onclick = function() {
    this.src = 'captcha.php?rand=' + new Date().getTime();
}
</script>
</body>
</html>