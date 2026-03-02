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
require_once ROOT_PATH . 'common/TemplateManager.php';
$template = TemplateManager::getActiveUserTemplate();
$template_base_url = "/template/user/{$template}/";
$is_logged_in = isset($_SESSION['user_id']);
if ($is_logged_in) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $stmt = $pdo->prepare("SELECT api_key FROM sl_users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_api_key'] = $user['api_key'] ?? '';
    } catch (PDOException $e) {
    }
}
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
$hasApiKeyParam = false;
foreach($params as $p) {
    if(strtolower($p['name']) === 'apikey' || strtolower($p['name']) === 'api_key') {
        $hasApiKeyParam = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title><?php echo htmlspecialchars($api['name']); ?> - API详情 - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
    <style>
/* Base Styles */
body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  line-height: 1.6;
}

/* API Detail Container */
.api-detail-container {
  padding: 20px;
  border-radius: 8px;
}

/* Card Styles */
.api-card {
  border-radius: 8px;
  margin-bottom: 20px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.api-card .card-header {
  border-bottom: 1px solid;
  font-weight: 600;
  padding: 15px 20px;
  display: flex;
  align-items: center;
}

.api-card .card-header i {
  margin-right: 8px;
}

.api-card .card-body {
  padding: 20px;
}

/* Info Grid Layout */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.full-width-item {
    grid-column: 1 / -1;
    text-align: center;
}

/* URL Box */
.url-box {
  padding: 12px 15px;
  border-radius: 6px;
  font-family: "SFMono-Regular", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  margin-bottom: 15px;
  word-break: break-all;
  border: 1px solid;
}

/* Table Styles */
.param-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.param-table th {
  font-weight: 600;
  padding: 12px 15px;
  border: 1px solid;
  text-align: left;
}

.param-table td {
  padding: 12px 15px;
  border: 1px solid;
  vertical-align: top;
}

/* Response Area */
.response-area {
  height: 300px;
  padding: 15px;
  border-radius: 6px;
  overflow-y: auto;
  font-family: "SFMono-Regular", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  border: 1px solid;
  background-color: var(--card-bg);
}
/* 新增媒体内容样式 - 适配原页面 */
.response-area img, .response-area audio, .response-area video {
  max-width: 100%;
  max-height: 220px;
  display: block;
  margin: 8px 0;
  border-radius: 4px;
}
.response-area pre {
  margin: 0;
  white-space: pre-wrap;
  word-wrap: break-word;
}

/* Code Tabs */
.code-tabs {
  display: flex;
  border-bottom: 1px solid;
  margin-bottom: 15px;
}

.tab-btn {
  padding: 10px 20px;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 600;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.3s ease;
}

.tab-btn.active {
  border-bottom-color: currentColor;
}

.code-panel {
  display: none;
}

.code-panel.active {
  display: block;
}

.code-panel pre {
  padding: 15px;
  border-radius: 0 6px 6px 6px;
  overflow-x: auto;
  margin: 0;
  border: 1px solid;
  font-family: "SFMono-Regular", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  tab-size: 4;
}

/* Status Badges */
.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 6px;
}

.status-green {
  background-color: rgba(34, 197, 94, 0.15);
  color: var(--success);
}

.status-red {
  background-color: rgba(239, 68, 68, 0.15);
  color: var(--danger);
}

.status-yellow {
  background-color: rgba(234, 179, 8, 0.15);
  color: var(--warning);
}

.status-gray {
  background-color: rgba(156, 163, 175, 0.15);
  color: var(--secondary);
}

/* Page Header */
.page-header {
  margin-bottom: 30px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 10px;
}

.page-description {
  font-size: 16px;
  margin-bottom: 20px;
}

/* Test Button */
.btn-test {
  border-radius: 6px;
  padding: 10px 15px;
  font-weight: 600;
  transition: all 0.3s ease;
}

/* Test Row */
.test-row {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}

.test-row label {
  margin-bottom: 0;
  min-width: 100px;
  font-weight: 500;
}

.test-row .form-control {
  flex: 1;
}

/* Floating API Switcher */
.floating-api-switcher {
  position: fixed;
  right: 30px;
  bottom: 30px;
  z-index: 999;
}

.floating-btn {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background-color: #2d3748;
  color: #e2e8f0;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.floating-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
  background-color: #4a5568;
}

.floating-btn i {
  font-size: 24px;
}

.api-list-container {
  position: absolute;
  right: 0;
  bottom: 70px;
  width: 300px;
  max-height: 500px;
  background-color: #1a202c;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  overflow: hidden;
  display: none;
  border: 1px solid #2d3748;
}

.api-list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #2d3748;
  background-color: #2d3748;
  color: #e2e8f0;
}

.api-list-header h5 {
  margin: 0;
  font-size: 16px;
}

.close-list-btn {
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: #e2e8f0;
}

.close-list-btn:hover {
  color: #ffffff;
}

.api-list {
  padding: 8px 0;
  overflow-y: auto;
  max-height: 440px;
}

.api-item {
  padding: 10px 16px;
  cursor: pointer;
  transition: background-color 0.2s;
  color: #e2e8f0;
  border-bottom: 1px solid #2d3748;
}

.api-item:hover {
  background-color: #2d3748;
}

.api-item.active {
  background-color: #4a5568;
  color: white;
}

.api-item .api-name {
  font-weight: 500;
  margin-bottom: 2px;
}

.api-item .api-endpoint {
  font-size: 12px;
  opacity: 0.8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #a0aec0;
}

/* Scrollbars */
.response-area::-webkit-scrollbar,
.api-list::-webkit-scrollbar {
  width: 8px;
}

.response-area::-webkit-scrollbar-thumb,
.api-list::-webkit-scrollbar-thumb {
  border-radius: 4px;
}

.response-area::-webkit-scrollbar-thumb {
  background-color: var(--scrollbar-thumb);
}

.api-list::-webkit-scrollbar-thumb {
  background-color: #4a5568;
}

.response-area::-webkit-scrollbar-track {
  background: transparent;
}

.api-list::-webkit-scrollbar-track {
  background-color: #2d3748;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
  .page-title {
    font-size: 24px;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .info-item {
    padding: 8px 12px;
    font-size: 13px;
  }
  
  .api-card .card-body {
    padding: 15px;
  }
  
  .url-box, 
  .param-table th, 
  .param-table td {
    padding: 10px 12px;
  }
  
  .floating-api-switcher {
    right: 15px;
    bottom: 15px;
  }
  
  .api-list-container {
    width: 280px;
    max-height: 400px;
  }
  .response-area img, .response-area audio, .response-area video {
    max-height: 180px;
  }
}
</style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="api-detail-container">
            <div class="page-header">
                <h1 class="page-title"><?php echo htmlspecialchars($api['name']); ?></h1>
                <p class="page-description"><?php echo htmlspecialchars($api['description']); ?></p>
<div class="row">
    <div class="col-lg-12">
        <div class="api-card card">
            <div class="card-header py-1">
                <i class="mdi mdi-link-variant mr-1"></i>接口信息
            </div>
            <div class="card-body p-1">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.25rem; margin-bottom: 0.25rem; font-size: 0.85rem; line-height: 1.2;">
                    <div style="display: flex; align-items: center; gap: 0.2rem; padding: 0.1rem;">
                        <i class="mdi mdi-check-circle-outline" style="font-size: 0.9rem;"></i>
                        接口状态: <?php echo getStatusBadge($api['status']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.2rem; padding: 0.1rem;">
                        <i class="mdi mdi-counter" style="font-size: 0.9rem;"></i>
                        总调用: <strong><?php echo number_format($api['total_calls']); ?></strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.2rem; padding: 0.1rem;">
                        <i class="mdi mdi-calendar" style="font-size: 0.9rem;"></i>
                        添加时间: <strong><?php echo date('Y-m-d', strtotime($api['created_at'])); ?></strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.2rem; padding: 0.1rem;">
                        <i class="mdi mdi-update" style="font-size: 0.9rem;"></i>
                        更新时间: <strong><?php echo date('Y-m-d', strtotime($api['updated_at'])); ?></strong>
                    </div>
                </div>
                <div style="text-align: center; font-size: 0.85rem; padding: 0.1rem; border-top: 1px solid #eee; margin-top: 0.2rem; line-height: 1.2;">
                    <i class="mdi mdi-shield-account" style="font-size: 0.9rem;"></i>
                    访问权限: 
                    <?php if ($api['visibility'] === 'public' && !$api['is_billable']): ?>
                        <strong>公开访问（无需密钥）</strong>
                    <?php elseif ($api['visibility'] === 'public' && $api['is_billable']): ?>
                        <strong>公开访问（需API密钥）</strong>
                    <?php else: ?>
                        <strong>需API密钥访问</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="api-card card">
                        <div class="card-header">
                            <i class="mdi mdi-link-variant mr-2"></i>请求信息
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>请求地址：</strong>
                                <div class="url-box"><?php echo htmlspecialchars($request_url); ?></div>
                            </div>
                            <div>
                                <strong>示例地址：</strong>
                                <div class="url-box"><?php echo htmlspecialchars($example_url); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="api-card card">
                        <div class="card-header">
                            <i class="mdi mdi-format-list-checks mr-2"></i>请求参数
                        </div>
                        <div class="card-body">
                            <table class="param-table">
                                <thead>
                                    <tr>
                                        <th>参数名</th>
                                        <th>类型</th>
                                        <th>必填</th>
                                        <th>说明</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($params)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center;">此接口无需请求参数</td>
                                    </tr>
                                    <?php else: foreach($params as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td><?php echo htmlspecialchars($p['type']); ?></td>
                                        <td><?php echo $p['required'] === 'yes' ? '是' : '否'; ?></td>
                                        <td><?php echo htmlspecialchars($p['desc']); ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="api-card card">
                        <div class="card-header">
                            <i class="mdi mdi-code-tags mr-2"></i>状态码说明
                        </div>
                        <div class="card-body">
                            <table class="param-table">
                                <thead>
                                    <tr>
                                        <th>状态码</th>
                                        <th>说明</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>200</td>
                                        <td>请求成功，服务器已成功处理了请求。</td>
                                    </tr>
                                    <tr>
                                        <td>403</td>
                                        <td>服务器拒绝请求。这可能是由于缺少必要的认证凭据（如API密钥）或权限不足。</td>
                                    </tr>
                                    <tr>
                                        <td>404</td>
                                        <td>请求的资源未找到。请检查您的请求地址是否正确。</td>
                                    </tr>
                                    <tr>
                                        <td>429</td>
                                        <td>请求过于频繁。您已超出速率限制，请稍后再试。</td>
                                    </tr>
                                    <tr>
                                        <td>500</td>
                                        <td>服务器内部错误。服务器在执行请求时遇到了问题。</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
<div class="row">
    <div class="col-lg-12">
        <div class="api-card card">
            <div class="card-header">
                <i class="mdi mdi-test-tube mr-2"></i>在线测试
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <form id="api-tester-form" data-url="<?php echo htmlspecialchars($request_url); ?>" data-method="<?php echo htmlspecialchars($api['method']); ?>">
                            <?php foreach($params as $p): ?>
                                <?php
                                $isApiKeyParam = (strtolower($p['name']) === 'apikey' || strtolower($p['name']) === 'api_key');
                                if ($isApiKeyParam && $api['visibility'] === 'public' && !$api['is_billable']) {
                                    continue;
                                }
                                ?>
                                
                                <div class="test-row">
                                    <label for="param-<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></label>
                                    <input type="text" id="param-<?php echo htmlspecialchars($p['name']); ?>" name="<?php echo htmlspecialchars($p['name']); ?>" class="form-control"
                                        <?php if ($isApiKeyParam && $is_logged_in && isset($_SESSION['user_api_key'])): ?>
                                            value="<?php echo htmlspecialchars($_SESSION['user_api_key']); ?>" placeholder="自动填充您的API密钥"
                                        <?php else: ?>
                                            placeholder="<?php echo htmlspecialchars($p['desc']); ?>"
                                        <?php endif; ?>
                                    >
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-primary btn-test">
                                    <i class="mdi mdi-send mr-1"></i>立即测试
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="response-area">
                            <div id="response-output">此处将显示接口返回结果...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="api-card card">
                        <div class="card-header">
                            <i class="mdi mdi-code-braces mr-2"></i>调用示例
                        </div>
                        <div class="card-body">
                            <div class="code-tabs" id="code-tabs">
                                <button class="tab-btn active" data-target="php">PHP</button>
                                <button class="tab-btn" data-target="python">Python</button>
                                <button class="tab-btn" data-target="js">JavaScript</button>
                            </div>
                            <div id="code-panels">
                                <div class="code-panel active" id="panel-php">
                                    <pre><code>&lt;?php
$url = '<?php echo $request_url; ?>';
$params = [<?php foreach($params as $p) echo "'".htmlspecialchars($p['name'])."' => 'YOUR_VALUE', "; ?>];
$url .= '?' . http_build_query($params);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?&gt;</code></pre>
                                </div>
                                <div class="code-panel" id="panel-python">
                                    <pre><code>import requests

url = "<?php echo $request_url; ?>"
params = {
<?php foreach($params as $p) echo "    '".htmlspecialchars($p['name'])."': 'YOUR_VALUE',\n"; ?>}
response = requests.get(url, params=params)
print(response.text)</code></pre>
                                </div>
                                <div class="code-panel" id="panel-js">
                                    <pre><code>const url = new URL('<?php echo $request_url; ?>');
const params = {
<?php foreach($params as $p) echo "    '".htmlspecialchars($p['name'])."': 'YOUR_VALUE',\n"; ?>};
Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

fetch(url)
    .then(response => response.text())
    .then(data => console.log(data))
    .catch(error => console.error('Error:', error));</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<div class="floating-api-switcher">
    <button class="floating-btn" id="api-switcher-btn">
        <i class="mdi mdi-api"></i>
    </button>
    <div class="api-list-container" id="api-list-container">
        <div class="api-list-header">
            <h5>API列表</h5>
            <button class="close-list-btn">&times;</button>
        </div>
        <div class="api-list" id="api-list">
        </div>
    </div>
</div>
    <script src="../../../assets/js/jquery.min.js"></script>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#code-tabs').on('click', '.tab-btn', function() {
            const targetId = $(this).data('target');
            $('#code-tabs .tab-btn').removeClass('active');
            $(this).addClass('active');
            $('#code-panels .code-panel').removeClass('active');
            $('#panel-' + targetId).addClass('active');
        });

        // 在线测试-兼容媒体类型核心逻辑（保留原授权头/参数逻辑）
        $('#api-tester-form').on('submit', async function(e) {
            e.preventDefault();
            const responseOutput = $('#response-output');
            responseOutput.html('正在请求...');
            
            const apiUrl = $(this).data('url');
            const apiMethod = $(this).data('method').toUpperCase();
            const formData = $(this).serialize();
            
            let requestUrl = apiUrl;
            let fetchOptions = { 
                method: apiMethod,
                headers: {}
            };
            
            <?php if($is_logged_in): ?>
            fetchOptions.headers['Authorization'] = 'Bearer <?php echo $_SESSION['user_api_key'] ?? ''; ?>';
            <?php endif; ?>
            
            if (apiMethod === 'GET') {
                requestUrl += '?' + formData;
            } else {
                fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                fetchOptions.body = formData;
            }
            
            try {
                const response = await fetch(requestUrl, fetchOptions);
                const status = response.status;
                const contentType = response.headers.get('Content-Type') || '';
                // 匹配媒体类型
                const isImage = /^image\//.test(contentType);
                const isAudio = /^audio\//.test(contentType);
                const isVideo = /^video\//.test(contentType);
                const isMedia = isImage || isAudio || isVideo;

                // 基础状态码展示
                let resultHtml = `<div style="color:#22c55e; margin-bottom:8px;">HTTP Status: ${status}</div><hr style="border:0.5px solid #ccc; margin:8px 0;">`;

                if (isMedia) {
                    // 媒体类型转Blob预览
                    const blob = await response.blob();
                    const blobUrl = URL.createObjectURL(blob);
                    if (isImage) {
                        resultHtml += `<img src="${blobUrl}" alt="图片返回结果" onclick="window.open('${blobUrl}', '_blank')" title="点击查看原图">`;
                    } else if (isAudio) {
                        resultHtml += `<audio controls src="${blobUrl}">您的浏览器不支持音频播放`;
                    } else if (isVideo) {
                        resultHtml += `<video controls src="${blobUrl}" preload="metadata">您的浏览器不支持视频播放</video>`;
                    }
                    // 释放Blob URL防止内存泄漏
                    window.addEventListener('unload', () => URL.revokeObjectURL(blobUrl));
                } else {
                    // 非媒体类型-文本/JSON展示（格式化JSON）
                    const responseText = await response.text();
                    let showText = responseText;
                    try {
                        showText = JSON.stringify(JSON.parse(responseText), null, 2);
                    } catch (e) {}
                    resultHtml += `<pre>${showText}</pre>`;
                }
                responseOutput.html(resultHtml);
            } catch (error) {
                responseOutput.html(`<div style="color:#ef4444;">请求失败: ${error.message}</div>`);
            }
        });

        // 浮动API列表逻辑（原逻辑不变）
        async function loadApiList() {
            try {
                const response = await fetch('get_api_list.php');
                const apis = await response.json();
                
                const apiList = $('#api-list');
                apiList.empty();
                
                apis.forEach(api => {
                    const isActive = api.id === <?php echo $api_id; ?>;
                    apiList.append(`
                        <div class="api-item ${isActive ? 'active' : ''}" data-id="${api.id}">
                            <div class="api-name">${api.name}</div>
                            <div class="api-endpoint">${api.endpoint}</div>
                        </div>
                    `);
                });
                
                $('.api-item').on('click', function() {
                    const apiId = $(this).data('id');
                    if (apiId !== <?php echo $api_id; ?>) {
                        window.location.href = `?id=${apiId}`;
                    }
                });
            } catch (error) {
                console.error('加载API列表失败:', error);
            }
        }

        $('#api-switcher-btn').on('click', function() {
            $('#api-list-container').toggle();
            if ($('#api-list-container').is(':visible')) {
                loadApiList();
            }
        });

        $('.close-list-btn').on('click', function() {
            $('#api-list-container').hide();
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.floating-api-switcher').length) {
                $('#api-list-container').hide();
            }
        });
    });
    </script>
</body>
</html>
