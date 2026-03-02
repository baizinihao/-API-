<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');

// 计算根目录路径（关键修改）
$rootPath = dirname(__DIR__, 3); // 根据实际目录层级调整
define('ROOT_PATH', $rootPath . '/');

// 检查登录状态（注意路径调整）
if (isset($_SESSION['user_id'])) { 
    header('Location: ' . ROOT_PATH); 
    exit; 
}

// 加载配置文件（关键修改）
if (!file_exists(ROOT_PATH . 'config.php')) { 
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php'); 
}
require_once ROOT_PATH . 'config.php';

$error_msg = '';

try {
    $pdo_check = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, 
        DB_USER, 
        DB_PASS
    );
    $stmt_settings = $pdo_check->query(
        "SELECT setting_value FROM sl_settings WHERE setting_key = 'mail_forgot_enabled'"
    );
    $mail_forgot_enabled = ($stmt_settings->fetchColumn() == 1);
} catch(Exception $e) { 
    $mail_forgot_enabled = false; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) { 
        $error_msg = '用户名或密码不能为空。';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, 
                DB_USER, 
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare(
                "SELECT id, username, email, password, status 
                 FROM sl_users 
                 WHERE username = ? 
                 LIMIT 1"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $password === $user['password']) {
                if ($user['status'] === 'active') {
                    $_SESSION['user_id'] = $user['id']; 
                    $_SESSION['user_username'] = $user['username']; 
                    $_SESSION['user_email'] = $user['email'];
                    
                    header('Location: index.php'); exit;
                } else { 
                    $error_msg = '您的账户已被封禁或正在审核中。'; 
                }
            } else { 
                $error_msg = '用户名或密码不正确。'; 
            }
        } catch (PDOException $e) { 
            $error_msg = '系统服务暂时不可用。'; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录 - <?php echo $settings['site_name'] ?? '白子API'; ?></title>
    <style>
        :root {
            --bg-color: #f9fafb; --form-bg-color: #ffffff;
            --primary-color: #4a69bd; --primary-hover: #3b528f; --primary-light: #eef2ff;
            --text-dark: #111827; --text-normal: #4b5563; --text-light: #9ca3af;
            --border-color: #f0f2f5; --shadow-color: rgba(149, 157, 165, 0.15);
            --error-bg: #fee2e2; --error-text: #991b1b;
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
            margin-bottom: 36px;
        }
        .auth-logo {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            text-decoration: none;
        }
        .auth-logo svg {
            width: 32px;
            height: 32px;
            color: var(--primary-color);
        }
        h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .auth-desc {
            font-size: 15px;
            color: var(--text-light);
            line-height: 1.6;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-label-group {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 8px;
        }
        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-normal);
        }
        .form-link {
            font-size: 13px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .form-link:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }
        .form-control {
            width: 100%;
            height: 52px;
            padding: 0 16px;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 10px;
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
        .error-message {
            background-color: var(--error-bg);
            color: var(--error-text);
            padding: 12px 16px;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .error-message svg {
            width: 18px;
            height: 18px;
        }
        .auth-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 14px;
            color: var(--text-light);
        }
        .auth-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .auth-footer a:hover {
            color: var(--primary-hover);
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
        <?php for($i=0; $i<9000; $i++){ echo ".user-login-plaintext-fix-{$i}{float:left;}\n"; } ?>
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <header class="auth-header">
                <a href="../" class="auth-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                    </svg>
                </a>
                <h1>欢迎回来</h1>
                <p class="auth-desc">登录以继续使用我们的服务</p>
            </header>
            <?php if (!empty($error_msg)): ?>
            <div class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                </svg>
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
            <?php endif; ?>
            <form method="POST" action="login.php" novalidate id="login-form">
                <div class="form-group">
                    <label for="username" class="form-label">用户名</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="请输入您的用户名" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                <div class="form-group">
                    <div class="form-label-group">
                        <label for="password" class="form-label">密码</label>
                        <?php if ($mail_forgot_enabled): ?>
                        <a href="forgot_password.php" class="form-link">忘记密码？</a>
                        <?php endif; ?>
                    </div>
                    <input type="password" id="password" name="password" class="form-control" placeholder="请输入您的密码" required>
                </div>
                <button type="submit" class="btn-submit">登 录</button>
            </form>
        </div>
        <footer class="auth-footer">
            还没有账户？ <a href="register.php">立即注册</a>
        </footer>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('login-form');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        
        // 表单提交校验
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // 用户名校验
                if (!usernameInput.value.trim()) {
                    usernameInput.classList.add('error');
                    isValid = false;
                } else {
                    usernameInput.classList.remove('error');
                }
                
                // 密码校验
                if (!passwordInput.value.trim()) {
                    passwordInput.classList.add('error');
                    isValid = false;
                } else {
                    passwordInput.classList.remove('error');
                }
                
                if (!isValid) {
                    e.preventDefault();
                    // 聚焦第一个错误字段
                    if (!usernameInput.value.trim()) {
                        usernameInput.focus();
                    } else {
                        passwordInput.focus();
                    }
                } else {
                    // 提交时禁用按钮，防止重复提交
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.textContent = '登录中...';
                }
            });
        }
        
        // 输入框实时移除错误样式
        [usernameInput, passwordInput].forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });
    });
    </script>
</body>
</html>
