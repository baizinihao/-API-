<?php
@session_start();
//@error_reporting(0);
@ini_set('display_errors', 'Off');

// 登录验证
if (!isset($_SESSION['admin_id'])) { 
    header('Location: login.php'); 
    exit; 
}

// 配置文件加载
if (file_exists('../config.php')) { 
    require_once '../config.php'; 
} else { 
    die("出现错误！配置文件丢失。"); 
}

$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; 
$feedback_type = '';

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; // 每页显示12条（卡片每行3个，共4行）
$offset = ($page - 1) * $limit;
$totalRecords = 0;
$totalPages = 0;

try {
    // 数据库连接
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 单条操作处理
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        switch ($_GET['action']) {
            // 删除友链
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_friend_links WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '友链已删除';
                break;

            // 审核通过
            case 'approve':
                $stmt = $pdo->prepare("UPDATE sl_friend_links SET status='approved', reviewed_at=NOW() WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '友链已通过审核';
                break;

            // 审核拒绝
            case 'reject':
                $note = isset($_POST['reject_note']) ? trim($_POST['reject_note']) : '未提供原因';
                $stmt = $pdo->prepare("UPDATE sl_friend_links SET status='rejected', review_note=?, reviewed_at=NOW() WHERE id = ?");
                $stmt->execute([$note, $id]);
                $_SESSION['feedback_msg'] = '友链已拒绝';
                break;

            // 切换隐藏状态
            case 'toggle':
                $stmt = $pdo->prepare("SELECT is_hidden FROM sl_friend_links WHERE id = ?");
                $stmt->execute([$id]);
                $link = $stmt->fetch();
                $newStatus = $link['is_hidden'] ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE sl_friend_links SET is_hidden=? WHERE id = ?");
                $stmt->execute([$newStatus, $id]);
                $_SESSION['feedback_msg'] = '友链' . ($newStatus ? '已隐藏' : '已显示');
                break;
                
            // 切换友链状态（正常/异常）
            case 'toggle_status':
                $stmt = $pdo->prepare("SELECT status_check FROM sl_friend_links WHERE id = ?");
                $stmt->execute([$id]);
                $link = $stmt->fetch();
                $newStatus = ($link['status_check'] == 'normal') ? 'broken' : 'normal';
                $stmt = $pdo->prepare("UPDATE sl_friend_links SET status_check=? WHERE id = ?");
                $stmt->execute([$newStatus, $id]);
                $_SESSION['feedback_msg'] = '友链状态已更新为' . ($newStatus == 'normal' ? '正常' : '异常');
                break;
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: friend_links.php' . (isset($_GET['page']) ? "?page={$_GET['page']}" : ''));
        exit;
    }

    // 批量操作处理
    if (isset($_POST['batch_action']) && isset($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        switch ($_POST['batch_action']) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE sl_friend_links SET status='approved', reviewed_at=NOW() WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已批量通过' . count($ids) . '条友链';
                break;
            
            case 'reject':
                $note = $_POST['reject_note'] ?? '批量拒绝';
                $stmt = $pdo->prepare("UPDATE sl_friend_links SET status='rejected', review_note=? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$note], $ids));
                $_SESSION['feedback_msg'] = '已批量拒绝' . count($ids) . '条友链';
                break;
            
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_friend_links WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已批量删除' . count($ids) . '条友链';
                break;
            
            case 'toggle':
                $stmt = $pdo->prepare("UPDATE sl_friend_links SET is_hidden = 1 - is_hidden WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已批量切换' . count($ids) . '条友链显示状态';
                break;
                
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE sl_friend_links SET status_check = CASE WHEN status_check = 'normal' THEN 'broken' ELSE 'normal' END WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已批量切换' . count($ids) . '条友链状态';
                break;
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: friend_links.php' . (isset($_GET['page']) ? "?page={$_GET['page']}" : ''));
        exit;
    }

    // 搜索筛选逻辑
    $where = [];
    $params = [];
    if (!empty($_GET['name'])) {
        $where[] = "site_name LIKE ?";
        $params[] = "%{$_GET['name']}%";
    }
    if (!empty($_GET['status'])) {
        $where[] = "status = ?";
        $params[] = $_GET['status'];
    }
    if (isset($_GET['is_hidden']) && $_GET['is_hidden'] !== '') {
        $where[] = "is_hidden = ?";
        $params[] = intval($_GET['is_hidden']);
    }
    if (!empty($_GET['status_check'])) {
        $where[] = "status_check = ?";
        $params[] = $_GET['status_check'];
    }
    $whereStr = $where ? "WHERE " . implode(' AND ', $where) : '';

    // 获取总记录数
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sl_friend_links {$whereStr}");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $limit));

    // 获取友链列表（带分页）
    $stmt = $pdo->prepare("SELECT * FROM sl_friend_links {$whereStr} ORDER BY sort_order DESC, created_at DESC LIMIT ?, ?");
    
    // 绑定查询条件参数
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    
    // 绑定分页参数
    $stmt->bindValue(count($params) + 1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 反馈信息处理
    if (isset($_SESSION['feedback_msg'])) {
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
} catch (PDOException $e) {
    $feedback_msg = '数据库错误: ' . $e->getMessage();
    $feedback_type = 'error';
    $links = [];
}

// 状态徽章生成函数
function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="badge bg-warning bg-opacity-10 text-warning"><i class="mdi mdi-clock-outline me-1"></i>待审核</span>';
        case 'approved': return '<span class="badge bg-success bg-opacity-10 text-success"><i class="mdi mdi-check-circle-outline me-1"></i>已通过</span>';
        case 'rejected': return '<span class="badge bg-danger bg-opacity-10 text-danger"><i class="mdi mdi-close-circle-outline me-1"></i>已拒绝</span>';
        default: return '<span class="badge bg-secondary"><i class="mdi mdi-help-circle-outline me-1"></i>未知</span>';
    }
}

// 友链状态徽章生成函数
function getStatusCheckBadge($status) {
    switch ($status) {
        case 'normal': return '<span class="badge bg-success bg-opacity-10 text-success"><i class="mdi mdi-check-circle-outline me-1"></i>正常</span>';
        case 'broken': return '<span class="badge bg-danger bg-opacity-10 text-danger"><i class="mdi mdi-alert-circle-outline me-1"></i>异常</span>';
        default: return '<span class="badge bg-secondary"><i class="mdi mdi-help-circle-outline me-1"></i>未知</span>';
    }
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
/* 友链卡片样式 */
.link-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
    margin-bottom: 1.5rem;
    position: relative;
    background-color: #fff;
}

.link-card:hover {
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.link-card .card-header {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.link-card .card-header .logo-container {
    width: 48px;
    height: 48px;
    margin-right: 1rem;
    flex-shrink: 0;
}

.link-card .card-header .logo-container img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 0.25rem;
}

.link-card .card-header .info {
    flex-grow: 1;
}

.link-card .card-header .info h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.link-card .card-header .info .url {
    font-size: 0.875rem;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.link-card .card-body {
    padding: 1rem;
}

.link-card .card-body .details {
    margin-bottom: 1rem;
}

.link-card .card-body .details p {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.link-card .card-body .details p i {
    margin-right: 0.5rem;
    color: #64748b;
    width: 1.2rem;
    text-align: center;
}

.link-card .card-body .tags {
    margin-bottom: 1rem;
}

.link-card .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    border-top: 1px solid #e2e8f0;
    background-color: #f8fafc;
    border-radius: 0 0 0.5rem 0.5rem;
}

.link-card .batch-checkbox {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    z-index: 10;
}

/* 响应式布局 */
@media (min-width: 992px) {
    .link-card-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
}

@media (max-width: 991px) and (min-width: 768px) {
    .link-card-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
}

@media (max-width: 767px) {
    .link-card-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
}
</style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
                <div class="card-title">
                  <i class="mdi mdi-link-variant me-2"></i>友链管理
                </div>
                <div class="card-action">
                  <a href="friend_link_add.php" class="btn btn-primary btn-sm">
                    <i class="mdi mdi-plus"></i> 添加友链
                  </a>
                </div>
              </header>
              <div class="card-body">
                
                <!-- 反馈提示框 -->
                <?php if ($feedback_msg): ?>
                <div class="alert alert-<?= $feedback_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                  <?= htmlspecialchars($feedback_msg) ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- 搜索筛选表单 -->
                <div class="card-search mb-3">
                  <form method="get" class="search-form">
                    <div class="row">
                      <div class="col-md-3">
                        <div class="row">
                          <label class="col-sm-4 col-form-label">网站名称</label>
                          <div class="col-sm-8">
                            <input type="text" class="form-control" name="name" 
                                   value="<?= htmlspecialchars($_GET['name'] ?? '') ?>" 
                                   placeholder="请输入网站名称">
                          </div>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="row">
                          <label class="col-sm-4 col-form-label">审核状态</label>
                          <div class="col-sm-8">
                            <select name="status" class="form-select">
                              <option value="">全部</option>
                              <option value="pending" <?= isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : '' ?>>待审核</option>
                              <option value="approved" <?= isset($_GET['status']) && $_GET['status'] === 'approved' ? 'selected' : '' ?>>已通过</option>
                              <option value="rejected" <?= isset($_GET['status']) && $_GET['status'] === 'rejected' ? 'selected' : '' ?>>已拒绝</option>
                            </select>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="row">
                          <label class="col-sm-4 col-form-label">显示状态</label>
                          <div class="col-sm-8">
                            <select name="is_hidden" class="form-select">
                              <option value="">全部</option>
                              <option value="0" <?= isset($_GET['is_hidden']) && $_GET['is_hidden'] === '0' ? 'selected' : '' ?>>显示</option>
                              <option value="1" <?= isset($_GET['is_hidden']) && $_GET['is_hidden'] === '1' ? 'selected' : '' ?>>隐藏</option>
                            </select>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="row">
                          <label class="col-sm-4 col-form-label">友链状态</label>
                          <div class="col-sm-8">
                            <select name="status_check" class="form-select">
                              <option value="">全部</option>
                              <option value="normal" <?= isset($_GET['status_check']) && $_GET['status_check'] === 'normal' ? 'selected' : '' ?>>正常</option>
                              <option value="broken" <?= isset($_GET['status_check']) && $_GET['status_check'] === 'broken' ? 'selected' : '' ?>>异常</option>
                            </select>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-3 mt-2">
                        <div class="row">
                          <label class="col-sm-4 col-form-label">&nbsp;</label>
                          <div class="col-sm-8">
                            <button type="submit" class="btn btn-primary me-1">
                              <i class="mdi mdi-magnify"></i> 搜索
                            </button>
                            <a href="friend_links.php" class="btn btn-default">
                              <i class="mdi mdi-refresh"></i> 重置
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </form>
                </div>
                
                <!-- 批量操作区 -->
                <div class="batch-actions mb-3">
                  <div class="form-check form-check-inline">
                    <input type="checkbox" class="form-check-input" id="check-all">
                    <label class="form-check-label" for="check-all">全选</label>
                  </div>
                  <select name="batch_action" id="batch-action" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                    <option value="">批量操作</option>
                    <option value="approve"><i class="mdi mdi-check-circle-outline me-1"></i>批量通过</option>
                    <option value="reject"><i class="mdi mdi-close-circle-outline me-1"></i>批量拒绝</option>
                    <option value="delete"><i class="mdi mdi-delete me-1"></i>批量删除</option>
                    <option value="toggle"><i class="mdi mdi-eye-outline me-1"></i>批量切换显示</option>
                    <option value="toggle_status"><i class="mdi mdi-refresh me-1"></i>批量切换状态</option>
                  </select>
                  <button type="button" class="btn btn-success btn-sm" id="batch-submit">
                    <i class="mdi mdi-check"></i> 执行操作
                  </button>
                  <div class="text-muted small mt-2">
                    提示: 勾选需要操作的友链，选择操作类型后点击"执行操作"
                    <span class="ms-2">共 <?= $totalRecords ?> 条数据</span>
                  </div>
                </div>
                
                <!-- 友链卡片网格 -->
                <div class="link-card-grid">
                  <?php if (empty($links)): ?>
                    <div class="col-12 text-center py-6 text-muted">
                      <div class="mb-3">
                        <i class="mdi mdi-information-outline text-4xl"></i>
                      </div>
                      <p>暂无友链数据</p>
                    </div>
                  <?php else: ?>
                    <?php foreach ($links as $link): ?>
                      <div class="link-card">
                        <!-- 批量选择复选框 -->
                        <div class="batch-checkbox">
                          <div class="form-check">
                            <input type="checkbox" class="form-check-input ids" name="ids[]" 
                                   value="<?= $link['id'] ?>" id="ids-<?= $link['id'] ?>">
                          </div>
                        </div>
                        
                        <!-- 卡片头部 -->
                        <div class="card-header">
                          <div class="logo-container">
                            <?php if (!empty($link['logo'])): ?>
                              <img src="<?= htmlspecialchars($link['logo']) ?>" 
                                   alt="<?= htmlspecialchars($link['site_name']) ?>"
                                   onError="this.src='../assets/images/default-logo.png'">
                            <?php else: ?>
                              <div class="d-flex align-items-center justify-content-center h-full bg-gray-100 rounded">
                                <span class="text-gray-500">无LOGO</span>
                              </div>
                            <?php endif; ?>
                          </div>
                          <div class="info">
                            <h4><?= htmlspecialchars($link['site_name']) ?></h4>
                            <div class="url">
                              <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="text-primary">
                                <i class="mdi mdi-web me-1"></i>
                                <?= mb_strlen($link['url']) > 30 ? mb_substr($link['url'], 0, 30) . '...' : $link['url'] ?>
                              </a>
                            </div>
                          </div>
                        </div>
                        
                        <!-- 卡片主体 -->
                        <div class="card-body">
                          <div class="details">
                            <p><i class="mdi mdi-account-outline"></i>
                              <strong>申请用户:</strong> <?= $link['user_id'] ? "用户ID:{$link['user_id']}" : '游客' ?></p>
                            <p><i class="mdi mdi-sort-numeric-ascending"></i>
                              <strong>排序值:</strong> <?= $link['sort_order'] ?? 0 ?></p>
                            <p><i class="mdi mdi-calendar-outline"></i>
                              <strong>申请时间:</strong> <?= $link['created_at'] ?></p>
                          </div>
                          
                          <div class="tags">
                            <span class="me-2"><?= getStatusBadge($link['status']) ?></span>
                            <a href="friend_links.php?action=toggle_status&id=<?= $link['id'] ?><?= isset($_GET['page']) ? "&page={$_GET['page']}" : '' ?>"
                               onclick="return confirm('确定要将此友链状态切换为<?= $link['status_check'] == 'normal' ? '异常' : '正常' ?>吗？')">
                              <?= getStatusCheckBadge($link['status_check']) ?>
                            </a>
                            <?php if ($link['is_hidden']): ?>
                              <span class="badge bg-danger bg-opacity-10 text-danger ms-2">
                                <i class="mdi mdi-eye-off-outline me-1"></i>隐藏
                              </span>
                            <?php endif; ?>
                          </div>
                          
                          <?php if ($link['status'] === 'rejected' && !empty($link['review_note'])): ?>
                            <div class="mt-2 p-2 bg-danger bg-opacity-10 text-danger rounded">
                              <i class="mdi mdi-alert-circle-outline me-1"></i>
                              <small><strong>拒绝原因:</strong> <?= htmlspecialchars($link['review_note']) ?></small>
                            </div>
                          <?php endif; ?>
                        </div>
                        
                        <!-- 卡片底部 -->
                        <div class="card-footer">
                          <div class="actions">
                            <a href="friend_link_edit.php?id=<?= $link['id'] ?>" 
                               class="btn btn-outline-primary btn-sm me-1" title="编辑">
                              <i class="mdi mdi-pencil"></i>
                            </a>
                            
                            <?php if ($link['status'] === 'pending'): ?>
                              <a href="friend_links.php?action=approve&id=<?= $link['id'] ?><?= isset($_GET['page']) ? "&page={$_GET['page']}" : '' ?>" 
                                 class="btn btn-outline-success btn-sm me-1" title="通过"
                                 onclick="return confirm('确定通过此友链？')">
                                <i class="mdi mdi-check"></i>
                              </a>
                              <button type="button" class="btn btn-outline-danger btn-sm reject-btn" 
                                      data-id="<?= $link['id'] ?>" title="拒绝">
                                <i class="mdi mdi-close"></i>
                              </button>
                            <?php else: ?>
                              <a href="friend_links.php?action=toggle&id=<?= $link['id'] ?><?= isset($_GET['page']) ? "&page={$_GET['page']}" : '' ?>" 
                                 class="btn btn-outline-secondary btn-sm me-1" 
                                 title="<?= $link['is_hidden'] ? '显示' : '隐藏' ?>">
                                <i class="mdi mdi-<?= $link['is_hidden'] ? 'eye' : 'eye-off' ?>"></i>
                              </a>
                            <?php endif; ?>
                          </div>
                          
                          <a href="friend_links.php?action=delete&id=<?= $link['id'] ?><?= isset($_GET['page']) ? "&page={$_GET['page']}" : '' ?>" 
                             class="btn btn-outline-danger btn-sm" title="删除"
                             onclick="return confirm('确定删除此友链？删除后不可恢复！')">
                            <i class="mdi mdi-delete"></i>
                          </a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                
                <!-- 分页 -->
                <div class="row mt-3">
                  <div class="col-md-12">
                    <nav class="float-end">
                      <ul class="pagination">
                        <!-- 上一页 -->
                        <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                          <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($_GET['name']) ? "&name=" . urlencode($_GET['name']) : '' ?><?= !empty($_GET['status']) ? "&status={$_GET['status']}" : '' ?><?= isset($_GET['is_hidden']) ? "&is_hidden={$_GET['is_hidden']}" : '' ?><?= !empty($_GET['status_check']) ? "&status_check={$_GET['status_check']}" : '' ?>">
                            <i class="mdi mdi-chevron-left"></i>
                          </a>
                        </li>
                        
                        <!-- 页码 -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                          <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($_GET['name']) ? "&name=" . urlencode($_GET['name']) : '' ?><?= !empty($_GET['status']) ? "&status={$_GET['status']}" : '' ?><?= isset($_GET['is_hidden']) ? "&is_hidden={$_GET['is_hidden']}" : '' ?><?= !empty($_GET['status_check']) ? "&status_check={$_GET['status_check']}" : '' ?>">
                              <?= $i ?>
                            </a>
                          </li>
                        <?php endfor; ?>
                        
                        <!-- 下一页 -->
                        <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                          <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($_GET['name']) ? "&name=" . urlencode($_GET['name']) : '' ?><?= !empty($_GET['status']) ? "&status={$_GET['status']}" : '' ?><?= isset($_GET['is_hidden']) ? "&is_hidden={$_GET['is_hidden']}" : '' ?><?= !empty($_GET['status_check']) ? "&status_check={$_GET['status_check']}" : '' ?>">
                            <i class="mdi mdi-chevron-right"></i>
                          </a>
                        </li>
                      </ul>
                    </nav>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
    <!--End 页面主要内容-->
  </div>
</div>

<!-- 拒绝原因弹窗（使用Bootstrap模态框） -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">填写拒绝原因</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="reject-form" method="post">
        <div class="modal-body">
          <input type="hidden" name="id" id="reject-id">
          <textarea class="form-control" name="reject_note" rows="3" placeholder="请输入拒绝原因（选填）"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="mdi mdi-close"></i> 取消
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="mdi mdi-check"></i> 确认拒绝
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
<script>
$(document).ready(function() {
  // 全选/取消全选
  $('#check-all').change(function() {
    $('.ids').prop('checked', $(this).prop('checked'));
  });
  
  // 批量操作执行
  $('#batch-submit').click(function() {
    const action = $('#batch-action').val();
    const checked = $('.ids:checked').length;
    
    if (!action) {
      alert('请选择操作类型');
      return;
    }
    
    if (checked === 0) {
      alert('请至少选择一条友链');
      return;
    }
    
    if (action === 'reject') {
      if (confirm('确定拒绝选中的' + checked + '条友链？')) {
        // 批量拒绝时添加原因输入框
        const note = prompt('请输入拒绝原因（可选）：', '批量拒绝');
        $('#batchForm').append(
          $('<input>', {name: 'reject_note', value: note, type: 'hidden'})
        ).append(
          $('<input>', {name: 'batch_action', value: 'reject', type: 'hidden'})
        ).submit();
      }
    } else {
      const confirmMsg = {
        'approve': '确定通过选中的' + checked + '条友链？',
        'delete': '确定删除选中的' + checked + '条友链？此操作不可恢复！',
        'toggle': '确定切换选中的' + checked + '条友链的显示状态？',
        'toggle_status': '确定切换选中的' + checked + '条友链的状态？'
      }[action] || '确定执行此操作？';
      
      if (confirm(confirmMsg)) {
        $('#batchForm').append(
          $('<input>', {name: 'batch_action', value: action, type: 'hidden'})
        ).submit();
      }
    }
  });
  
  // 单条拒绝操作（使用Bootstrap模态框）
  $('.reject-btn').click(function() {
    const id = $(this).data('id');
    $('#reject-id').val(id);
    $('#rejectModal').modal('show');
  });
  
  // 提交拒绝原因
  $('#reject-form').submit(function(e) {
    e.preventDefault();
    const id = $('#reject-id').val();
    const note = $(this).find('textarea').val();
    const page = <?= isset($_GET['page']) ? $_GET['page'] : 1 ?>;
    
    // 构造表单提交
    const form = $('<form>', {
      method: 'post',
      action: `friend_links.php?action=reject&id=${id}&page=${page}`
    }).append(
      $('<input>', {name: 'reject_note', value: note, type: 'hidden'})
    );
    
    $('body').append(form);
    form.submit();
  });
});
</script>
</body>
</html>