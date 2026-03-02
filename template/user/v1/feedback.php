<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
function checkUserLoginStatus() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT username, email FROM sl_users WHERE id = ? AND status = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            return true;
        }
    } catch (PDOException $e) {
        error_log("登录状态检查错误: " . $e->getMessage());
    }
    unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['user_email']);
    return false;
}
$is_logged_in = checkUserLoginStatus();
if (!$is_logged_in) {
    header("HTTP/1.1 403 Forbidden");
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>访问被拒绝</title>
    <style>
        body {font-family: Arial, sans-serif;text-align: center;padding: 50px;}
        .container {max-width: 600px;margin: 0 auto;}
        h1 {color: #d9534f;}
        .btn {display: inline-block;padding: 10px 20px;background: #337ab7;color: white;text-decoration: none;border-radius: 4px;margin-top: 20px;}
        .btn:hover {background: #286090;}
    </style>
</head>
<body>
    <div class="container">
        <h1>访问被拒绝</h1>
        <p>意见反馈需要登录后才能操作，请先登录账号</p>
        <a href="/" class="btn">返回首页并登录</a>
    </div>
</body>
</html>
<?php
    exit;
}
$user_info = ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']];
$apis = []; $settings = [];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_apis = $pdo->query("SELECT id, name FROM sl_apis ORDER BY name ASC");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {}
$site_name = $settings['site_name'] ?? '白子API';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
<style>
.captcha-box{display:flex;gap:0.8rem;align-items:center;}
.captcha-img{width:120px;height:48px;border-radius:0.5rem;cursor:pointer;}
.get-code-btn{white-space:nowrap;}
.btn-disabled{background-color:#c9cdd4!important;cursor:not-allowed!important;}
.alert{border-radius:0.5rem!important;border:none!important;padding:1rem 1.25rem!important;margin-bottom:1.5rem!important;}
.form-control{border-radius:0.5rem!important;}
.btn{border-radius:0.5rem!important;}
</style>
</head>
<body>
<div class="container-fluid py-3">
  <div class="row">
    <div class="col-lg-8 col-md-10 mx-auto">
      <div class="card shadow-sm">
        <header class="card-header bg-white py-3"><div class="card-title h5 mb-0">意见反馈</div></header>
        <div class="card-body py-4">
          <div class="alert alert-info mb-4">
            <i class="mdi mdi-information-outline me-2"></i>感谢您的宝贵意见，它将帮助我们不断改进产品与服务。
          </div>
          <form id="feedback-form" method="post" class="form-horizontal needs-validation" novalidate>
            <div id="feedback-result"></div>
            <div class="mb-4">
              <label for="feedback_type" class="form-label fw-medium">反馈类型</label>
              <select id="feedback_type" name="type" class="form-select" required>
                <option value="">请选择反馈类型</option>
                <option value="general">意见与建议</option>
                <option value="api">接口问题反馈</option>
              </select>
              <div class="invalid-feedback">请选择反馈类型</div>
            </div>
            <div class="mb-4 d-none" id="api-select-group">
              <label for="api_id" class="form-label fw-medium">选择接口</label>
              <select id="api_id" name="api_id" class="form-select">
                <option value="">请选择一个接口</option>
                <?php foreach($apis as $api): ?>
                <option value="<?php echo $api['id']; ?>"><?php echo htmlspecialchars($api['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-4">
              <label for="content" class="form-label fw-medium">反馈内容 <span class="text-danger">*</span></label>
              <textarea id="content" name="content" class="form-control" rows="5" placeholder="请详细描述您遇到的问题、建议或优化方向..." required></textarea>
              <div class="invalid-feedback">请填写反馈内容</div>
            </div>
            <div class="mb-4">
              <label for="contact" class="form-label fw-medium">联系邮箱 <span class="text-danger">*</span></label>
              <input type="email" id="contact" name="contact" class="form-control" placeholder="您的登录邮箱" value="<?php echo htmlspecialchars($user_info['email']); ?>" readonly required>
              <div class="invalid-feedback">联系邮箱不能为空</div>
            </div>
            <div class="mb-4">
              <label for="captcha" class="form-label fw-medium">图形验证码 <span class="text-danger">*</span></label>
              <div class="captcha-box">
                <input type="text" class="form-control flex-grow-1" id="captcha" name="captcha" placeholder="请输入图形验证码" maxlength="4" required>
                <img src="/common/ajax/captcha.php" class="captcha-img" id="captcha-img" alt="图形验证码">
              </div>
              <div class="invalid-feedback">请输入图形验证码</div>
            </div>
            <div class="mb-4">
              <label for="email_code" class="form-label fw-medium">邮箱验证码 <span class="text-danger">*</span></label>
              <div class="d-flex gap-2 align-items-center">
                <input type="text" class="form-control flex-grow-1" id="email_code" name="email_code" placeholder="请输入6位邮箱验证码" maxlength="6" required>
                <button type="button" class="btn btn-primary get-code-btn" id="get-code-btn">获取验证码</button>
              </div>
              <div class="invalid-feedback">请输入邮箱验证码</div>
            </div>
            <div class="form-actions d-flex gap-3">
              <button type="submit" class="btn btn-primary flex-grow-1 py-2">提交反馈</button>
              <button type="button" class="btn btn-outline-secondary py-2" onclick="history.back();">返回</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/main.min.js"></script>
<script>
$(document).ready(function() {
    $('#feedback_type').change(function() {
        $('#api-select-group').toggleClass('d-none', this.value !== 'api');
    });
    const captchaImg = document.getElementById('captcha-img');
    if (captchaImg) {
        captchaImg.addEventListener('click', function() {
            this.src = '/common/ajax/captcha.php?t=' + new Date().getTime();
        });
    }
    (function() {
        'use strict';
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
    const getCodeBtn = document.getElementById('get-code-btn');
    const captchaInput = document.getElementById('captcha');
    const emailInput = document.getElementById('contact');
    const emailCodeInput = document.getElementById('email_code');
    const feedbackType = document.getElementById('feedback_type');
    const contentInput = document.getElementById('content');
    if (getCodeBtn) {
        getCodeBtn.addEventListener('click', function() {
            const captcha = captchaInput.value.trim().toLowerCase();
            const email = emailInput.value.trim();
            if (!captcha) {
                captchaInput.classList.add('is-invalid');
                return;
            }
            this.classList.add('btn-disabled');
            this.disabled = true;
            $.ajax({
                url: '/common/ajax/send_code.php',
                type: 'POST',
                data: {email: email,type: 'feedback',captcha: captcha},
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        let count = 60;
                        getCodeBtn.innerText = count + '秒后重新获取';
                        const timer = setInterval(() => {
                            count--;
                            getCodeBtn.innerText = count + '秒后重新获取';
                            if (count <= 0) {
                                clearInterval(timer);
                                getCodeBtn.classList.remove('btn-disabled');
                                getCodeBtn.disabled = false;
                                getCodeBtn.innerText = '获取验证码';
                                captchaImg.src = '/common/ajax/captcha.php?t=' + new Date().getTime();
                                captchaInput.value = '';
                            }
                        }, 1000);
                        alert(res.message);
                    } else {
                        getCodeBtn.classList.remove('btn-disabled');
                        getCodeBtn.disabled = false;
                        alert(res.message);
                        captchaImg.src = '/common/ajax/captcha.php?t=' + new Date().getTime();
                        captchaInput.value = '';
                    }
                },
                error: function() {
                    getCodeBtn.classList.remove('btn-disabled');
                    getCodeBtn.disabled = false;
                    alert('网络错误，请检查网络后重试');
                    captchaImg.src = '/common/ajax/captcha.php?t=' + new Date().getTime();
                    captchaInput.value = '';
                }
            });
        });
    }
    captchaInput.addEventListener('input', function() {this.classList.remove('is-invalid');});
    captchaInput.addEventListener('blur', function() {this.value = this.value.trim().toLowerCase();});
    emailCodeInput.addEventListener('input', function() {this.classList.remove('is-invalid');});
    feedbackType.addEventListener('change', function() {this.classList.remove('is-invalid');});
    contentInput.addEventListener('input', function() {this.classList.remove('is-invalid');});
    $('#feedback-form').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        if (!form[0].checkValidity()) {
            form.addClass('was-validated');
            return;
        }
        var submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-2"></i>提交中...');
        $.ajax({
            url: '../../../common/ajax/submit_feedback.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(data) {
                var alertClass = data.success ? 'alert-success' : 'alert-danger';
                $('#feedback-result').html('<div class="alert ' + alertClass + ' mb-4">' + data.message + '</div>');
                if (data.success) {
                    form[0].reset();
                    $('#api-select-group').addClass('d-none');
                    captchaImg.src = '/common/ajax/captcha.php?t=' + new Date().getTime();
                    getCodeBtn.classList.remove('btn-disabled');
                    getCodeBtn.disabled = false;
                    getCodeBtn.innerText = '获取验证码';
                }
            },
            error: function() {
                $('#feedback-result').html('<div class="alert alert-danger mb-4">提交失败，请检查网络后重试。</div>');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('提交反馈');
            }
        });
    });
});
</script>
</body>
</html>