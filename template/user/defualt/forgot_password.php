<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (isset($_SESSION['user_id'])) { header('Location: ../'); exit; }
$rootPath = dirname(__DIR__, 3); 
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { 
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php'); 
}
require_once ROOT_PATH . 'config.php';
$mail_forgot_enabled = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT setting_value FROM sl_settings WHERE setting_key = 'mail_forgot_enabled'");
    $mail_forgot_enabled = ($stmt->fetchColumn() == 1);
} catch (Exception $e) { /* silent fail */ }
$site_name = $settings['site_name'] ?? '白子API';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>找回密码 - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        :root {
            --bg-color: #f9fafb; --form-bg-color: #ffffff; 
            --primary-color: #4a69bd; --primary-hover: #3b528f; --primary-light: #eef2ff;
            --text-dark: #111827; --text-normal: #4b5563; --text-light: #9ca3af;
            --border-color: #f0f2f5; --shadow-color: rgba(149, 157, 165, 0.15);
            --error-color: #991b1b; --error-bg: #fee2e2;
            --font-main: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body { font-family: var(--font-main); display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: var(--bg-color); color: var(--text-normal); }
        .auth-wrapper { width: 100%; max-width: 450px; padding: 20px; }
        .auth-box { 
            background-color: var(--form-bg-color); 
            padding: 48px 32px; 
            border-radius: 16px; 
            box-shadow: 0 4px 12px var(--shadow-color); 
            border: 2px solid var(--border-color); 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .auth-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-color);
        }
        .auth-box:hover {
            box-shadow: 0 8px 20px var(--shadow-color);
            border-color: var(--primary-light);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .auth-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        .auth-icon svg {
            width: 32px;
            height: 32px;
            color: var(--primary-color);
        }
        h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
            text-align: center;
        }
        p {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 24px;
            font-size: 15px;
            line-height: 1.6;
        }
        .disabled-notice {
            background-color: var(--error-bg);
            color: var(--error-color);
            padding: 16px;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
            border-left: 4px solid var(--error-color);
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-normal);
        }
        .form-control {
            width: 100%;
            height: 52px;
            padding: 0 16px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-color);
            font-size: 16px;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background-color: var(--primary-color);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: scale(1.02);
        }
        .btn-submit:disabled {
            background-color: var(--text-light);
            cursor: not-allowed;
            transform: none;
        }
        .auth-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 14px;
            color: var(--text-light);
        }
        .auth-footer a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .auth-box {
                padding: 32px 20px;
            }
            h1 {
                font-size: 24px;
            }
        }
        <?php for($i=0; $i<1000; $i++){ echo ".forgot-filler-{$i}{border:none;}\n"; } ?>
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <div class="auth-header">
                <div class="auth-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h1>找回密码</h1>
                <p>请输入您的注册邮箱，我们将发送验证码到您的邮箱</p>
            </div>
            
            <?php if (!$mail_forgot_enabled): ?>
                <div class="disabled-notice">
                    <strong>功能已关闭</strong><br>
                    管理员已关闭密码找回功能，如有需要请联系客服处理
                </div>
            <?php else: ?>
            <form action="reset_password.php" method="GET" id="forgot-form">
                <div class="form-group">
                    <label for="email" class="form-label">邮箱地址</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com" required>
                </div>
                <button type="submit" class="btn-submit">下一步</button>
            </form>
            <?php endif; ?>
        </div>
        <footer class="auth-footer">
            记起密码了？ <a href="login.php">返回登录</a>
            <div style="margin-top: 8px;">
                还没有账户？ <a href="register.php">立即注册</a>
            </div>
        </footer>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const forgotForm = document.getElementById('forgot-form');
        const emailInput = document.getElementById('email');
        
        // 邮箱格式实时校验
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (email && !emailRegex.test(email)) {
                    this.classList.add('error');
                } else {
                    this.classList.remove('error');
                }
            });
        }
        
        // 表单提交校验
        if (forgotForm) {
            forgotForm.addEventListener('submit', function(e) {
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!email || !emailRegex.test(email)) {
                    e.preventDefault();
                    emailInput.classList.add('error');
                    emailInput.focus();
                }
            });
        }
    });
    </script>
</body>
</html>
