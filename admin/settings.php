<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '系统设置';
$settings_keys = [
    'site_name', 'site_description', 'copyright_info', 'allow_registration', 'allow_temp_key',
    'temp_key_duration', 'temp_key_limit',
    'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_secure', 'mail_smtp_user', 'mail_smtp_pass',
    'mail_reg_enabled', 'mail_forgot_enabled','enable_qps_limit'
];
$defaults = [
    'site_name' => 'xiaobai', 'site_description' => '一个稳定、快速、易用的高质量API服务平台', 'copyright_info' => '1GDP2025',
    'allow_registration' => 1, 'allow_temp_key' => 1, 'temp_key_duration' => 24, 'temp_key_limit' => 100,
    'mail_smtp_host' => '', 'mail_smtp_port' => '465', 'mail_smtp_secure' => 'ssl', 'mail_smtp_user' => '', 'mail_smtp_pass' => '',
    'mail_reg_enabled' => 0, 'mail_forgot_enabled' => 0,
    'enable_qps_limit' => 0
];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $init_sql = "INSERT IGNORE INTO sl_settings (setting_key, setting_value) VALUES ('site_name', 'xiaobai'), ('site_description', '一个稳定、快速、易用的高质量API服务平台'), ('copyright_info', '1GDP2025'), ('allow_registration', '1'), ('allow_temp_key', '1'), ('temp_key_duration', '24'), ('temp_key_limit', '100'), ('mail_smtp_host', ''), ('mail_smtp_port', '465'), ('mail_smtp_secure', 'ssl'), ('mail_smtp_user', ''), ('mail_smtp_pass', ''), ('mail_reg_enabled', '0'), ('mail_forgot_enabled', '0');";
    $pdo->exec($init_sql);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE sl_settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($settings_keys as $key) {
            if(in_array($key, ['allow_registration', 'allow_temp_key', 'mail_reg_enabled', 'mail_forgot_enabled'])) {
                $value = isset($_POST[$key]) ? '1' : '0';
            } else {
                $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
            }
            $stmt->execute([$value, $key]);
        }
        $pdo->commit();
        $feedback_msg = '设置已成功保存。';
        $feedback_type = 'success';
    }
    $stmt_get = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
    $db_settings = $stmt_get->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($defaults, $db_settings);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error';
}
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
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
  
<body>
<div class="container-fluid">
  
  <div class="row">
    
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header"><div class="card-title">系统设置</div></header>
        <div class="card-body">
          
          <ul class="nav nav-tabs">
            <li class="nav-item">
              <button class="nav-link active" id="basic-config" data-bs-toggle="tab" data-bs-target="#config" type="button">基本设置</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="basic-function" data-bs-toggle="tab" data-bs-target="#function" type="button">功能设置</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="basic-mail" data-bs-toggle="tab" data-bs-target="#mail" type="button">邮件设置</button>
            </li>
          </ul>
          
          <form method="POST" action="settings.php" class="edit-form">
            <div class="tab-content">
              <div class="tab-pane fade show active" id="config" aria-labelledby="basic-config">
                <?php if ($feedback_msg): ?>
                <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> mb-3">
                  <?php echo htmlspecialchars($feedback_msg); ?>
                </div>
                <?php endif; ?>
              
                <div class="mb-3">
                  <label for="site_name" class="form-label">网站标题</label>
                  <input class="form-control" type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" placeholder="请输入站点标题">
                </div>
                <div class="mb-3">
                  <label for="site_description" class="form-label">网站描述</label>
                  <textarea class="form-control" id="site_description" rows="5" name="site_description" placeholder="请输入站点描述"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                </div>
                <div class="mb-3">
                  <label for="copyright_info" class="form-label">版权信息</label>
                  <input class="form-control" type="text" id="copyright_info" name="copyright_info" value="<?php echo htmlspecialchars($settings['copyright_info']); ?>" placeholder="请输入版权信息">
                </div>
                <div>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
              </div>
              
              <div class="tab-pane fade" id="function" aria-labelledby="basic-function">
                <div class="mb-3">
                  <label class="form-label">允许用户注册</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="allow_registration" name="allow_registration" value="1" <?php echo $settings['allow_registration'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_registration"></label>
                  </div>
                  <small class="form-text">关闭后，前台将注册入口禁止访问</small>
                </div>
                
                <div class="mb-3">
                  <label class="form-label">允许申请临时密钥</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="allow_temp_key" name="allow_temp_key" value="1" <?php echo $settings['allow_temp_key'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_temp_key"></label>
                  </div>
                  <small class="form-text">关闭后，前台将隐藏申请临时密钥的入口</small>
                </div>
                
                <div class="mb-3">
                  <label for="temp_key_duration" class="form-label">临时密钥有效时间 (小时)</label>
                  <input class="form-control" type="number" id="temp_key_duration" name="temp_key_duration" value="<?php echo htmlspecialchars($settings['temp_key_duration']); ?>" placeholder="请输入有效时间">
                </div>
                <div class="mb-3">
    <label class="form-label">启用QPS限速</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_qps_limit" name="enable_qps_limit" value="1" <?php echo $settings['enable_qps_limit'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_qps_limit"></label>
    </div>
    <small class="form-text">开启后，系统将根据用户组设置的QPS限制进行API请求频率控制【还没写】</small>
</div>

                <div class="mb-3">
                  <label for="temp_key_limit" class="form-label">临时密钥调用次数限制</label>
                  <input class="form-control" type="number" id="temp_key_limit" name="temp_key_limit" value="<?php echo htmlspecialchars($settings['temp_key_limit']); ?>" placeholder="请输入调用次数限制">
                </div>
                
                <div>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
              </div>
              
              <div class="tab-pane fade" id="mail" aria-labelledby="basic-mail">
                <div class="mb-3">
                  <label class="form-label">开启邮件验证码注册</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="mail_reg_enabled" name="mail_reg_enabled" value="1" <?php echo $settings['mail_reg_enabled'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mail_reg_enabled"></label>
                  </div>
                  <small class="form-text">开启后，用户注册时必须通过邮箱验证码</small>
                </div>
                
                <div class="mb-3">
                  <label class="form-label">开启邮件找回密码</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="mail_forgot_enabled" name="mail_forgot_enabled" value="1" <?php echo $settings['mail_forgot_enabled'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mail_forgot_enabled"></label>
                  </div>
                  <small class="form-text">开启后，用户可以通过邮箱重置密码</small>
                </div>
                
                <div class="mb-3">
                  <label for="mail_smtp_host" class="form-label">SMTP 服务器</label>
                  <input class="form-control" type="text" id="mail_smtp_host" name="mail_smtp_host" value="<?php echo htmlspecialchars($settings['mail_smtp_host']); ?>" placeholder="例如：smtp.qq.com">
                </div>
                
                <div class="mb-3">
                  <label for="mail_smtp_port" class="form-label">端口</label>
                  <input class="form-control" type="number" id="mail_smtp_port" name="mail_smtp_port" value="<?php echo htmlspecialchars($settings['mail_smtp_port']); ?>" placeholder="例如：465">
                </div>
                
                <div class="mb-3">
                  <label for="mail_smtp_secure" class="form-label">加密方式</label>
                  <select class="form-select" id="mail_smtp_secure" name="mail_smtp_secure">
                    <option value="ssl" <?php echo $settings['mail_smtp_secure'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo $settings['mail_smtp_secure'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                  </select>
                </div>
                
                <div class="mb-3">
                  <label for="mail_smtp_user" class="form-label">发信邮箱账户</label>
                  <input class="form-control" type="email" id="mail_smtp_user" name="mail_smtp_user" value="<?php echo htmlspecialchars($settings['mail_smtp_user']); ?>" placeholder="例如：yourname@qq.com">
                </div>
                
                <div class="mb-3">
                  <label for="mail_smtp_pass" class="form-label">发信邮箱密码/授权码</label>
                  <input class="form-control" type="password" id="mail_smtp_pass" name="mail_smtp_pass" value="<?php echo htmlspecialchars($settings['mail_smtp_pass']); ?>" placeholder="请输入密码或授权码">
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                  <a href="mail_test.php" class="text-primary">发送测试邮件...</a>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
              </div>
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