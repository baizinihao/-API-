<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
@set_time_limit(0);
date_default_timezone_set('Asia/Shanghai');

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
if (file_exists('../common/version.php')) { require_once '../common/version.php'; } else { define('SENLIN_CLIENT_VERSION', '0.0.0'); }
define('UPDATE_API_URL', 'http://106.14.180.166:9999/updates/api.php');
$username = htmlspecialchars($_SESSION['admin_username']);
$page_title = '在线更新';
$current_page = basename($_SERVER['PHP_SELF']);
$feedback_msg = '';
$feedback_type = '';
$update_info = null;
$update_available = false;
$update_start_time = null;
$file_size = 0;

function check_for_updates() {
    global $feedback_msg, $feedback_type, $update_info, $update_available, $file_size;
    try {
        $post_data = [
            'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]",
            'ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']),
            'version' => SENLIN_CLIENT_VERSION
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, UPDATE_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $api_response_str = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) { throw new Exception('cURL Error: ' . curl_error($ch)); }
        if ($http_code !== 200) { throw new Exception('更新服务器返回了非正常的HTTP状态码: ' . $http_code); }
        curl_close($ch);
        $api_response = json_decode($api_response_str, true);
        if ($api_response === null) { throw new Exception('无法解析来自更新服务器的响应。'); }
        if (!$api_response['success']) { throw new Exception($api_response['message'] ?? '从服务器获取更新信息失败。'); }
        $update_info = $api_response;
        $update_available = version_compare(SENLIN_CLIENT_VERSION, $api_response['version'], '<');
        
        // 获取实际文件大小
        if ($update_available && !empty($api_response['download_url'])) {
            $file_size = get_file_size($api_response['download_url']);
        }
        
        if(isset($_POST['action']) && $_POST['action'] === 'check'){
             $feedback_msg = '已成功获取最新版本信息。';
             $feedback_type = 'success';
        }
    } catch (Exception $e) {
        $feedback_msg = '检测更新失败: ' . $e->getMessage();
        $feedback_type = 'error';
    }
}

function get_file_size($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    return $size > 0 ? $size : 0;
}

function run_update() {
    global $feedback_msg, $feedback_type, $update_info, $update_start_time, $file_size;
    
    $log_dir = dirname(__FILE__, 2) . '/gn/';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . 'update_log_' . date('YmdHis') . '.txt';
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 开始更新到版本: " . ($update_info['version'] ?? '未知') . "\n", FILE_APPEND);
    
    $ch_check = curl_init();
    curl_setopt($ch_check, CURLOPT_URL, UPDATE_API_URL);
    curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
    $update_info_json = curl_exec($ch_check);
    curl_close($ch_check);
    $update_info = json_decode($update_info_json, true);
    if (!$update_info || !$update_info['success']) { 
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 获取更新包信息失败\n", FILE_APPEND);
        $feedback_msg = "无法开始更新，获取更新包信息失败。"; 
        $feedback_type = 'error'; 
        return; 
    }
    if (!version_compare(SENLIN_CLIENT_VERSION, $update_info['version'], '<')) { 
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 已经是最新版本，无需更新\n", FILE_APPEND);
        $feedback_msg = "已经是最新版本，无需更新。"; 
        $feedback_type = 'success'; 
        return; 
    }
    $download_url = $update_info['download_url'];
    $temp_zip_path = rtrim(sys_get_temp_dir(), '/') . '/update_package_' . uniqid() . '.zip';
    $extract_path = dirname(__FILE__, 2);
    
    $file_size = get_file_size($download_url);
    
    try {
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 开始下载更新包: $download_url\n", FILE_APPEND);
        
        $fp = fopen($temp_zip_path, 'w+');
        if (!$fp) { 
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 无法创建临时文件\n", FILE_APPEND);
            throw new Exception('无法创建临时文件，请检查临时目录权限。'); 
        }
        
        $ch = curl_init(str_replace(" ", "%20", $download_url));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); 
        curl_setopt($ch, CURLOPT_FILE, $fp); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if(!curl_exec($ch)) { 
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 下载失败: " . curl_error($ch) . "\n", FILE_APPEND);
            throw new Exception('下载更新包失败: ' . curl_error($ch)); 
        }
        
        curl_close($ch); 
        fclose($fp);
        
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 下载完成\n", FILE_APPEND);
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 开始解压\n", FILE_APPEND);
        
        if (!class_exists('ZipArchive')) { 
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 不支持ZipArchive\n", FILE_APPEND);
            throw new Exception('服务器不支持ZipArchive，无法解压。请安装php-zip扩展。'); 
        }
        
        $zip = new ZipArchive;
        if ($zip->open($temp_zip_path) === TRUE) {
            $zip->extractTo($extract_path);
            $zip->close();
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 解压完成\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 无法打开更新包文件\n", FILE_APPEND);
            throw new Exception('无法打开更新包文件。');
        }
        
        $version_file_path = $extract_path . '/common/version.php';
        $new_version_content = "<?php\n\ndefine('SENLIN_CLIENT_VERSION', '" . $update_info['version'] . "');\n?>";
        if (file_put_contents($version_file_path, $new_version_content) === false) {
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 无法更新版本文件\n", FILE_APPEND);
            throw new Exception("文件覆盖成功，但无法自动更新本地版本号文件，请检查 /common/version.php 文件的权限。");
        }
        
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($version_file_path, true);
        }
        
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 更新成功到版本: " . $update_info['version'] . "\n", FILE_APPEND);
        
        $feedback_msg = '系统已成功更新到版本 ' . $update_info['version'] . '！';
        $feedback_type = 'success';
        
    } catch (Exception $e) {
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] 更新失败: " . $e->getMessage() . "\n", FILE_APPEND);
        $feedback_msg = '更新过程中发生错误: ' . $e->getMessage();
        $feedback_type = 'error';
    } finally {
        if (file_exists($temp_zip_path)) {
            unlink($temp_zip_path);
        }
    }
}

function format_bytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update') {
        $update_start_time = microtime(true);
        run_update();
        if ($feedback_type === 'success') {
            $update_end_time = microtime(true);
            $update_duration = round($update_end_time - $update_start_time, 2);
            $feedback_msg .= " 更新用时: {$update_duration}秒";
        }
    }
}

check_for_updates();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>系统更新</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
<style>
body {
    background-color: #f8f9fa;
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    padding: 20px;
}

.container {
    max-width: 800px;
    margin: 0 auto;
}

.card {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    margin-bottom: 20px;
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 15px 20px;
    font-weight: 600;
    font-size: 16px;
    color: #212529;
}

.card-body {
    padding: 20px;
}

.version-box {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
}

.version-item {
    flex: 1;
    text-align: center;
    padding: 15px;
    background: #fff;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.version-label {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 5px;
}

.version-value {
    font-size: 20px;
    font-weight: 700;
    color: #0d6efd;
}

.update-btn {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    background: #0d6efd;
    color: white;
    border: none;
    border-radius: 6px;
}

.update-btn:hover {
    background: #0b5ed7;
}

.update-btn:disabled {
    background: #adb5bd;
    cursor: not-allowed;
}

.changelog-list {
    padding: 0;
    margin: 15px 0 0 0;
    list-style: none;
}

.changelog-item {
    padding: 8px 0;
    border-bottom: 1px solid #f1f1f1;
    color: #495057;
    font-size: 14px;
    display: flex;
}

.changelog-item:last-child {
    border-bottom: none;
}

.changelog-number {
    min-width: 24px;
    height: 24px;
    background: #0d6efd;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    margin-right: 10px;
}

.modal-content {
    border-radius: 8px;
}

.modal-header {
    background: #0d6efd;
    color: white;
    border-radius: 8px 8px 0 0;
    border: none;
    padding: 15px 20px;
}

.progress-container {
    margin: 20px 0;
}

.progress {
    height: 8px;
    border-radius: 4px;
    margin-bottom: 10px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}

.status-text {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 12px;
    font-size: 14px;
    color: #495057;
    margin: 15px 0;
}

.complete-icon {
    text-align: center;
    margin: 20px 0;
}

.complete-icon i {
    font-size: 48px;
    color: #198754;
}

.btn-close-white {
    filter: invert(1);
}

.file-info {
    text-align: center;
    color: #6c757d;
    margin: 10px 0;
    font-size: 14px;
}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            系统更新
        </div>
        <div class="card-body">
            <?php if ($feedback_msg): ?>
            <div class="alert alert-<?php echo $feedback_type; ?> alert-dismissible fade show mb-4">
                <?php echo htmlspecialchars($feedback_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="version-box">
                <div class="version-item">
                    <div class="version-label">当前版本</div>
                    <div class="version-value">v<?php echo SENLIN_CLIENT_VERSION; ?></div>
                </div>
                <div class="version-item">
                    <div class="version-label">最新版本</div>
                    <div class="version-value">v<?php echo htmlspecialchars($update_info['version'] ?? 'N/A'); ?></div>
                </div>
            </div>
            
            <?php if($update_available): ?>
            <div class="file-info">
                <i class="bi bi-download"></i> 更新文件大小: <?php echo format_bytes($file_size); ?>
            </div>
            <?php endif; ?>
            
            <button type="button" id="update-btn" class="update-btn mb-4" <?php if(!$update_available) echo 'disabled'; ?>>
                <?php if($update_available): ?>
                    更新到 v<?php echo htmlspecialchars($update_info['version']); ?>
                <?php else: ?>
                    已是最新版本
                <?php endif; ?>
            </button>
            
            <?php if($update_info && !empty($update_info['changelog'])): ?>
            <div>
                <div class="mb-3" style="font-weight: 500; color: #212529;">更新内容：</div>
                <ul class="changelog-list">
                    <?php 
                        $lines = array_filter(explode("\n", $update_info['changelog']), function($line) {
                            return trim($line) != '';
                        });
                        $line_number = 1;
                        foreach($lines as $line): 
                    ?>
                        <li class="changelog-item">
                            <span class="changelog-number"><?php echo $line_number; ?></span>
                            <span class="changelog-text"><?php echo htmlspecialchars(trim($line)); ?></span>
                        </li>
                    <?php 
                        $line_number++;
                        endforeach; 
                    ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">确认更新</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>确定要更新系统到 v<?php echo htmlspecialchars($update_info['version'] ?? ''); ?> 吗？</p>
                <p class="text-muted small">更新文件大小: <?php echo format_bytes($file_size); ?></p>
                <p class="text-muted small">更新过程中请勿关闭页面，否则可能导致更新失败。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="startUpdate()">确定更新</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="updateModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">系统更新</h5>
            </div>
            <div class="modal-body">
                <div id="progress-content">
                    <div class="progress-container">
                        <div class="d-flex justify-content-between mb-2">
                            <span>更新进度</span>
                            <span id="progress-percent">0%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
                        </div>
                        <div class="progress-info">
                            <span id="progress-status">正在检查更新...</span>
                        </div>
                    </div>
                    
                    <div class="status-text" id="step-text">
                        准备开始更新...
                    </div>
                    
                    <div class="file-info">
                        文件大小: <?php echo format_bytes($file_size); ?>
                    </div>
                </div>
                
                <div id="complete-content" style="display: none;">
                    <div class="complete-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h5 class="text-center mb-3">更新完成</h5>
                    <div class="text-center text-muted mb-4">系统已成功更新到最新版本</div>
                    
                    <div class="file-info">
                        <div>更新版本: v<?php echo htmlspecialchars($update_info['version'] ?? ''); ?></div>
                        <div>总用时: <span id="complete-duration">-</span></div>
                        <div>完成时间: <span id="complete-time">-</span></div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-success" onclick="completeUpdate()">完成</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let progressInterval;
let updateStartTime;
let currentProgress = 0;
let totalSize = <?php echo $file_size > 0 ? $file_size : 0; ?>;
let isUpdating = false;
let updatePhase = 'checking';
let updateDuration = 0;

// 初始化按钮事件
document.getElementById('update-btn').addEventListener('click', function() {
    if (!this.disabled) {
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        confirmModal.show();
    }
});

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function updateProgressDisplay() {
    const progressBar = document.getElementById('progress-bar');
    const progressPercent = document.getElementById('progress-percent');
    
    progressBar.style.width = currentProgress + '%';
    progressPercent.textContent = Math.round(currentProgress) + '%';
}

function updateStep(description, phase = null) {
    const stepText = document.getElementById('step-text');
    const progressStatus = document.getElementById('progress-status');
    
    stepText.textContent = description;
    progressStatus.textContent = description;
    
    if (phase) {
        updatePhase = phase;
    }
}

function showComplete() {
    document.getElementById('progress-content').style.display = 'none';
    document.getElementById('complete-content').style.display = 'block';
    
    updateDuration = Math.round((Date.now() - updateStartTime) / 1000);
    
    const completeTime = new Date();
    const hours = completeTime.getHours().toString().padStart(2, '0');
    const minutes = completeTime.getMinutes().toString().padStart(2, '0');
    const seconds = completeTime.getSeconds().toString().padStart(2, '0');
    
    document.getElementById('complete-duration').textContent = updateDuration + '秒';
    document.getElementById('complete-time').textContent = `${hours}:${minutes}:${seconds}`;
}

function completeUpdate() {
    // 关闭模态框并刷新整个页面
    const modal = bootstrap.Modal.getInstance(document.getElementById('updateModal'));
    if (modal) {
        modal.hide();
    }
    // 刷新页面
    setTimeout(() => {
        window.location.reload(true);
    }, 300);
}

function startUpdate() {
    if (isUpdating) return;
    
    // 关闭确认框
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
    if (confirmModal) {
        confirmModal.hide();
    }
    
    // 显示更新模态框
    const updateModal = new bootstrap.Modal(document.getElementById('updateModal'), {
        backdrop: 'static',
        keyboard: false
    });
    updateModal.show();
    
    isUpdating = true;
    updateStartTime = Date.now();
    currentProgress = 0;
    
    // 更新状态为检查中
    updateStep('正在检查更新信息...', 'checking');
    updateProgressDisplay();
    
    // 发送更新请求
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'update.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                const responseText = xhr.responseText;
                
                if (responseText.includes('成功')) {
                    // 收到成功响应，直接跳到100%
                    currentProgress = 100;
                    updateStep('更新完成！', 'done');
                    updateProgressDisplay();
                    
                    clearInterval(progressInterval);
                    
                    setTimeout(showComplete, 500);
                } else {
                    updateStep('更新失败，请查看日志', 'error');
                    isUpdating = false;
                    clearInterval(progressInterval);
                    
                    // 添加错误重试按钮
                    setTimeout(() => {
                        const progressContent = document.getElementById('progress-content');
                        const errorBtn = document.createElement('button');
                        errorBtn.className = 'btn btn-danger w-100';
                        errorBtn.textContent = '更新失败，点击重试';
                        errorBtn.onclick = function() {
                            window.location.reload();
                        };
                        progressContent.innerHTML = '';
                        progressContent.appendChild(errorBtn);
                    }, 500);
                }
            } else {
                updateStep('请求失败，请检查网络', 'error');
                isUpdating = false;
                clearInterval(progressInterval);
            }
        }
    };
    
    xhr.send('action=update');
    
    // 启动进度条模拟
    progressInterval = setInterval(() => {
        if (currentProgress < 100) {
            let increment = 0;
            
            switch(updatePhase) {
                case 'checking':
                    if (currentProgress < 10) {
                        increment = 0.5;
                    }
                    if (currentProgress >= 10) {
                        updateStep('正在下载更新文件...', 'downloading');
                    }
                    break;
                    
                case 'downloading':
                    if (currentProgress < 70) {
                        // 下载阶段：10% -> 70%
                        increment = 0.6;
                    }
                    if (currentProgress >= 70) {
                        updateStep('下载完成，正在解压文件...', 'extracting');
                    }
                    break;
                    
                case 'extracting':
                    if (currentProgress < 100) {
                        // 解压阶段：70% -> 100%
                        increment = 0.3;
                    }
                    break;
            }
            
            currentProgress = Math.min(100, currentProgress + increment);
            updateProgressDisplay();
        }
    }, 200);
}

// 更新模态框关闭时重置
const updateModalEl = document.getElementById('updateModal');
if (updateModalEl) {
    updateModalEl.addEventListener('hidden.bs.modal', function () {
        clearInterval(progressInterval);
        currentProgress = 0;
        isUpdating = false;
        updatePhase = 'checking';
        
        document.getElementById('progress-bar').style.width = '0%';
        document.getElementById('progress-percent').textContent = '0%';
        updateStep('准备开始更新...');
        
        document.getElementById('progress-content').style.display = 'block';
        document.getElementById('complete-content').style.display = 'none';
    });
}
</script>
</body>
</html>