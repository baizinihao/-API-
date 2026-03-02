<?php
@session_start(); // 启动会话
@error_reporting(0);
@ini_set('display_errors', 'Off');

// 销毁所有会话变量
$_SESSION = [];

// 如果使用基于cookie的会话，删除会话cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 彻底销毁会话
session_destroy();

// 加载配置文件获取站点名称（保持风格统一）
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
$site_name = '白子API';
if (file_exists(ROOT_PATH . 'config.php')) {
    require_once ROOT_PATH . 'config.php';
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $stmt = $pdo->query("SELECT setting_value FROM sl_settings WHERE setting_key = 'site_name'");
        $site_name = $stmt->fetchColumn() ?: $site_name;
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>退出登录 - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        :root {
            --bg-color: #f9fafb; --card-bg-color: #ffffff;
            --primary-color: #4a69bd; --primary-light: #eef2ff;
            --text-dark: #111827; --text-normal: #4b5563; --text-light: #9ca3af;
            --border-color: #f0f2f5; --shadow-color: rgba(149, 157, 165, 0.15);
            --font-main: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body { 
            font-family: var(--font-main); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            background-color: var(--bg-color); 
            color: var(--text-normal); 
        }
        .logout-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        .logout-card {
            background-color: var(--card-bg-color);
            padding: 48px 32px;
            border-radius: 16px;
            box-shadow: 0 4px 12px var(--shadow-color);
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .logout-card:hover {
            box-shadow: 0 8px 20px var(--shadow-color);
            border-color: var(--primary-light);
        }
        .logout-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .logout-icon svg {
            width: 40px;
            height: 40px;
            color: var(--primary-color);
        }
        h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 12px;
        }
        .logout-desc {
            font-size: 15px;
            color: var(--text-light);
            margin-bottom: 32px;
            line-height: 1.6;
        }
        .btn-login {
            display: inline-block;
            padding: 12px 32px;
            border-radius: 10px;
            background-color: var(--primary-color);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background-color: var(--primary-color);
            transform: scale(1.05);
        }
        .countdown {
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-light);
        }
        @media (max-width: 768px) {
            .logout-card {
                padding: 32px 20px;
            }
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-card">
            <div class="logout-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 6a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 6a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <h1>退出成功</h1>
            <p class="logout-desc">您已安全退出账户，感谢使用<?php echo htmlspecialchars($site_name); ?>服务</p>
            <a href="login.php" class="btn-login">返回登录</a>
            <div class="countdown">
                将于 <span id="countdown-num">3</span> 秒后自动跳转至登录页...
            </div>
        </div>
    </div>
    <script>
    // 倒计时自动跳转
    document.addEventListener('DOMContentLoaded', function() {
        let count = 3;
        const countElement = document.getElementById('countdown-num');
        const countdownInterval = setInterval(function() {
            count--;
            countElement.textContent = count;
            if (count <= 0) {
                clearInterval(countdownInterval);
                window.location.href = 'login.php';
            }
        }, 1000);
    });
    </script>
</body>
</html>
