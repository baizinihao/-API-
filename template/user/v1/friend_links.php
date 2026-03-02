<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');

// 登录验证，未登录则跳转至登录页
if (!isset($_SESSION['user_id'])) { 
    header('Location: /template/user/v1/login.php');
    exit;
}

$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { 
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php'); 
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/TemplateManager.php';

// 验证用户登录状态
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
// 强制登录验证，如果验证失败则跳转
if (!$is_logged_in) {
    header('Location: /template/user/v1/login.php');
    exit;
}
$user_info = ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']];

// 模板路径设置
$currentTemplate = basename(dirname(__FILE__));
$activeTemplate = TemplateManager::getActiveUserTemplate();
$homeTemplate = TemplateManager::getActiveHomeTemplate();
$homeTemplateBaseUrl = "/template/home/{$homeTemplate}/";
$userTemplate = TemplateManager::getActiveUserTemplate();
$userTemplateBaseUrl = "/template/user/{$userTemplate}/";

// 模板权限检查
if ($currentTemplate !== $activeTemplate) {
    header("HTTP/1.1 403 Forbidden");
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8">
        <title>访问被拒绝</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .container { max-width: 600px; margin: 0 auto; }
            h1 { color: #d9534f; }
            .btn { 
                display: inline-block; 
                padding: 10px 20px; 
                background: #337ab7; 
                color: white; 
                text-decoration: none; 
                border-radius: 4px; 
                margin-top: 20px;
            }
            .btn:hover { background: #286090; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>访问被拒绝</h1>
            <p>您正在尝试访问未激活的模板页面。</p>
            <p>请从首页重新进入用户中心。</p>
            <a href="/" class="btn">返回首页</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 友链数据处理
$links = []; 
$apply_msg = ''; 
$apply_type = ''; 
$total_links = 0;
$broken_links = 0;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 获取所有友链（包括异常状态）
    $stmt_links = $pdo->query("SELECT * FROM sl_friend_links 
                             WHERE status='approved' AND is_hidden=0 
                             ORDER BY sort_order DESC, created_at DESC");
    $links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);
    $total_links = count($links);
    
    // 统计异常友链数量
    foreach ($links as $link) {
        if ($link['status_check'] == 'broken') {
            $broken_links++;
        }
    }

    // 处理友链申请提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_friend_link'])) {
        $site_name = trim($_POST['site_name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $logo_url = trim($_POST['logo_url'] ?? '');

        // 表单验证
        if (empty($site_name) || empty($url)) {
            throw new Exception("网站名称和URL为必填项");
        }
        
        if (mb_strlen($site_name, 'UTF-8') > 50) {
            throw new Exception("网站名称长度不能超过50个字符");
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("网站URL格式不正确，请以http://或https://开头");
        }
        
        // 验证LOGO URL（如果提供）
        if (!empty($logo_url) && !filter_var($logo_url, FILTER_VALIDATE_URL)) {
            throw new Exception("LOGO URL格式不正确，请以http://或https://开头");
        }
        
        if (!empty($description) && mb_strlen($description, 'UTF-8') > 200) {
            throw new Exception("网站描述长度不能超过200个字符");
        }

        // 保存申请数据（关联当前登录用户ID）
        $stmt_apply = $pdo->prepare("INSERT INTO sl_friend_links 
                                   (site_name, url, description, logo, user_id, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt_apply->execute([$site_name, $url, $description, $logo_url, $_SESSION['user_id']]);

        $apply_msg = "友链申请已提交，我们将在1-3个工作日内审核，请耐心等待";
        $apply_type = "success";
    }
} catch (Exception $e) {
    $apply_msg = $e->getMessage();
    $apply_type = "error";
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<meta name="keywords" content="白子,友情链接,友链交换,API管理系统">
<meta name="description" content="友情链接 - 白子API管理系统">
<meta name="author" content="yinq">
<title>友情链接 - API管理系统</title>
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/js/bootstrap-multitabs/multitabs.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
<!-- 友链页面样式 -->
<style>
/* 优化后的友链卡片 */
.friend-card {
    transition: all 0.3s ease;
    border-radius: 0.75rem;
    box-shadow: 0 3px 10px rgba(0,0,0,0.07);
    overflow: hidden;
    position: relative;
    border: 1px solid #f0f2f5;
    margin-bottom: 1rem;
}

.friend-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.12);
    border-color: #e5e9f2;
}

.friend-card .card-body {
    padding: 1rem;
}

.friend-logo-container {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 6px;
    flex-shrink: 0;
    overflow: hidden;
    border: 1px solid #e9ecef;
}

.friend-logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.friend-logo-container:hover .friend-logo-img {
    transform: scale(1.1);
}

.friend-logo-icon {
    font-size: 22px;
    color: #4d5b76;
}

.friend-card h5 {
    margin-top: 0;
    margin-bottom: 0.3rem;
    line-height: 1.4;
    font-size: 1.05rem;
}

.friend-card p {
    margin-bottom: 0;
    font-size: 0.9rem;
    color: #6c757d;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.5;
}

.friend-status {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    z-index: 10;
}

/* 横向长方形统计卡片样式 - 增加顶部间距实现下移效果 */
.stats-container {
    display: flex;
    gap: 1rem;
    margin: 1rem 1rem 1.5rem; /* 增加顶部margin实现下移 */
    align-items: center;
}

.stats-card {
    flex: 1;
    border-radius: 0.75rem;
    transition: all 0.3s ease;
    overflow: hidden;
    border: 0;
    box-shadow: 0 3px 10px rgba(0,0,0,0.07);
    display: flex;
    align-items: center;
    padding: 0.9rem 1.2rem;
    height: 80px;
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}

.stats-card .mdi {
    font-size: 2rem;
    margin-right: 1.2rem;
    flex-shrink: 0;
}

.stats-info {
    flex-grow: 1;
}

.stats-card h5.card-title {
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
    color: #495057;
    font-weight: 500;
}

.stats-card .stat-value {
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.2;
}

/* 自定义滚动条 */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}
::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* 动画效果 */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.4s ease forwards;
}

/* 响应式布局调整 */
@media (min-width: 576px) {
    .friend-card-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}

@media (min-width: 768px) {
    .friend-card-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 992px) {
    .friend-card-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 575px) {
    .stats-container {
        flex-direction: column;
    }
    
    .stats-card {
        width: 100%;
    }
}

/* 表单提示文字样式调整 */
.form-hint {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 0.3rem;
}
</style>
</head>
<body>
<div class="container-fluid px-3 py-4">
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h4 class="mb-0">友情链接</h4>
                    <p class="text-muted small mb-0">展示合作网站链接及自助申请功能</p>
                </div>

                <!-- 横向长方形统计卡片 - 已下移 -->
                <div class="stats-container">
                    <div class="stats-card bg-primary bg-opacity-10 border-primary">
                        <i class="mdi mdi-link-variant text-primary"></i>
                        <div class="stats-info">
                            <h5 class="card-title">友链总数</h5>
                            <div class="stat-value"><?= $total_links ?></div>
                        </div>
                    </div>
                    <div class="stats-card bg-danger bg-opacity-10 border-danger">
                        <i class="mdi mdi-alert-circle-outline text-danger"></i>
                        <div class="stats-info">
                            <h5 class="card-title">异常友链</h5>
                            <div class="stat-value"><?= $broken_links ?></div>
                        </div>
                    </div>
                </div>

                <!-- 友链列表卡片 -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white">
                        <h5 class="card-title mb-0"><i class="mdi mdi-website me-2"></i>友情链接列表</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#applyLinkModal">
                            <i class="mdi mdi-pencil-plus me-1"></i>申请友链
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($apply_msg): ?>
                            <div class="alert alert-<?= $apply_type ?> alert-dismissible fade show mb-4">
                                <?= htmlspecialchars($apply_msg) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="friend-card-grid">
                            <?php if (empty($links)): ?>
                                <div class="col-12 text-center py-5 text-muted">
                                    <i class="mdi mdi-information-outline display-4 mb-3"></i>
                                    <p>暂无友情链接，欢迎申请合作</p>
                                    <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#applyLinkModal">
                                        <i class="mdi mdi-pencil-plus me-1"></i>立即申请
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($links as $index => $link): ?>
                                    <div class="friend-card" style="animation-delay: <?= $index * 100 ?>ms;">
                                        <div class="friend-status">
                                            <?php if ($link['status_check'] == 'broken'): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger">
                                                    <i class="mdi mdi-alert-circle-outline me-1"></i>异常
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success">
                                                    <i class="mdi mdi-check-circle-outline me-1"></i>正常
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex">
                                                <!-- LOGO展示 -->
                                                <div class="friend-logo-container flex-shrink-0 me-3">
                                                    <?php if (!empty($link['logo'])): ?>
                                                        <img src="<?= htmlspecialchars($link['logo']) ?>" 
                                                             class="friend-logo-img" 
                                                             alt="<?= htmlspecialchars($link['site_name']) ?>">
                                                    <?php else: ?>
                                                        <?php 
                                                        $siteType = strpos($link['url'], 'blog') !== false ? 'mdi mdi-blog' : 
                                                                    (strpos($link['url'], 'api') !== false ? 'mdi mdi-api' : 
                                                                    (strpos($link['url'], 'shop') !== false ? 'mdi mdi-store' : 'mdi mdi-web'));
                                                        ?>
                                                        <i class="friend-logo-icon <?= $siteType ?>"></i>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="flex-grow-1">
                                                    <h5 class="card-title mb-1">
                                                        <a href="<?= htmlspecialchars($link['url']) ?>" 
                                                           target="_blank" 
                                                           class="text-dark hover:text-primary font-weight-medium">
                                                            <?= htmlspecialchars($link['site_name']) ?>
                                                        </a>
                                                    </h5>
                                                    <p class="card-text">
                                                        <?= htmlspecialchars($link['description'] ?? '暂无描述') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 友链申请弹窗 -->
                <div class="modal fade" id="applyLinkModal" tabindex="-1" aria-labelledby="applyLinkModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header bg-white">
                                <h5 class="modal-title" id="applyLinkModalLabel"><i class="mdi mdi-pencil-plus me-2"></i>申请友情链接</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <form method="post" class="needs-validation" novalidate id="friend-link-form">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label for="site_name" class="form-label">网站名称 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" 
                                                value="<?= htmlspecialchars($_POST['site_name'] ?? '') ?>" 
                                                placeholder="请输入网站名称" required>
                                            <div class="invalid-feedback">请填写网站名称</div>
                                            <div class="form-hint">最多50个字符</div>
                                        </div>

                                        <div class="col-md-12">
                                            <label for="url" class="form-label">网站URL <span class="text-danger">*</span></label>
                                            <input type="url" class="form-control" id="url" name="url" 
                                                value="<?= htmlspecialchars($_POST['url'] ?? '') ?>" 
                                                placeholder="请输入http://或https://开头的网址" required>
                                            <div class="invalid-feedback">请填写有效的URL地址</div>
                                        </div>

                                        <div class="col-md-12">
                                            <label for="logo_url" class="form-label">网站LOGO链接</label>
                                            <input type="url" class="form-control" id="logo_url" name="logo_url" 
                                                value="<?= htmlspecialchars($_POST['logo_url'] ?? '') ?>" 
                                                placeholder="请输入http://或https://开头的LOGO链接">
                                            <div class="invalid-feedback">请填写有效的URL地址</div>
                                            <div class="form-hint">
                                                建议尺寸：120x120px，支持JPG、PNG、GIF、WebP格式
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <label for="description" class="form-label">网站描述</label>
                                            <textarea class="form-control" id="description" name="description" rows="3" 
                                                placeholder="请简要描述您的网站"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                            <div class="form-hint">建议填写网站核心内容或特色，有助于提高审核通过率（最多200个字符）</div>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label d-block">申请须知</label>
                                            <ul class="text-muted small">
                                                <li>1. 提交后将在1-3个工作日内完成审核</li>
                                                <li>2. 内容需符合法律法规及平台规范</li>
                                                <li>3. 审核通过后将展示在友链列表中</li>
                                                <li>4. 若网站内容与本平台不符，可能会被拒绝</li>
                                            </ul>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer bg-white">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                <button type="submit" form="friend-link-form" name="apply_friend_link" class="btn btn-primary">
                                    <i class="mdi mdi-send me-2"></i>提交申请
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/perfect-scrollbar.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap-multitabs/multitabs.min.js"></script>
<script type="text/javascript" src="../../../assets/js/jquery.cookie.min.js"></script>
<script type="text/javascript" src="../../../assets/js/index.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 表单验证
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

    // 自动隐藏提示框
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000); // 5秒后自动隐藏提示

    // 登录状态检查（5分钟一次）
    setInterval(() => {
        fetch('../../../common/ajax/check_session.php')
            .then(response => response.json())
            .then(data => {
                if (!data.logged_in) {
                    alert('登录状态已过期，请重新登录');
                    location.href = '../../../user/login.php';
                }
            })
            .catch(error => {
                console.error('Session check failed:', error);
            });
    }, 300000); // 5分钟检查一次登录状态

    // LOGO链接验证
    const logoInput = document.getElementById('logo_url');
    if (logoInput) {
        logoInput.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value && !isValidUrl(value)) {
                this.setCustomValidity('请输入有效的URL地址');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });
    }

    // URL验证辅助函数
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    // 为友链卡片添加渐入动画
    document.querySelectorAll('.friend-card').forEach(card => {
        card.classList.add('fade-in');
    });
});
</script>
</body>
</html>