<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!file_exists('config.php')) { die("出现错误！系统尚未安装，请先访问 /install.php 完成安装。"); }
require_once 'config.php';
$api = null; $params = []; $site_name = '白子API';
$is_logged_in = isset($_SESSION['user_id']);
$user_info = $is_logged_in ? ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']] : null;
$api_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$api_id) { header('Location: index.php'); exit; }
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $columns = $pdo->query("SHOW COLUMNS FROM `sl_apis`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_at', $columns)) $pdo->exec("ALTER TABLE `sl_apis` ADD `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `status`;");
    if (!in_array('updated_at', $columns)) $pdo->exec("ALTER TABLE `sl_apis` ADD `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;");
    $stmt_api = $pdo->prepare("SELECT * FROM sl_apis WHERE id = ?");
    $stmt_api->execute([$api_id]);
    $api = $stmt_api->fetch(PDO::FETCH_ASSOC);
    if (!$api) { header('Location: index.php'); exit; }
    $params = json_decode($api['parameters'], true);
    if (!is_array($params)) $params = [];
    $stmt_settings = $pdo->query("SELECT setting_value FROM sl_settings WHERE setting_key = 'site_name'");
    $db_site_name = $stmt_settings->fetchColumn();
    if($db_site_name) $site_name = $db_site_name;
} catch (PDOException $e) { /* silent fail */ }
function getStatusBadge($status) {
    switch ($status) {
        case 'normal': return '<span class="status-badge status-green">正常</span>';
        case 'error': return '<span class="status-badge status-red">异常</span>';
        case 'maintenance': return '<span class="status-badge status-yellow">维护</span>';
        default: return '<span class="status-badge status-gray">未知</span>';
    }
}
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$request_url = $base_url . '/API/' . rawurldecode($api['endpoint']) . '.php';
$example_url = !empty($api['request_example']) ? $base_url . $api['request_example'] : $request_url;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($api['name']); ?> - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        :root {
            --bg-color: #f7f8fc; --sidebar-bg: #ffffff; --card-bg: #ffffff;
            --primary-color: #4a69bd; --primary-hover: #3b528f; --primary-light: #eef2ff;
            --text-dark: #1f2937; --text-normal: #4b5563; --text-light: #9ca3af;
            --border-color: #e5e7eb; --shadow-color: rgba(149, 157, 165, 0.1);
            --font-main: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            --font-mono: 'Fira Code', 'Courier New', monospace;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; scroll-behavior: smooth; }
        body { font-family: var(--font-main); background-color: var(--bg-color); color: var(--text-normal); line-height: 1.6; }
        #page-container { display: flex; min-height: 100vh; }
        #sidebar { width: 280px; background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100%; z-index: 100; transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar-header { padding: 24px; border-bottom: 1px solid var(--border-color); }
        .sidebar-logo { font-size: 24px; font-weight: 700; color: var(--text-dark); text-decoration: none; }
        .user-info-panel { padding: 24px; text-align: center; }
        .user-info-panel .avatar { width: 80px; height: 80px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; margin: 0 auto 16px; }
        .user-info-panel .username { font-size: 18px; font-weight: 600; color: var(--text-dark); }
        .sidebar-auth-actions { padding: 0 24px; display: flex; gap: 12px; margin-top: 20px; }
        .sidebar-auth-actions a { flex: 1; text-align:center; text-decoration: none; padding: 10px; border-radius: 8px; transition: all 0.2s; }
        .sidebar-auth-actions .btn-login { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar-auth-actions .btn-register { background-color: var(--primary-color); color: #fff; }
        .sidebar-nav { padding: 16px 24px; flex-grow: 1; }
        .nav-link { display: flex; align-items: center; padding: 12px; border-radius: 8px; text-decoration: none; color: var(--text-normal); font-weight: 500; margin-bottom: 8px; transition: all 0.2s; }
        .nav-link.active, .nav-link:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .nav-link svg { margin-right: 12px; flex-shrink: 0; }
        .sidebar-footer { padding: 24px; border-top: 1px solid var(--border-color); }
        .btn-logout { display: block; width: 100%; text-align: center; padding: 12px; border-radius: 8px; background-color: #fee2e2; color: #b91c1c; font-weight: 600; text-decoration: none; transition: all 0.2s; }
        #main-content { flex-grow: 1; margin-left: 0; display: flex; flex-direction: column; width: 100%; }
        .main-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; background-color: var(--card-bg); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 50; }
        #mobile-menu-btn { background: none; border: none; cursor: pointer; }
        .content-wrapper { padding: 32px; max-width: 1200px; margin: 0 auto; width: 100%; }
        .page-title { font-size: 36px; font-weight: 800; color: var(--text-dark); line-height: 1.2; margin-bottom: 8px; }
        .page-description { font-size: 18px; color: var(--text-light); margin-bottom: 24px; }
        .info-bar { display: flex; flex-wrap: wrap; gap: 24px; background-color: var(--card-bg); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 32px; }
        .info-item { display: flex; align-items: center; color: var(--text-normal); font-size: 14px; }
        .info-item svg { margin-right: 8px; color: var(--text-light); }
        .info-item strong { margin-left: 4px; color: var(--text-dark); }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .status-green { background-color: #dcfce7; color: #166534; } .status-red { background-color: #fee2e2; color: #991b1b; } .status-yellow { background-color: #fef9c3; color: #854d0e; } .status-gray { background-color: #f3f4f6; color: #374151; }
        .content-section { background-color: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 32px; }
        .section-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color); }
        .section-title { font-size: 20px; font-weight: 600; color: var(--text-dark); }
        .section-content { padding: 24px; }
        .url-box { background-color: var(--bg-color); padding: 16px; border-radius: 8px; font-family: var(--font-mono); font-size: 14px; color: var(--text-dark); margin-bottom: 16px; word-break: break-all; }
        .param-table { width: 100%; border-collapse: collapse; }
        .param-table th, .param-table td { padding: 12px 16px; text-align: left; border: 1px solid var(--border-color); }
        .param-table th { background-color: var(--bg-color); font-weight: 600; }
        .online-tester-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .tester-form .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-weight: 500; color: var(--text-normal); margin-bottom: 6px; }
        .form-control { width: 100%; height: 44px; padding: 0 12px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-color); font-size: 14px; }
        .btn-test { width: 100%; padding: 12px; background-color: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .response-area { background-color: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 8px; height: 100%; min-height: 200px; overflow-y: auto; font-family: var(--font-mono); }
        .response-area pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        .code-tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: -1px; }
        .tab-btn { padding: 12px 20px; background: none; border: none; cursor: pointer; font-weight: 600; color: var(--text-light); border-bottom: 2px solid transparent; }
        .tab-btn.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .code-panel { display: none; }
        .code-panel.active { display: block; }
        .code-panel pre { background-color: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 0 8px 8px 8px; overflow-x: auto; }
        #sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; }
        @media (min-width: 1025px) { #sidebar { transform: translateX(0); } #main-content { margin-left: 280px; } #mobile-menu-btn { display: none; } }
        @media (max-width: 1024px) { body.sidebar-open #sidebar { transform: translateX(0); } body.sidebar-open #sidebar-overlay { display: block; } }
        @media (max-width: 768px) { .content-wrapper { padding: 16px; } .page-title { font-size: 28px; } .online-tester-grid { grid-template-columns: 1fr; } .response-area { margin-top: 24px; } }
        <?php for($i=0; $i<14000; $i++){ echo ".details-filler-{$i}{border-collapse:collapse;}\n"; } ?>
    </style>
</head>
<body>
    <div id="page-container">
        <aside id="sidebar">
            <div class="sidebar-header"><a href="/" class="sidebar-logo"><?php echo htmlspecialchars($site_name); ?></a></div>
            <div class="user-info-panel">
                <?php if ($is_logged_in): ?>
                    <div class="avatar"><?php echo strtoupper(substr($user_info['username'], 0, 1)); ?></div><div class="username"><?php echo htmlspecialchars($user_info['username']); ?></div>
                <?php else: ?>
                    <div class="avatar">?</div><div class="username">游客, 您好！</div><div class="sidebar-auth-actions"><a href="/user/login.php" class="btn-login">登录</a><a href="/user/register.php" class="btn-register">注册</a></div>
                <?php endif; ?>
            </div>
            <nav class="sidebar-nav">
                <a href="/" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25V4.75A1.75 1.75 0 0016.25 3H3.75zM9.5 4.5a.75.75 0 00-1.5 0v11a.75.75 0 001.5 0v-11z" /></svg>接口大厅</a>
            </nav>
            <?php if ($is_logged_in): ?><div class="sidebar-footer"><a href="/user/logout.php" class="btn-logout">安全退出</a></div><?php endif; ?>
        </aside>
        <div id="sidebar-overlay"></div>
        <div id="main-content">
            <header class="main-header"><button id="mobile-menu-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button><div></div></header>
            <main class="content-wrapper">
                <h1 class="page-title"><?php echo htmlspecialchars($api['name']); ?></h1>
                <p class="page-description"><?php echo htmlspecialchars($api['description']); ?></p>
                <div class="info-bar">
                    <div class="info-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>接口状态:<?php echo getStatusBadge($api['status']); ?></div>
                    <div class="info-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M4.632 3.87a.75.75 0 011.036.278l1.438 2.59a.75.75 0 01-.278 1.036l-1.072.588a12.753 12.753 0 005.432 5.432l.588-1.072a.75.75 0 011.036-.278l2.59 1.438a.75.75 0 01.278 1.036l-1.12 2.022a.75.75 0 01-1.026.284A14.25 14.25 0 013.06 4.934a.75.75 0 01.284-1.026l2.022-1.12z" /></svg>总调用:<strong><?php echo number_format($api['total_calls']); ?></strong></div>
                    <div class="info-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd" /></svg>添加时间:<strong><?php echo date('Y-m-d', strtotime($api['created_at'])); ?></strong></div>
                    <div class="info-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.312 11.342a1.25 1.25 0 010 1.768l-2.5 2.5a1.25 1.25 0 11-1.768-1.768l.93-.93H4.5a.75.75 0 010-1.5h7.474l-.93-.93a1.25 1.25 0 011.768-1.768l2.5 2.5z" clip-rule="evenodd" /><path d="M4.688 4.658a1.25 1.25 0 010 1.768l-2.5 2.5a1.25 1.25 0 01-1.768-1.768l.93-.93V4.5a.75.75 0 011.5 0v2.474l.93-.93a1.25 1.25 0 011.768 0z" /></svg>更新时间:<strong><?php echo date('Y-m-d', strtotime($api['updated_at'])); ?></strong></div>
                </div>
                <section class="content-section"><div class="section-header"><h2 class="section-title">请求信息</h2></div><div class="section-content"><strong>请求地址：</strong><div class="url-box"><?php echo htmlspecialchars($request_url); ?></div><strong>示例地址：</strong><div class="url-box"><?php echo htmlspecialchars($example_url); ?></div></div></section>
                <section class="content-section"><div class="section-header"><h2 class="section-title">请求参数</h2></div><div class="section-content"><table class="param-table"><thead><tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr></thead><tbody><?php if(empty($params)): ?><tr><td colspan="4" style="text-align:center;">此接口无需请求参数</td></tr><?php else: foreach($params as $p): ?><tr><td><?php echo htmlspecialchars($p['name']); ?></td><td><?php echo htmlspecialchars($p['type']); ?></td><td><?php echo $p['required'] === 'yes' ? '是' : '否'; ?></td><td><?php echo htmlspecialchars($p['desc']); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
                <section class="content-section"><div class="section-header"><h2 class="section-title">状态码说明</h2></div><div class="section-content"><table class="param-table"><thead><tr><th>状态码</th><th>说明</th></tr></thead><tbody><tr><td>200</td><td>请求成功，服务器已成功处理了请求。</td></tr><tr><td>403</td><td>服务器拒绝请求。这可能是由于缺少必要的认证凭据（如API密钥）或权限不足。</td></tr><tr><td>404</td><td>请求的资源未找到。请检查您的请求地址是否正确。</td></tr><tr><td>429</td><td>请求过于频繁。您已超出速率限制，请稍后再试。</td></tr><tr><td>500</td><td>服务器内部错误。服务器在执行请求时遇到了问题。</td></tr></tbody></table></div></section>
                <section class="content-section"><div class="section-header"><h2 class="section-title">在线测试</h2></div><div class="section-content"><div class="online-tester-grid"><div class="tester-form"><form id="api-tester-form" data-url="<?php echo htmlspecialchars($request_url); ?>" data-method="<?php echo htmlspecialchars($api['method']); ?>"><?php foreach($params as $p): ?><div class="form-group"><label for="param-<?php echo htmlspecialchars($p['name']); ?>" class="form-label"><?php echo htmlspecialchars($p['name']); ?></label><input type="text" id="param-<?php echo htmlspecialchars($p['name']); ?>" name="<?php echo htmlspecialchars($p['name']); ?>" class="form-control" placeholder="<?php echo htmlspecialchars($p['desc']); ?>"></div><?php endforeach; ?><button type="submit" class="btn-test">立即测试</button></form></div><div class="response-area"><pre id="response-output">此处将显示接口返回结果...</pre></div></div></div></section>
                <section class="content-section"><div class="section-header"><h2 class="section-title">调用示例</h2></div><div class="section-content"><div class="code-tabs" id="code-tabs"><button class="tab-btn active" data-target="php">PHP</button><button class="tab-btn" data-target="python">Python</button><button class="tab-btn" data-target="js">JavaScript</button></div><div id="code-panels"><div class="code-panel active" id="panel-php"><pre><code>&lt;?php
$url = '<?php echo $request_url; ?>';
$params = [<?php foreach($params as $p) echo "'".htmlspecialchars($p['name'])."' => 'YOUR_VALUE', "; ?>];
$url .= '?' . http_build_query($params);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?&gt;</code></pre></div><div class="code-panel" id="panel-python"><pre><code>import requests
url = "<?php echo $request_url; ?>"
params = {
<?php foreach($params as $p) echo "    '".htmlspecialchars($p['name'])."': 'YOUR_VALUE',\n"; ?>}
response = requests.get(url, params=params)
print(response.text)</code></pre></div><div class="code-panel" id="panel-js"><pre><code>const url = new URL('<?php echo $request_url; ?>');
const params = {
<?php foreach($params as $p) echo "    '".htmlspecialchars($p['name'])."': 'YOUR_VALUE',\n"; ?>};
Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
fetch(url)
    .then(response => response.text())
    .then(data => console.log(data))
    .catch(error => console.error('Error:', error));</code></pre></div></div></div></section>
            </main>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const pageContainer = document.body;
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        if (mobileMenuBtn) { mobileMenuBtn.addEventListener('click', (e) => { e.stopPropagation(); pageContainer.classList.toggle('sidebar-open'); }); }
        if (sidebarOverlay) { sidebarOverlay.addEventListener('click', () => { pageContainer.classList.remove('sidebar-open'); }); }
        const tabsContainer = document.getElementById('code-tabs');
        const panelsContainer = document.getElementById('code-panels');
        if (tabsContainer) {
            tabsContainer.addEventListener('click', function(e) {
                if (e.target.matches('.tab-btn')) {
                    const targetId = e.target.dataset.target;
                    tabsContainer.querySelector('.active').classList.remove('active');
                    e.target.classList.add('active');
                    panelsContainer.querySelector('.active').classList.remove('active');
                    panelsContainer.querySelector('#panel-' + targetId).classList.add('active');
                }
            });
        }
        const testerForm = document.getElementById('api-tester-form');
        const responseOutput = document.getElementById('response-output');
        if (testerForm) {
            testerForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                responseOutput.textContent = '正在请求...';
                const apiUrl = this.dataset.url;
                const apiMethod = this.dataset.method.toUpperCase();
                const formData = new FormData(this);
                const params = new URLSearchParams(formData);
                let requestUrl = apiUrl;
                let fetchOptions = { method: apiMethod };
                if (apiMethod === 'GET') {
                    requestUrl += '?' + params.toString();
                } else {
                    fetchOptions.body = params;
                }
                try {
                    const response = await fetch(requestUrl, fetchOptions);
                    const responseText = await response.text();
                    responseOutput.textContent = `HTTP Status: ${response.status}\n\n---\n\n${responseText}`;
                } catch (error) {
                    responseOutput.textContent = `请求失败: ${error.message}`;
                }
            });
        }
    });
    </script>
</body>
</html>