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
   
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id_to_delete = intval($_GET['id']);
        $stmt_get = $pdo->prepare("SELECT file_path FROM sl_apis WHERE id = ?");
        $stmt_get->execute([$id_to_delete]);
        $api = $stmt_get->fetch();
        if ($api && !empty($api['file_path'])) {
            $safe_path = realpath('../' . $api['file_path']);
            $base_dir = realpath('../API');
            if ($safe_path && strpos($safe_path, $base_dir) === 0 && file_exists($safe_path)) {
                unlink($safe_path);
            }
        }
        $stmt_delete = $pdo->prepare("DELETE FROM sl_apis WHERE id = ?");
        $stmt_delete->execute([$id_to_delete]);
        $_SESSION['feedback_msg'] = '接口已成功删除。';
        $_SESSION['feedback_type'] = 'success';
        header('Location: api_list.php');
        exit;
    }
   
    if (isset($_POST['batch_action']) && isset($_POST['ids'])) {
        $ids = $_POST['ids'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        switch ($_POST['batch_action']) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE sl_apis SET status = 'normal' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已启用选中的接口';
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE sl_apis SET status = 'error' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已禁用选中的接口';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("SELECT id, file_path FROM sl_apis WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $apis = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 删除文件
                foreach ($apis as $api) {
                    if (!empty($api['file_path'])) {
                        $safe_path = realpath('../' . $api['file_path']);
                        $base_dir = realpath('../API');
                        if ($safe_path && strpos($safe_path, $base_dir) === 0 && file_exists($safe_path)) {
                            unlink($safe_path);
                        }
                    }
                }
                
                $stmt = $pdo->prepare("DELETE FROM sl_apis WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已删除选中的接口';
                break;
        }
        
        $_SESSION['feedback_type'] = 'success';
        header('Location: api_list.php');
        exit;
    }
    
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    
    $stmt_list = $pdo->query("SELECT * FROM sl_apis ORDER BY id DESC");
    $apis = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback_msg = '数据库操作失败: ' . $e->getMessage();
    $feedback_type = 'error';
    $apis = [];
}

function getStatusBadge($status) {
    switch ($status) {
        case 'normal': return '<span class="badge badge-green">正常</span>';
        case 'error': return '<span class="badge badge-red">异常</span>';
        case 'maintenance': return '<span class="badge badge-yellow">维护</span>';
        case 'deprecated': return '<span class="badge badge-gray">失效</span>';
        default: return '<span class="badge badge-dark grey">未知</span>';
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
l<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
  
<body>
<div class="container-fluid">
  
  <div class="row">
    
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
          <div class="card-title">API 接口列表</div>
          <div class="card-action">
            <a href="api_edit.php" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> 添加新接口</a>
          </div>
        </header>
        <div class="card-body">
          
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
          
          <div class="card-search mb-3">
            <form class="search-form" method="get" action="api_list.php" role="form">
              <div class="row">
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">接口名称</label>
                    <div class="col-sm-8">
                      <input type="text" class="form-control" name="name" value="" placeholder="请输入接口名称" />
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">接口类型</label>
                    <div class="col-sm-8">
                      <select name="type" class="form-select">
                        <option value="">全部</option>
                        <option value="local">本地接口</option>
                        <option value="remote">远程接口</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">接口状态</label>
                    <div class="col-sm-8">
                      <select name="status" class="form-select">
                        <option value="">全部</option>
                        <option value="normal">正常</option>
                        <option value="error">异常</option>
                        <option value="deprecated">失效</option>
                        <option value="maintenance">维护中</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
    <div class="row">
        <label class="col-sm-4 col-form-label">接口分类</label>
        <div class="col-sm-8">
            <select name="category" class="form-select">
                <option value="">全部</option>
                <?php 
                $stmt_cats = $pdo->query("SELECT * FROM sl_api_categories ORDER BY name");
                while($cat = $stmt_cats->fetch(PDO::FETCH_ASSOC)): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo isset($_GET['category']) && $_GET['category'] == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
</div>

              </div>
              
              <div class="row mt-2">
                <div class="col-md-12 text-end">
                  <button type="submit" class="btn btn-primary me-1">搜索</button>
                  <button type="reset" class="btn btn-default">重置</button>
                </div>
              </div>
            </form>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th width="40">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="check-all">
                      <label class="form-check-label" for="check-all"></label>
                    </div>
                  </th>
                  <th>接口名称</th>
                  <th>调用地址</th>
                  <th width="100">类型</th>
                  <th width="100">权限</th>
                  <th width="100">状态</th>
                  <th width="100">接口分类</th>
                  <th width="120">调用次数</th>
                  <th width="120">操作</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($apis)): ?>
                <tr>
                  <td colspan="8" class="text-center py-4 text-muted">
                    <i class="mdi mdi-information-outline me-1"></i> 暂无数据，请先添加一个接口
                  </td>
                </tr>
                <?php else: ?>
                  <?php foreach ($apis as $api): ?>
                  <tr>
                    <td>
                      <div class="form-check">
                        <input type="checkbox" class="form-check-input ids" name="ids[]" value="<?php echo $api['id']; ?>" id="ids-<?php echo $api['id']; ?>">
                        <label class="form-check-label" for="ids-<?php echo $api['id']; ?>"></label>
                      </div>
                    </td>
                    <td><?php echo htmlspecialchars($api['name']); ?></td>
                    <td><code>/API/<?php echo htmlspecialchars(rawurldecode($api['endpoint'])); ?>.php</code></td>
                    <td>
                      <span class="badge bg-primary bg-opacity-10 text-primary">
                        <?php echo $api['type'] === 'local' ? '本地' : '远程'; ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-secondary bg-opacity-10 text-secondary">
                        <?php echo $api['visibility'] === 'public' ? '公开' : ($api['is_billable'] ? '计费' : '密钥'); ?>
                      </span>
                    </td>
                    <td>
<?php 
if ($api['status'] === 'normal'): ?>
  <span class="badge bg-success bg-opacity-10 text-success">启用</span>
<?php elseif ($api['status'] === 'error'): ?>
  <span class="badge bg-danger bg-opacity-10 text-danger">禁用</span>
<?php elseif ($api['status'] === 'deprecated'): ?>
  <span class="badge bg-secondary bg-opacity-10 text-secondary">失效</span>
<?php elseif ($api['status'] === 'maintenance'): ?>
  <span class="badge bg-info bg-opacity-10 text-info">维护中</span>
<?php else: ?>
  <span class="badge bg-warning bg-opacity-10 text-warning">未知</span>
<?php endif; ?>
                  </td>
                  <td>
    <?php 
    if ($api['category_id']) {
        $stmt_cat = $pdo->prepare("SELECT name FROM sl_api_categories WHERE id = ?");
        $stmt_cat->execute([$api['category_id']]);
        $category_name = $stmt_cat->fetchColumn();
        echo htmlspecialchars($category_name);
    } else {
        echo '<span class="text-muted">无分类</span>';
    }
    ?>
</td>
                    <td><?php echo number_format($api['total_calls']); ?></td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <a href="api_edit.php?id=<?php echo $api['id']; ?>" class="btn btn-default" data-bs-toggle="tooltip" title="编辑">
                          <i class="mdi mdi-pencil"></i>
                        </a>
                        <a href="api_list.php?action=delete&id=<?php echo $api['id']; ?>" 
                           class="btn btn-default" 
                           data-bs-toggle="tooltip" 
                           title="删除"
                           onclick="return confirm('确定要删除这个接口吗？此操作不可恢复，并将删除服务器上的对应文件。');">
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
          
          <div class="row mt-3">
            <div class="col-md-6">
              <div class="btn-group">
                <button type="button" class="btn btn-success btn-sm me-1" id="batch-activate"><i class="mdi mdi-check"></i> 启用</button>
                <button type="button" class="btn btn-warning btn-sm me-1" id="batch-deactivate"><i class="mdi mdi-block-helper"></i> 禁用</button>
                <button type="button" class="btn btn-danger btn-sm" id="batch-delete"><i class="mdi mdi-delete"></i> 删除</button>
              </div>
            </div>
            <div class="col-md-6">
              <nav class="float-end">
                <ul class="pagination">
                  <li class="page-item disabled"><span class="page-link">上一页</span></li>
                  <li class="page-item active"><span class="page-link">1</span></li>
                  <li class="page-item"><a class="page-link" href="#1">2</a></li>
                  <li class="page-item"><a class="page-link" href="#1">3</a></li>
                  <li class="page-item"><a class="page-link" href="#1">4</a></li>
                  <li class="page-item"><a class="page-link" href="#1">5</a></li>
                  <li class="page-item"><a class="page-link" href="#!">下一页</a></li>
                </ul>
              </nav>
            </div>
          </div>
          
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
$(document).ready(function() {
  $('[data-bs-toggle="tooltip"]').tooltip();
  
  $('#check-all').change(function() {
    $('.ids').prop('checked', $(this).prop('checked'));
  });
  
  $('.ids').change(function() {
    if (!$(this).prop('checked')) {
      $('#check-all').prop('checked', false);
    }
  });
 
  <?php if ($feedback_msg): ?>
  setTimeout(function() {
    $('.alert').fadeTo(500, 0).slideUp(500, function(){
      $(this).remove(); 
    });
  }, 3000);
  <?php endif; ?>
  
  $('#batch-activate').click(function() {
    batchAction('activate', '启用', 'normal');
  });
  
  $('#batch-deactivate').click(function() {
    batchAction('deactivate', '禁用', 'error');
  });
  
  $('#batch-delete').click(function() {
    batchAction('delete', '删除');
  });
  
  function batchAction(action, actionName, status = null) {
    const selectedIds = $('.ids:checked').map(function() {
      return $(this).val();
    }).get();
    
    if (selectedIds.length === 0) {
      alert('请至少选择一个接口');
      return false;
    }
    
    let confirmMsg = `确定要${actionName}选中的 ${selectedIds.length} 个接口吗？`;
    if (action === 'delete') {
      confirmMsg += '此操作不可恢复！';
    }
    
    if (confirm(confirmMsg)) {
      const form = $('<form>', {
        method: 'post',
        action: 'api_list.php'
      }).append(
        $('<input>', {
          type: 'hidden',
          name: 'batch_action',
          value: action
        })
      );
      
      if (status) {
        form.append(
          $('<input>', {
            type: 'hidden',
            name: 'status',
            value: status
          })
        );
      }
      
      $.each(selectedIds, function(index, value) {
        form.append(
          $('<input>', {
            type: 'hidden',
            name: 'ids[]',
            value: value
          })
        );
      });
      
      $('body').append(form);
      form.submit();
    }
  }
});
</script>
</body>
</html>