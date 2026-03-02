<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        switch ($_GET['action']) {
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_feedback WHERE id = ?"); $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '反馈已成功删除。'; break;
            case 'update_status':
                $status = $_GET['status'] ?? 'viewed';
                if(!in_array($status, ['viewed', 'resolved'])) $status = 'viewed';
                $stmt = $pdo->prepare("UPDATE sl_feedback SET status = ? WHERE id = ?"); $stmt->execute([$status, $id]);
                $_SESSION['feedback_msg'] = '反馈状态已更新。'; break;
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: feedback_list.php'); exit;
    }
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg']; $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $results_per_page = 15;
    $total_results = $pdo->query("SELECT count(*) FROM sl_feedback")->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $results_per_page;
    $stmt_list = $pdo->prepare("SELECT f.*, u.username, a.name as api_name FROM sl_feedback f LEFT JOIN sl_users u ON f.user_id = u.id LEFT JOIN sl_apis a ON f.api_id = a.id ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt_list->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_list->execute();
    $feedbacks = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $feedback_msg = '数据库操作失败: ' . $e->getMessage(); $feedback_type = 'error'; $feedbacks = []; }
function getFeedbackStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="badge badge-yellow">待处理</span>';
        case 'viewed': return '<span class="badge badge-blue">已查看</span>';
        case 'resolved': return '<span class="badge badge-green">已解决</span>';
        default: return '<span class="badge badge-gray">未知</span>';
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="keywords" content="白子API管理系统">
    <meta name="description" content="白子API管理系统后台">
    <title>用户反馈 - 白子API管理系统</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <style>
        .content-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-viewed {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-resolved {
            background-color: #dcfce7;
            color: #166534;
        }
    </style>
</head>
  
<body>
<div class="container-fluid">
  
  <div class="row">
    
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
            <div class="card-title">用户反馈</div>
        </header>
        <div class="card-body">
          
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
          
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>反馈内容</th>
                  <th>类型</th>
                  <th>用户</th>
                  <th>联系方式</th>
                  <th>状态</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($feedbacks)): ?>
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">暂无反馈记录</td>
                </tr>
                <?php else: ?>
                <?php foreach ($feedbacks as $item): ?>
                <tr>
                  <td><?php echo $item['id']; ?></td>
                  <td class="content-cell" title="<?php echo htmlspecialchars($item['content']); ?>">
                    <?php echo htmlspecialchars($item['content']); ?>
                  </td>
                  <td>
                    <?php if ($item['type'] === 'api'): ?>
                    <span class="badge bg-primary">接口问题</span>
                    <?php if ($item['api_name']): ?>
                    <small class="text-muted d-block"><?php echo htmlspecialchars($item['api_name']); ?></small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="badge bg-info">意见建议</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($item['username'] ?: '游客'); ?></td>
                  <td><?php echo htmlspecialchars($item['contact']); ?></td>
                  <td>
                    <?php if ($item['status'] === 'pending'): ?>
                      <span class="badge badge-pending">待处理</span>
                    <?php elseif ($item['status'] === 'viewed'): ?>
                      <span class="badge badge-viewed">已查看</span>
                    <?php else: ?>
                      <span class="badge badge-resolved">已解决</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <?php if ($item['status'] === 'pending'): ?>
                        <a class="btn btn-primary" href="?action=update_status&id=<?php echo $item['id']; ?>&status=viewed" data-bs-toggle="tooltip" title="标记为已查看">
                          <i class="mdi mdi-eye-check"></i>
                        </a>
                      <?php endif; ?>
                      <?php if ($item['status'] !== 'resolved'): ?>
                        <a class="btn btn-success" href="?action=update_status&id=<?php echo $item['id']; ?>&status=resolved" data-bs-toggle="tooltip" title="标记为已解决">
                          <i class="mdi mdi-check"></i>
                        </a>
                      <?php endif; ?>
                      <a class="btn btn-danger" href="?action=delete&id=<?php echo $item['id']; ?>" onclick="return confirm('确定要删除这条反馈吗？');" data-bs-toggle="tooltip" title="删除">
                        <i class="mdi mdi-delete"></i>
                      </a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          
          <?php if ($total_pages > 1): ?>
          <ul class="pagination justify-content-center mt-3">
            <?php if ($page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>">上一页</a>
              </li>
            <?php else: ?>
              <li class="page-item disabled">
                <span class="page-link">上一页</span>
              </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <?php if ($i == $page): ?>
                <li class="page-item active">
                  <span class="page-link"><?php echo $i; ?></span>
                </li>
              <?php else: ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
              <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>">下一页</a>
              </li>
            <?php else: ?>
              <li class="page-item disabled">
                <span class="page-link">下一页</span>
              </li>
            <?php endif; ?>
          </ul>
          <?php endif; ?>
          
        </div>
      </div>
    </div>
        
  </div>
  
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
<script>
$(function () {
  // 初始化工具提示
  $('[data-bs-toggle="tooltip"]').tooltip();
  
  // 自动隐藏提示框
  <?php if ($feedback_msg): ?>
  setTimeout(function() {
    $('.alert').alert('close');
  }, 3000);
  <?php endif; ?>
});
</script>
</body>
</html>