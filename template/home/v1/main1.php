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
$homeTemplate = TemplateManager::getActiveHomeTemplate();
$homeTemplateBaseUrl = "/template/home/{$homeTemplate}/";
$userTemplate = TemplateManager::getActiveUserTemplate();
$userTemplateBaseUrl = "/template/User/{$userTemplate}/";
$apis = [];
define('SITE_START_TIME', '2025-12-01 00:00:00');
$today_calls = 0;
$yesterday_calls = 0;
$month_calls = 0;
$total_calls_all = 0;
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_apis = $pdo->query("SELECT * FROM sl_apis ORDER BY id DESC");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
    $today_calls = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE DATE(request_time) = CURDATE()")->fetchColumn() ?: 0;
    $yesterday_calls = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE DATE(request_time) = CURDATE() - INTERVAL 1 DAY")->fetchColumn() ?: 0;
    $month_calls = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE MONTH(request_time) = MONTH(CURDATE()) AND YEAR(request_time) = YEAR(CURDATE())")->fetchColumn() ?: 0;
    $total_calls_all = array_sum(array_column($apis, 'total_calls') ?: [0]);
} catch (PDOException $e) {
}
$announcement = null;
try {
    $stmt_announcement = $pdo->query("SELECT * FROM sl_announcements WHERE is_active = 1 ORDER BY created DESC LIMIT 1");
    $announcement = $stmt_announcement->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}
function getStatusBadge($status) {
    switch ($status) {
        case 'normal': return '<span class="badge bg-success">正常</span>';
        case 'error': return '<span class="badge bg-danger">异常</span>';
        case 'maintenance': return '<span class="badge bg-warning">维护</span>';
        case 'deprecated': return '<span class="badge bg-secondary text-white">失效</span>';
        default: return '<span class="badge bg-secondary text-white">未知</span>';
    }
}
function getVisibilityBadge($visibility, $is_billable) {
    if ($is_billable) return '<span class="badge bg-purple me-2"><i class="mdi mdi-cash-multiple me-1"></i>计费调用</span>';
    if ($visibility === 'private') return '<span class="badge bg-info me-2"><i class="mdi mdi-key me-1"></i>密钥调用</span>';
    return '';
}
function getCallCountStyle($count) {
    $count = intval($count);
    if ($count > 500000) return ['color' => 'text-danger', 'icon' => '<i class="mdi mdi-fire text-danger flame-icon"></i>'];
    elseif ($count > 100000) return ['color' => 'text-danger', 'icon' => '<i class="mdi mdi-fire text-danger flame-icon"></i>'];
    elseif ($count > 50000) return ['color' => 'text-warning', 'icon' => '<i class="mdi mdi-fire text-warning flame-icon"></i>'];
    elseif ($count > 10000) return ['color' => 'text-warning', 'icon' => '<i class="mdi mdi-fire text-warning flame-icon"></i>'];
    elseif ($count > 1000) return ['color' => 'text-success', 'icon' => ''];
    return ['color' => 'text-muted', 'icon' => ''];
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<meta name="keywords" content="API,API大厅,接口服务">
<meta name="description" content="API服务平台接口管理中心">
<title>API大厅 - <?php echo htmlspecialchars($site_name ?? 'API服务平台'); ?></title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
<style>
  .api-card {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    height: 100%;
  }
  .api-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  }
  .announcement-bar {
    background-color: #e9f5ff;
    border-left: 4px solid #4a69bd;
  }
  .flame-icon {
    animation: flame-flicker 1s infinite alternate;
  }
  @keyframes flame-flicker {
    0% { opacity: 0.8; transform: scale(1); }
    100% { opacity: 1; transform: scale(1.1); }
  }
  .api-search-box {
    border-radius: 50px;
    padding-left: 40px;
  }
 .search-icon {
  position: absolute;
  z-index: 10;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
  pointer-events: none;
}
.api-search-box {
  border-radius: 50px;
  padding-left: 35px;
  height: 40px;
}
#run-time-count {
    font-weight: 600;
}
.avatar-box {
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
<style>
.floating-sidebar-btn {
    position: fixed;
    right: 30px;
    bottom: 30px;
    z-index: 999;
}
.floating-sidebar-btn .btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #0d6efd;
    border: none;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.35);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.floating-sidebar-btn .btn:hover {
    background-color: #0b5ed7;
    box-shadow: 0 6px 16px rgba(13, 110, 253, 0.5);
    transform: translateY(-2px);
}
.floating-sidebar-btn .btn i {
    font-size: 20px;
}
.offcanvas-start {
    width: 300px;
}
.search-box {
    border-bottom: 1px solid #dee2e6;
    padding: 0.5rem;
}
.sidebar-categories {
    height: calc(100vh - 120px);
    overflow-y: auto;
    padding: 0;
    margin: 0;
    list-style: none;
}
.sidebar-category {
    border-bottom: 1px solid #f1f1f1;
}
.sidebar-category-title {
    padding: 12px 16px;
    background-color: #f8f9fa;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 0;
}
.sidebar-category-title:hover {
    background-color: #e9ecef;
}
.sidebar-arrow {
    transition: transform 0.2s;
}
.sidebar-arrow.rotate {
    transform: rotate(90deg);
}
.sidebar-category-content {
    display: none;
    background-color: #fff;
}
.sidebar-category-content.show {
    display: block;
}
.sidebar-api-item {
    padding: 10px 16px 10px 30px;
    cursor: pointer;
    border-left: 3px solid transparent;
}
.sidebar-api-item:hover {
    background-color: #f8f9fa;
    border-left-color: #4a69bd;
}
.sidebar-api-name {
    font-size: 14px;
    margin-bottom: 2px;
}
.sidebar-api-endpoint {
    font-size: 12px;
    color: #6c757d;
}
.no-sidebar-category {
    padding: 16px;
    color: #6c757d;
    text-align: center;
}
</style>
</head>
  
<body>
<div class="container-fluid py-4">
<?php if ($announcement): ?>
<div class="announcement-bar alert mb-4" style="background-color: #e9f5ff; border-left: 4px solid #4a69bd; color: #333;">
  <div class="d-flex align-items-center">
    <i class="mdi mdi-bullhorn-outline fs-4 me-3 text-primary"></i>
    <div>
      <h5 class="mb-1 text-dark"><?php echo htmlspecialchars($announcement['title']); ?></h5>
      <p class="mb-0 text-dark"><?php echo htmlspecialchars($announcement['content']); ?></p>
    </div>
  </div>
</div>
<?php endif; ?>
  <div class="row mb-4">
    <div class="col-md-6 col-xl-3">
      <div class="card bg-dark text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-timer-sand-empty fs-4"></i>
            </span>
            <span class="fs-4" id="run-time-count">0天0时0分0秒</span>
          </div>
          <div class="text-end">运行时间</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-code-array fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo number_format($today_calls); ?></span>
          </div>
          <div class="text-end">今日调用</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-code-array fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo number_format($yesterday_calls); ?></span>
          </div>
          <div class="text-end">昨日调用</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-calendar-month fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo number_format($month_calls); ?></span>
          </div>
          <div class="text-end">本月调用</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-api fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo count($apis); ?></span>
          </div>
          <div class="text-end">API总数</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-pink text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-arrow-up-bold fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo $total_calls_all; ?></span>
          </div>
          <div class="text-end">总调用量</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-check-circle-outline fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis, function($api) { return $api['status'] === 'normal'; })); ?></span>
          </div>
          <div class="text-end">可用API</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-wrench-outline fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis, function($api) { return $api['status'] === 'maintenance'; })); ?></span>
          </div>
          <div class="text-end">维护API</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-danger text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-alert-circle-outline fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis, function($api) { return $api['status'] === 'error'; })); ?></span>
          </div>
          <div class="text-end">异常API</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-secondary text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center">
              <i class="mdi mdi-alert-circle-outline fs-4" style="color: #ffffff !important;"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis, function($api) { return $api['status'] === 'deprecated'; })); ?></span>
          </div>
          <div class="text-end">失效API</div>
        </div>
      </div>
    </div>
  </div>
      
<div class="card mb-4">
  <div class="card-body p-3">
    <div class="position-relative">
      <i class="mdi mdi-magnify search-icon fs-5"></i>
      <input type="text" id="api-search-input" class="form-control api-search-box ps-4" 
             placeholder="搜索API接口名称或描述...">
    </div>
  </div>
</div>
  <div class="row" id="api-grid">
    <?php foreach ($apis as $api): 
      $style = getCallCountStyle($api['total_calls']);
    ?>
    <div class="col-md-6 col-lg-4 mb-4 api-card-item" 
     data-name="<?php echo htmlspecialchars(strtolower($api['name'])); ?>"
     data-desc="<?php echo htmlspecialchars(strtolower($api['description'])); ?>"
     data-category="<?php echo $api['category_id'] ?? '0'; ?>">
    <div class="card h-100 api-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h4 class="card-title mb-0"><?php echo htmlspecialchars($api['name']); ?></h4>
                <?php echo getStatusBadge($api['status']); ?>
            </div>
            
            <?php echo getVisibilityBadge($api['visibility'], $api['is_billable']); ?>
            
            <?php if (!empty($api['category_id'])): ?>
                <?php 
                $stmtCat = $pdo->prepare("SELECT name FROM sl_api_categories WHERE id = ?");
                $stmtCat->execute([$api['category_id']]);
                $categoryName = $stmtCat->fetchColumn();
                ?>
                <span class="badge bg-light text-dark mb-2">
                    <i class="mdi mdi-tag-outline me-1"></i>
                    <?php echo htmlspecialchars($categoryName); ?>
                </span>
            <?php endif; ?>
            
            <p class="card-text text-muted mb-3"><?php echo htmlspecialchars($api['description']); ?></p>
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="me-3 <?php echo $style['color']; ?>">
                        <i class="mdi mdi-counter me-1"></i>
                        <?php echo number_format($api['total_calls']); ?>
                        <?php echo $style['icon']; ?>
                    </span>
                    <span class="text-muted">
                        <i class="mdi mdi-format-list-bulleted-type me-1"></i>
                        <?php echo strtoupper(explode('/', $api['response_format'])[1] ?? 'TEXT'); ?>
                    </span>
                </div>
                <a href="<?= $homeTemplateBaseUrl ?>doc.php?id=<?php echo $api['id']; ?>" class="btn btn-sm btn-primary">
                    查看详情
                </a>
            </div>
        </div>
    </div>
</div>
    <?php endforeach; ?>
  </div>
</div>
<div class="offcanvas offcanvas-start" tabindex="-1" id="apiSidebar" aria-labelledby="apiSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="apiSidebarLabel">API分类导航</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="search-box">
            <div class="position-relative">
                <i class="mdi mdi-magnify search-icon"></i>
                <input type="text" id="sidebar-search" class="form-control form-control-sm ps-4" placeholder="搜索接口...">
            </div>
        </div>
        <ul class="sidebar-categories" id="sidebar-categories"></ul>
    </div>
</div>
<div class="floating-sidebar-btn">
    <button class="btn btn-primary rounded-circle p-3" data-bs-toggle="offcanvas" data-bs-target="#apiSidebar">
        <i class="mdi mdi-menu"></i>
    </button>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/scroll-numbers.js"></script>
<script>
function filterAPIs() {
  const input = document.getElementById('api-search-input');
  const filter = input.value.toLowerCase().trim();
  const cards = document.querySelectorAll('.api-card-item');
  if (!filter) {
    cards.forEach(card => card.style.display = "block");
    return;
  }
  cards.forEach(card => {
    const name = card.dataset.name || '';
    const desc = card.dataset.desc || '';
    card.style.display = (name.includes(filter) || desc.includes(filter)) ? "block" : "none";
  });
}
document.getElementById('api-search-input').addEventListener('input', filterAPIs);
</script>
<script>
(function() {
    const startTime = new Date("<?php echo SITE_START_TIME; ?>").getTime();
    function updateRunTime() {
        const now = new Date().getTime();
        const diff = now - startTime;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        document.getElementById("run-time-count").innerText = `${days}天${hours}时${minutes}分${seconds}秒`;
    }
    updateRunTime();
    setInterval(updateRunTime, 1000);
})();
</script>
<script>
window.onload = function() {
    loadSidebarCategories();
    document.getElementById('sidebar-search').addEventListener('input', filterSidebarApi);
}
function loadSidebarCategories() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_api_list.php?with_categories=1', true);
    xhr.responseType = 'json';
    xhr.onload = function() {
        if (this.status === 200 && this.response) {
            const data = this.response;
            const container = document.getElementById('sidebar-categories');
            container.innerHTML = '';
            if (data.categories && data.categories.length > 0) {
                data.categories.forEach(cate => {
                    const cateLi = document.createElement('li');
                    cateLi.className = 'sidebar-category';
                    
                    const cateTitle = document.createElement('div');
                    cateTitle.className = 'sidebar-category-title';
                    cateTitle.innerHTML = `${cate.name} (${cate.api_count || 0}) <i class="mdi mdi-chevron-right sidebar-arrow"></i>`;
                    
                    const cateContent = document.createElement('div');
                    cateContent.className = 'sidebar-category-content';
                    cateContent.id = `cate-${cate.id}`;
                    if (cate.apis && cate.apis.length > 0) {
                        cate.apis.forEach(api => {
                            const apiItem = document.createElement('div');
                            apiItem.className = 'sidebar-api-item';
                            apiItem.dataset.id = api.id;
                            apiItem.innerHTML = `<div class="sidebar-api-name">${api.name}</div><div class="sidebar-api-endpoint">${api.endpoint}</div>`;
                            apiItem.onclick = function() {
                                window.location.href = "<?= $homeTemplateBaseUrl ?>doc.php?id=" + this.dataset.id;
                            };
                            cateContent.appendChild(apiItem);
                        });
                    } else {
                        cateContent.innerHTML = '<div class="p-2 text-muted text-center">暂无API</div>';
                    }
                    cateLi.appendChild(cateTitle);
                    cateLi.appendChild(cateContent);
                    container.appendChild(cateLi);
                    cateTitle.onclick = function() {
                        const arrow = this.querySelector('.sidebar-arrow');
                        const content = this.nextElementSibling;
                        arrow.classList.toggle('rotate');
                        content.classList.toggle('show');
                    };
                });
            }
            if (data.uncategorized && data.uncategorized.length > 0) {
                const unCateLi = document.createElement('li');
                unCateLi.className = 'sidebar-category';
                
                const unCateTitle = document.createElement('div');
                unCateTitle.className = 'sidebar-category-title';
                unCateTitle.innerHTML = `未分类API (${data.uncategorized.length}) <i class="mdi mdi-chevron-right sidebar-arrow rotate"></i>`;
                
                const unCateContent = document.createElement('div');
                unCateContent.className = 'sidebar-category-content show';
                unCateContent.id = 'cate-0';
                data.uncategorized.forEach(api => {
                    const apiItem = document.createElement('div');
                    apiItem.className = 'sidebar-api-item';
                    apiItem.dataset.id = api.id;
                    apiItem.innerHTML = `<div class="sidebar-api-name">${api.name}</div><div class="sidebar-api-endpoint">${api.endpoint}</div>`;
                    apiItem.onclick = function() {
                        window.location.href = "<?= $homeTemplateBaseUrl ?>doc.php?id=" + this.dataset.id;
                    };
                    unCateContent.appendChild(apiItem);
                });
                unCateLi.appendChild(unCateTitle);
                unCateLi.appendChild(unCateContent);
                container.appendChild(unCateLi);
                unCateTitle.onclick = function() {
                    const arrow = this.querySelector('.sidebar-arrow');
                    const content = this.nextElementSibling;
                    arrow.classList.toggle('rotate');
                    content.classList.toggle('show');
                };
            }
            if ((!data.categories || data.categories.length === 0) && (!data.uncategorized || data.uncategorized.length === 0)) {
                container.innerHTML = '<li class="no-sidebar-category">暂无分类数据</li>';
            }
        }
    }
    xhr.onerror = function() {
        document.getElementById('sidebar-categories').innerHTML = '<li class="p-3 text-danger">加载分类失败，请刷新</li>';
    }
    xhr.send();
}
function filterSidebarApi() {
    const searchTerm = this.value.toLowerCase().trim();
    const allApiItems = document.querySelectorAll('.sidebar-api-item');
    const allCateLi = document.querySelectorAll('.sidebar-category');
    if (!searchTerm) {
        allApiItems.forEach(item => item.style.display = 'block');
        allCateLi.forEach(li => li.style.display = 'block');
        return;
    }
    allApiItems.forEach(item => item.style.display = 'none');
    allCateLi.forEach(li => li.style.display = 'none');
    allApiItems.forEach(item => {
        const name = item.querySelector('.sidebar-api-name').textContent.toLowerCase();
        const endpoint = item.querySelector('.sidebar-api-endpoint').textContent.toLowerCase();
        if (name.includes(searchTerm) || endpoint.includes(searchTerm)) {
            item.style.display = 'block';
            const cateLi = item.closest('.sidebar-category');
            cateLi.style.display = 'block';
            const cateContent = item.closest('.sidebar-category-content');
            const arrow = cateLi.querySelector('.sidebar-arrow');
            cateContent.classList.add('show');
            arrow.classList.add('rotate');
        }
    });
}
</script>
</body>
</html>