<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$admin_id = $_SESSION['admin_id'];
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '添加新接口';
$api = [
    'id' => null, 'name' => '', 'description' => '', 'endpoint' => '', 'type' => 'local', 'status' => 'normal',
    'visibility' => 'private', 'is_billable' => 0, 'price_per_call' => '0.0000',
    'remote_url' => '', 'method' => 'GET', 'file_path' => '', 'parameters' => '[]',
    'request_example' => '', 'response_format' => 'application/json', 'response_example' => '',
    'category_id' => null
];
$local_code = "<?php\n\nheader('Content-Type: application/json; charset=utf-8');\n\n\$response = ['code' => 200, 'message' => '新的世界新的开始', 'user_id' => \$auth_user_id ?? null];\n\necho json_encode(\$response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);";
$edit_mode = isset($_GET['id']);
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $columns = $pdo->query("SHOW COLUMNS FROM `sl_apis`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_id', $columns)) {
        $pdo->exec("ALTER TABLE `sl_apis` ADD `admin_id` INT NOT NULL AFTER `id`;");
    }
    if (!in_array('category_id', $columns)) {
        $pdo->exec("ALTER TABLE `sl_apis` ADD `category_id` INT NULL AFTER `admin_id`;");
    }
    if ($edit_mode) {
        $page_title = '编辑接口';
        $id_to_edit = intval($_GET['id']);
        $stmt_get = $pdo->prepare("SELECT * FROM sl_apis WHERE id = ?"); 
        $stmt_get->execute([$id_to_edit]);
        $api = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$api) { header('Location: api_list.php'); exit; }
        $api['endpoint'] = rawurldecode($api['endpoint']);
        if ($api['type'] === 'local' && !empty($api['file_path']) && file_exists('../' . $api['file_path'])) {
            $content = file_get_contents('../' . $api['file_path']);
            $local_code = preg_replace('/^<\?php\s*require_once __DIR__ \. \'\/..\/common\/security\/api_auth.php\';\s*/s', '', $content, 1);
            $local_code = str_replace('?>', '', $local_code);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $api_id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $name = trim($_POST['name']);
        $endpoint_raw = trim($_POST['endpoint']);
        $type = $_POST['type'];
        $status = $_POST['status'];
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        
        if (empty($name) || empty($endpoint_raw)) { 
            throw new Exception('接口名称和调用地址不能为空。'); 
        }
        $endpoint_encoded = rawurlencode($endpoint_raw);
        $api_dir = '../API';
        if (!is_dir($api_dir)) { 
            mkdir($api_dir, 0755, true); 
        }
        
        $file_path = 'API/' . $endpoint_encoded . '.php';
        $file_content = '';
        $auth_bootstrap = "<?php require_once __DIR__ . '/../common/security/api_auth.php';\n";
        if ($type === 'local') {
            $user_code = $_POST['local_code'];
            if (strpos(ltrim($user_code), '<?php') === 0) {
                $file_content = $auth_bootstrap . preg_replace('/^<\?php\s*/', '', $user_code, 1);
            } else {
                $file_content = $auth_bootstrap . $user_code;
            }
            $remote_url = null; 
            $method = 'GET';
        } else {
            $remote_url = trim($_POST['remote_url']); 
            $method = $_POST['method'];
            if (empty($remote_url)) { 
                throw new Exception('远程接口地址不能为空。'); 
            }
            $proxy_script = "\n@error_reporting(0);\n\$remote_url = '" . addslashes($remote_url) . "';\n\$method = '" . $method . "';\n\$params = array_merge(\$_GET, \$_POST);\n\$ch = curl_init();\nif (\$method === 'GET' && !empty(\$params)) { \$remote_url .= (strpos(\$remote_url, '?') === false ? '?' : '&') . http_build_query(\$params); }\ncurl_setopt(\$ch, CURLOPT_URL, \$remote_url);\ncurl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\ncurl_setopt(\$ch, CURLOPT_FOLLOWLOCATION, true);\nif (\$method === 'POST') { curl_setopt(\$ch, CURLOPT_POST, true); curl_setopt(\$ch, CURLOPT_POSTFIELDS, http_build_query(\$params)); }\n\$headers = []; foreach (getallheaders() as \$h_name => \$h_value) { if (in_array(strtolower(\$h_name), ['user-agent', 'accept', 'accept-language'])) { \$headers[] = \$h_name . ': ' . \$h_value; } } curl_setopt(\$ch, CURLOPT_HTTPHEADER, \$headers);\n\$response = curl_exec(\$ch);\n\$http_code = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);\n\$content_type = curl_getinfo(\$ch, CURLINFO_CONTENT_TYPE);\ncurl_close(\$ch);\nhttp_response_code(\$http_code);\nif(\$content_type) { header('Content-Type: ' . \$content_type); }\necho \$response;";
            $file_content = $auth_bootstrap . $proxy_script;
        }
        
        if (file_put_contents('../' . $file_path, $file_content) === false) { 
            throw new Exception('无法创建或写入接口文件，请检查API目录权限。'); 
        }
        
        $visibility_setting = $_POST['visibility'];
        $visibility = ($visibility_setting === 'public') ? 'public' : 'private';
        $is_billable = ($visibility_setting === 'billable') ? 1 : 0;
        $price_per_call = $is_billable ? $_POST['price_per_call'] : '0.0000';
        $params_json = $_POST['parameters_json'];
        
        if ($api_id) {
            $sql = "UPDATE sl_apis SET name=?, description=?, endpoint=?, type=?, status=?, visibility=?, is_billable=?, price_per_call=?, remote_url=?, method=?, file_path=?, parameters=?, request_example=?, response_format=?, response_example=?, category_id=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND admin_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name, 
                trim($_POST['description']), 
                $endpoint_encoded, 
                $type, 
                $status, 
                $visibility, 
                $is_billable, 
                $price_per_call, 
                $remote_url, 
                $method, 
                $file_path, 
                $params_json, 
                trim($_POST['request_example']), 
                $_POST['response_format'], 
                trim($_POST['response_example']), 
                $category_id,
                $api_id, 
                $admin_id
            ]);
            $_SESSION['feedback_msg'] = '接口已成功更新。';
        } else {
            $sql = "INSERT INTO sl_apis (admin_id, name, description, endpoint, type, status, visibility, is_billable, price_per_call, remote_url, method, file_path, parameters, request_example, response_format, response_example, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $admin_id, 
                $name, 
                trim($_POST['description']), 
                $endpoint_encoded, 
                $type, 
                $status, 
                $visibility, 
                $is_billable, 
                $price_per_call, 
                $remote_url, 
                $method, 
                $file_path, 
                $params_json, 
                trim($_POST['request_example']), 
                $_POST['response_format'], 
                trim($_POST['response_example']),
                $category_id
            ]);
            $_SESSION['feedback_msg'] = '接口已成功添加。';
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: api_list.php');
        exit;
    }
} catch (Exception $e) { 
    $feedback_msg = '操作失败: ' . $e->getMessage(); 
    $feedback_type = 'error'; 
}
$categories = [];
if (isset($pdo)) {
    $stmt_cats = $pdo->query("SELECT * FROM sl_api_categories ORDER BY name");
    $categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
}
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
    <style>
        .code-editor {
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            min-height: 300px;
        }
        .param-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            position: relative;
        }
        .param-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .btn-remove-param {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
            padding: 0;
        }
        .api-type-options {
            display: flex;
            gap: 20px;
            padding: 8px 0;
        }
        .api-type-options label {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-sidebar {
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background-color: #fff;
            border-right: 1px solid #e5e7eb;
            z-index: 1000;
            transition: all 0.3s;
        }
        .nav-sidebar.collapsed {
            transform: translateX(-100%);
        }
        .main-content {
            margin-left: 280px;
            transition: all 0.3s;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            z-index: 999;
            display: none;
        }
    </style>
</head>
  
<body>
<div class="container-fluid">        
            <div class="card">
                <header class="card-header">
                    <div class="card-title"><?php echo $page_title; ?></div>
                </header>
                <div class="card-body">
                    <?php if ($feedback_msg): ?>
                    <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($feedback_msg); ?></div>
                    <?php endif; ?>
                    
                    <form id="api-form" method="POST" action="api_edit.php<?php echo $edit_mode ? '?id='.$api['id'] : ''; ?>">
                        <input type="hidden" name="id" value="<?php echo $api['id']; ?>">
                        <input type="hidden" name="parameters_json" id="parameters_json">
                        
                        <div class="row">
                            <div class="mb-3">
                            <label for="category_id" class="form-label">接口分类</label>
                            <select id="category_id" name="category_id" class="form-select">
                                <option value="">-- 无分类 --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $api['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">接口名称</label>
                                    <input type="text" id="name" name="name" class="form-control" placeholder="例如：随机一言" value="<?php echo htmlspecialchars($api['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">接口描述</label>
                                    <textarea id="description" name="description" class="form-control code-editor" rows="3" placeholder="简单描述这个接口的功能和用途"><?php echo htmlspecialchars($api['description']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">接口类型</label>
                                    <div class="api-type-options">
                                        <label>
                                            <input type="radio" name="type" value="local" <?php echo $api['type'] === 'local' ? 'checked' : ''; ?>>
                                            本地接口
                                        </label>
                                        <label>
                                            <input type="radio" name="type" value="remote" <?php echo $api['type'] === 'remote' ? 'checked' : ''; ?>>
                                            套用接口
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="local-options">
                                    <div class="mb-3">
                                        <label for="local_code" class="form-label">接口代码 (PHP)</label>
                                        <textarea id="local_code" name="local_code" class="form-control code-editor" rows="10"><?php echo htmlspecialchars($local_code); ?></textarea>
                                    </div>
                                </div>
                                
                                <div id="remote-options" style="display:none;">
                                    <div class="mb-3">
                                        <label for="remote_url" class="form-label">远程接口地址</label>
                                        <input type="url" id="remote_url" name="remote_url" class="form-control" placeholder="https://example.com/api" value="<?php echo htmlspecialchars($api['remote_url']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="method" class="form-label">请求方法</label>
                                        <select id="method" name="method" class="form-select">
                                            <?php foreach(['GET', 'POST'] as $m): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $api['method'] === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">请求参数</label>
                                    <div id="param-container" class="mb-2">
                                        <?php if (!empty($api['parameters'])): ?>
                                            <?php foreach($api['parameters'] as $param): ?>
                                            <div class="param-card">
                                                <button type="button" class="btn-remove-param">&times;</button>
                                                <div class="param-grid">
                                                    <div class="mb-3">
                                                        <label class="form-label">参数名</label>
                                                        <input type="text" class="form-control param-name" value="<?php echo htmlspecialchars($param['name']); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">类型</label>
                                                        <select class="form-select param-type">
                                                            <option value="string" <?php echo $param['type'] === 'string' ? 'selected' : ''; ?>>String</option>
                                                            <option value="integer" <?php echo $param['type'] === 'integer' ? 'selected' : ''; ?>>Integer</option>
                                                            <!-- 已添加：参数类型的音频/视频/图片/动图 -->
                                                            <option value="audio" <?php echo $param['type'] === 'audio' ? 'selected' : ''; ?>>音频</option>
                                                            <option value="video" <?php echo $param['type'] === 'video' ? 'selected' : ''; ?>>视频</option>
                                                            <option value="image" <?php echo $param['type'] === 'image' ? 'selected' : ''; ?>>图片</option>
                                                            <option value="gif" <?php echo $param['type'] === 'gif' ? 'selected' : ''; ?>>动图</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">是否必填</label>
                                                        <select class="form-select param-required">
                                                            <option value="yes" <?php echo $param['required'] === 'yes' ? 'selected' : ''; ?>>必填</option>
                                                            <option value="no" <?php echo $param['required'] === 'no' ? 'selected' : ''; ?>>可选</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">说明</label>
                                                        <input type="text" class="form-control param-desc" value="<?php echo htmlspecialchars($param['desc']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" id="add-param-btn" class="btn btn-outline-primary">添加参数</button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">接口状态</label>
                                    <select id="status" name="status" class="form-select">
                                        <?php foreach(['normal'=>'正常', 'error'=>'异常', 'maintenance'=>'维护', 'deprecated'=>'失效'] as $s_val => $s_text): ?>
                                        <option value="<?php echo $s_val; ?>" <?php echo $api['status'] === $s_val ? 'selected' : ''; ?>><?php echo $s_text; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="endpoint" class="form-label">调用地址 (文件名)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">/API/</span>
                                        <input type="text" id="endpoint" name="endpoint" class="form-control" placeholder="my_api" value="<?php echo htmlspecialchars($api['endpoint']); ?>" required>
                                    </div>
                                    <small class="text-muted">最终调用地址为: /API/文件名.php</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="visibility" class="form-label">调用权限</label>
                                    <select id="visibility" name="visibility" class="form-select">
                                        <option value="public" <?php echo ($api['visibility'] === 'public') ? 'selected' : ''; ?>>公开调用 (无需密钥)</option>
                                        <option value="private" <?php echo ($api['visibility'] === 'private' && !$api['is_billable']) ? 'selected' : ''; ?>>密钥调用 (免费)</option>
                                        <option value="billable" <?php echo ($api['is_billable']) ? 'selected' : ''; ?>>计费调用</option>
                                    </select>
                                </div>
                                
                                <div id="price-options" class="mb-3" style="display:none;">
                                    <label for="price_per_call" class="form-label">每次调用价格</label>
                                    <input type="number" step="0.0001" id="price_per_call" name="price_per_call" class="form-control" value="<?php echo htmlspecialchars($api['price_per_call']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="request_example" class="form-label">请求示例</label>
                                    <input type="text" id="request_example" name="request_example" class="form-control" placeholder="/API/文件名.php?参数=值" value="<?php echo htmlspecialchars($api['request_example']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="response_format" class="form-label">返回格式</label>
                                    <select id="response_format" name="response_format" class="form-select">
                                        <?php 
                                        // 【核心改动：添加“无返回格式”选项】
                                        $formats = [
                                            '' => '无返回格式', // 新增选项
                                            'application/json' => 'JSON',
                                            'text/xml' => 'XML',
                                            'text/html' => 'HTML',
                                            'text/plain' => '纯文本',
                                            'audio/mpeg' => '音频（MP3）',
                                            'video/mp4' => '视频（MP4）',
                                            'image/jpeg' => '图片（JPG）',
                                            'image/gif' => '动图（GIF）'
                                        ]; 
                                        foreach($formats as $f_val => $f_text): ?>
                                        <option value="<?php echo $f_val; ?>" <?php echo $api['response_format'] === $f_val ? 'selected' : ''; ?>><?php echo $f_text; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="response_example" class="form-label">返回示例</label>
                                    <textarea id="response_example" name="response_example" class="form-control code-editor" rows="5" placeholder="留空则详情页实时请求"><?php echo htmlspecialchars($api['response_example']); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100"><?php echo $edit_mode ? '更新接口' : '立即添加'; ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const localOptions = document.getElementById('local-options');
    const remoteOptions = document.getElementById('remote-options');
    
    function toggleApiTypeOptions() {
        if (document.querySelector('input[name="type"]:checked').value === 'local') {
            localOptions.style.display = 'block';
            remoteOptions.style.display = 'none';
        } else {
            localOptions.style.display = 'none';
            remoteOptions.style.display = 'block';
        }
    }
    
    typeRadios.forEach(radio => radio.addEventListener('change', toggleApiTypeOptions));
    const visibilitySelect = document.getElementById('visibility');
    const priceOptions = document.getElementById('price-options');
    
    function togglePriceOptions() {
        priceOptions.style.display = visibilitySelect.value === 'billable' ? 'block' : 'none';
    }
    
    visibilitySelect.addEventListener('change', togglePriceOptions);
    toggleApiTypeOptions();
    togglePriceOptions();
    
    const paramContainer = document.getElementById('param-container');
    const addParamBtn = document.getElementById('add-param-btn');
    const apiForm = document.getElementById('api-form');
    const paramsJsonInput = document.getElementById('parameters_json');
    let currentParams = <?php echo !empty($api['parameters']) ? $api['parameters'] : '[]'; ?>;
    function createParamRow(param = { name: '', type: 'string', required: 'no', desc: '' }) {
        const card = document.createElement('div');
        card.className = 'param-card';
        card.innerHTML = `
            <button type="button" class="btn-remove-param">&times;</button>
            <div class="param-grid">
                <div class="param-row">
                    <label class="form-label">参数名</label>
                    <input type="text" class="form-control param-name" value="${escapeHTML(param.name)}">
                </div>
                <div class="param-row">
                    <label class="form-label">类型</label>
                    <select class="form-select param-type">
                        <option value="string" ${param.type === 'string' ? 'selected' : ''}>String</option>
                        <option value="integer" ${param.type === 'integer' ? 'selected' : ''}>Integer</option>
                        <!-- 已添加：参数类型的音频/视频/图片/动图 -->
                        <option value="audio" ${param.type === 'audio' ? 'selected' : ''}>音频</option>
                        <option value="video" ${param.type === 'video' ? 'selected' : ''}>视频</option>
                        <option value="image" ${param.type === 'image' ? 'selected' : ''}>图片</option>
                        <option value="gif" ${param.type === 'gif' ? 'selected' : ''}>动图</option>
                    </select>
                </div>
                <div class="param-row">
                    <label class="form-label">是否必填</label>
                    <select class="form-select param-required">
                        <option value="yes" ${param.required === 'yes' ? 'selected' : ''}>必填</option>
                        <option value="no" ${param.required === 'no' ? 'selected' : ''}>可选</option>
                    </select>
                </div>
                <div class="param-row">
                    <label class="form-label">说明</label>
                    <input type="text" class="form-control param-desc" value="${escapeHTML(param.desc)}">
                </div>
            </div>`;
        paramContainer.appendChild(card);
        card.querySelector('.btn-remove-param').addEventListener('click', () => card.remove());
    }
    addParamBtn.addEventListener('click', () => createParamRow());
    currentParams.forEach(p => createParamRow(p));
    apiForm.addEventListener('submit', function(e) {
        const params = [];
        document.querySelectorAll('.param-card').forEach(card => {
            const name = card.querySelector('.param-name').value.trim();
            if (name) {
                params.push({
                    name: name,
                    type: card.querySelector('.param-type').value,
                    required: card.querySelector('.param-required').value,
                    desc: card.querySelector('.param-desc').value.trim()
                });
            }
        });
        paramsJsonInput.value = JSON.stringify(params);
    });
    
    function escapeHTML(str) {
        var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};
        return String(str).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
</body>
</html>
