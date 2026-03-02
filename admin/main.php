<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (file_exists('../common/version.php')) { 
    require_once '../common/version.php'; 
}
if (!defined('SENLIN_CLIENT_VERSION')) {
    define('SENLIN_CLIENT_VERSION', '1.4.0');
}
if (!file_exists('../config.php')) { 
    die("出现错误！配置文件丢失，请先完成安装。"); 
}
require_once '../config.php';
if (!isset($_SESSION['admin_id'])) { 
    header('Location: login.php'); 
    exit; 
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') { 
    session_destroy(); 
    header('Location: login.php'); 
    exit; 
}
$username = htmlspecialchars($_SESSION['admin_username'] ?? '管理员');
$stats = [
    'today_calls' => 0,
    'yesterday_calls' => 0,
    'month_calls' => 0,
    'total_apis' => 0,
    'total_users' => 0,
    'total_calls_all' => 0,
    'pending_feedback' => 0,
    'success_orders' => 0,
    'failed_orders' => 0,
    'pending_orders' => 0,
    'today_income' => 0
];
$apis = [];
define('SITE_START_TIME', '2025-12-01 00:00:00');
$server_info = [
    'php_version' => PHP_VERSION,
    'server_software' => substr($_SERVER['SERVER_SOFTWARE'], 0, 25) . '...',
    'mysql_version' => 'N/A',
    'load_avg' => 'N/A'
];
$chart_data_json = '{"labels":[],"data":[]}';
$update_available = false;
$pdo = null;
$db_error = '';
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, 
        DB_USER, 
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stats['today_calls'] = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE DATE(request_time) = CURDATE()")->fetchColumn() ?: 0;
    $stats['yesterday_calls'] = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE DATE(request_time) = CURDATE() - INTERVAL 1 DAY")->fetchColumn() ?: 0;
    $stats['month_calls'] = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE MONTH(request_time) = MONTH(CURDATE()) AND YEAR(request_time) = YEAR(CURDATE())")->fetchColumn() ?: 0;
    $stats['total_apis'] = $pdo->query("SELECT COUNT(*) FROM sl_apis")->fetchColumn() ?: 0;
    $stmt_apis = $pdo->query("SELECT * FROM sl_apis");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM sl_users")->fetchColumn() ?: 0;
    $stats['total_calls_all'] = $pdo->query("SELECT SUM(total_calls) FROM sl_apis")->fetchColumn() ?: 0;
    $stats['pending_feedback'] = $pdo->query("SELECT COUNT(*) FROM sl_feedback WHERE status = 'pending'")->fetchColumn() ?: 0;
    $stats['success_orders'] = $pdo->query("SELECT COUNT(*) FROM sl_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $stats['failed_orders'] = $pdo->query("SELECT COUNT(*) FROM sl_orders WHERE status = 'failed' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $stats['pending_orders'] = $pdo->query("SELECT COUNT(*) FROM sl_orders WHERE status = 'pending'")->fetchColumn() ?: 0;
    $stats['today_income'] = $pdo->query("SELECT SUM(amount) FROM sl_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $server_info['mysql_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $chart_query = $pdo->query("
        SELECT DATE(request_time) as day, COUNT(*) as calls 
        FROM sl_api_logs 
        WHERE request_time >= CURDATE() - INTERVAL 30 DAY 
        GROUP BY day 
        ORDER BY day ASC
    ");    
    $chart_raw_data = $chart_query->fetchAll(PDO::FETCH_ASSOC);
    $chart_labels = []; 
    $chart_values = [];    
    $period = new DatePeriod(
        new DateTime('-29 days'), 
        new DateInterval('P1D'), 
        new DateTime('+1 day')
    );    
    $dates = [];
    foreach($period as $date) { 
        $dates[$date->format('Y-m-d')] = 0; 
    }    
    foreach($chart_raw_data as $row) { 
        $dates[$row['day']] = (int)$row['calls']; 
    }    
    foreach($dates as $day => $calls) { 
        $chart_labels[] = date('m-d', strtotime($day)); 
        $chart_values[] = $calls; 
    }    
    $chart_data_json = json_encode(['labels' => $chart_labels, 'data' => $chart_values]);
} catch (PDOException $e) { 
    $db_error = "数据库连接错误: " . $e->getMessage();
    error_log("[" . date('Y-m-d H:i:s') . "] 数据库错误: " . $e->getMessage() . "\n", 3, "../logs/db_errors.log");
    foreach ($stats as &$stat) {
        $stat = 0;
    }
    $server_info['mysql_version'] = '连接失败';
}
if (function_exists('sys_getloadavg')) { 
    $load = sys_getloadavg(); 
    $server_info['load_avg'] = round($load[0], 2); 
}
$current_page = basename($_SERVER['PHP_SELF']);
date_default_timezone_set('Asia/Shanghai');
$server_time = date('Y-m-d H:i:s');
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
<title>API管理系统 - 统计面板</title>
</head>
  
<body>
<div class="container-fluid">
    <?php if($db_error): ?>
    <div class="alert alert-danger mt-3">
        <?php echo $db_error; ?>
    </div>
    <?php endif; ?>
  <div class="row mt-3">
    <!-- 运行时间卡片 置顶 -->
    <div class="col-md-6 col-xl-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-timer-sand-empty fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers" id="run-time-count">0天0时0分0秒</span>
          </div>
          <div class="text-end">运行时间</div>
        </div>
      </div>
    </div>
    <!-- 原有统计卡片 -->
    <div class="col-md-6 col-xl-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-code-array fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo number_format($stats['today_calls']); ?></span>
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
            <span class="fs-4 scroll-numbers"><?php echo number_format($stats['yesterday_calls']); ?></span>
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
            <span class="fs-4 scroll-numbers"><?php echo number_format($stats['month_calls']); ?></span>
          </div>
          <div class="text-end">本月调用</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-sync fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo number_format($stats['total_calls_all']); ?></span>
          </div>
          <div class="text-end">总调用数</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-currency-cny fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo number_format($stats['today_income'] ?? 0, 2); ?></span>
          </div>
          <div class="text-end">今日收益(元)</div>
        </div>
      </div>
    </div>
  	<div class="col-md-6 col-xl-3 mt-3">
  	  <div class="card bg-danger text-white">
  	    <div class="card-body">
  	      <div class="d-flex justify-content-between">
  	        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-api fs-4"></i>
            </span>
  	        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['total_apis']); ?></span>
  	      </div>
  	      <div class="text-end">API总数</div>
  	    </div>
  	  </div>
  	</div>
  	<div class="col-md-6 col-xl-3 mt-3">
  	  <div class="card bg-success text-white">
  	    <div class="card-body">
  	      <div class="d-flex justify-content-between">
  	        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-account fs-4"></i>
            </span>
  	        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['total_users']); ?></span>
  	      </div>
  	      <div class="text-end">用户总数</div>
  	    </div>
  	  </div>
  	</div>
  	<div class="col-md-6 col-xl-3 mt-3">
  	  <div class="card bg-purple text-white">
  	    <div class="card-body">
  	      <div class="d-flex justify-content-between">
  	        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-thumb-up-outline fs-4"></i>
            </span>
  	        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['pending_feedback']); ?></span>
  	      </div>
  	      <div class="text-end">待处理反馈</div>
  	    </div>
  	  </div>
  	</div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-check-circle fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo number_format($stats['success_orders'] ?? 0); ?></span>
          </div>
          <div class="text-end">成功订单</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-danger text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-close-circle fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo number_format($stats['failed_orders'] ?? 0); ?></span>
          </div>
          <div class="text-end">失败订单</div>
        </div>
      </div>
    </div>
    <!-- 剩余新增卡片 -->
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-check-circle-outline fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis ?? [], function($api) { return $api['status'] === 'normal'; })); ?></span>
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
            <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis ?? [], function($api) { return $api['status'] === 'maintenance'; })); ?></span>
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
            <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis ?? [], function($api) { return $api['status'] === 'error'; })); ?></span>
          </div>
          <div class="text-end">异常API</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3 mt-3">
      <div class="card bg-secondary text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
              <i class="mdi mdi-alert-circle-outline fs-4"></i>
            </span>
            <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis ?? [], function($api) { return $api['status'] === 'deprecated'; })); ?></span>
          </div>
          <div class="text-end">失效API</div>
        </div>
      </div>
    </div>
  </div>
  <div class="row mt-3">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">API调用统计 (近30天)</div>
                </header>
                <div class="card-body">
                    <?php if(empty($chart_labels)): ?>
                        <div class="alert alert-info">
                            暂无API调用数据或数据库连接错误
                        </div>
                    <?php else: ?>
                        <div class="chart-container" style="position: relative; height:40vh; width:100%">
                            <canvas id="apiCallsChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<div class="row mt-3">    
  <div class="col-lg-12">
    <div class="card">
      <header class="card-header">
        <div class="card-title">系统信息</div>
      </header>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>项目</th>
                <th>值</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sysInfo = [];
              
              $sysInfo['系统版本'] = 'v'.SENLIN_CLIENT_VERSION;
              $sysInfo['PHP版本'] = phpversion();
              
              if (isset($_SERVER['SERVER_SOFTWARE'])) {
                  $sysInfo['服务器软件'] = $_SERVER['SERVER_SOFTWARE'];
              } else {
                  $sysInfo['服务器软件'] = '未知';
              }
              
              $sysInfo['MySQL版本'] = $server_info['mysql_version'] ?? '未知';
              
              if (function_exists('sys_getloadavg')) {
                  $load = @sys_getloadavg();
                  $sysInfo['系统负载'] = $load ? round($load[0], 2) : '0.00';
              } else {
                  $sysInfo['系统负载'] = '不支持';
              }
              
              if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                  $sysInfo['操作系统'] = 'Windows';
              } elseif (function_exists('php_uname')) {
                  $sysInfo['操作系统'] = php_uname('s').' '.php_uname('r');
              } else {
                  $sysInfo['操作系统'] = PHP_OS;
              }
              
              $cpuInfo = '未知';
              $cpuCount = 0;
              $cpuModel = '未知';
              
              if (function_exists('shell_exec') && is_callable('shell_exec')) {
                  if (stripos(PHP_OS, 'linux') !== false) {
                      $cpuinfo = @shell_exec('cat /proc/cpuinfo 2>/dev/null || lscpu 2>/dev/null');
                      if ($cpuinfo) {
                          if (preg_match_all('/processor\s*:\s*\d+/i', $cpuinfo, $matches)) {
                              $cpuCount = count($matches[0]);
                          }
                          if (preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $matches)) {
                              $cpuModel = trim($matches[1]);
                          } elseif (preg_match('/Model name:\s*(.+)/i', $cpuinfo, $matches)) {
                              $cpuModel = trim($matches[1]);
                          }
                          
                          if ($cpuCount > 0 && $cpuModel !== '未知') {
                              $cpuInfo = "{$cpuCount}核 - {$cpuModel}";
                          } elseif ($cpuCount > 0) {
                              $cpuInfo = "{$cpuCount}核";
                          } else {
                              $cpuInfo = $cpuModel;
                          }
                      }
                  } elseif (stripos(PHP_OS, 'win') !== false) {
                      $cpuinfo = @shell_exec('wmic cpu get name /value 2>nul');
                      if ($cpuinfo && preg_match('/Name=(.+)/i', $cpuinfo, $matches)) {
                          $cpuModel = trim($matches[1]);
                          
                          $cpuCountStr = @shell_exec('wmic cpu get NumberOfCores /value 2>nul');
                          if ($cpuCountStr && preg_match('/NumberOfCores=(\d+)/i', $cpuCountStr, $matches)) {
                              $cpuCount = intval($matches[1]);
                          } else {
                              $cpuCount = 1;
                          }
                          
                          if ($cpuCount > 0) {
                              $cpuInfo = "{$cpuCount}核 - {$cpuModel}";
                          } else {
                              $cpuInfo = $cpuModel;
                          }
                      }
                  }
              }
              
              if ($cpuInfo === '未知') {
                  $cpuInfo = @exec('nproc 2>/dev/null || echo 1');
                  if ($cpuInfo) {
                      $cpuInfo = "{$cpuInfo}核";
                  }
              }
              
              $sysInfo['CPU信息'] = $cpuInfo;
              
              $memoryInfo = '未知';
              
              if (function_exists('shell_exec') && is_callable('shell_exec')) {
                  if (stripos(PHP_OS, 'linux') !== false) {
                      $meminfo = @shell_exec('free -m 2>/dev/null');
                      if ($meminfo && preg_match('/Mem:\s*(\d+)\s+(\d+)/', $meminfo, $matches)) {
                          $total = intval($matches[1]);
                          $used = intval($matches[2]);
                          $percent = $total > 0 ? round(($used/$total)*100) : 0;
                          $memoryInfo = "{$used}MB / {$total}MB ({$percent}%)";
                      } else {
                          $meminfo = @shell_exec('cat /proc/meminfo 2>/dev/null');
                          if ($meminfo) {
                              if (preg_match('/MemTotal:\s*(\d+)/', $meminfo, $matches)) {
                                  $total = round($matches[1]/1024);
                              }
                              if (preg_match('/MemAvailable:\s*(\d+)/', $meminfo, $matches)) {
                                  $available = round($matches[1]/1024);
                                  $used = $total - $available;
                                  $percent = $total > 0 ? round(($used/$total)*100) : 0;
                                  $memoryInfo = "{$used}MB / {$total}MB ({$percent}%)";
                              }
                          }
                      }
                  } elseif (stripos(PHP_OS, 'win') !== false) {
                      $memory = @shell_exec('wmic OS get TotalVisibleMemorySize,FreePhysicalMemory /value 2>nul');
                      if ($memory) {
                          $total = 0;
                          $free = 0;
                          
                          if (preg_match('/TotalVisibleMemorySize=(\d+)/', $memory, $matches)) {
                              $total = round($matches[1]/1024);
                          }
                          if (preg_match('/FreePhysicalMemory=(\d+)/', $memory, $matches)) {
                              $free = round($matches[1]/1024);
                              $used = $total - $free;
                              $percent = $total > 0 ? round(($used/$total)*100) : 0;
                              $memoryInfo = "{$used}MB / {$total}MB ({$percent}%)";
                          } elseif ($total > 0) {
                              $memoryInfo = "总共 {$total}MB";
                          }
                      }
                  }
              }
              
              if ($memoryInfo === '未知') {
                  if (function_exists('memory_get_usage') && function_exists('memory_get_peak_usage')) {
                      $used = round(memory_get_usage(true)/(1024*1024), 2);
                      $peak = round(memory_get_peak_usage(true)/(1024*1024), 2);
                      $memoryInfo = "当前: {$used}MB, 峰值: {$peak}MB";
                  }
              }
              
              $sysInfo['内存使用'] = $memoryInfo;
              
              $diskInfo = '无法获取';
              
              if (function_exists('shell_exec') && is_callable('shell_exec') && stripos(PHP_OS, 'linux') !== false) {
                  $df_output = @shell_exec('df -h / 2>/dev/null | tail -1');
                  if ($df_output) {
                      $df_output = trim($df_output);
                      if (preg_match('/\S+\s+(\d+\.?\d*)([KMGTP]?)\s+(\d+\.?\d*)([KMGTP]?)\s+(\d+\.?\d*)([KMGTP]?)\s+(\d+)%\s+/', $df_output, $matches)) {
                          $total_num = floatval($matches[1]);
                          $total_unit = $matches[2];
                          $used_num = floatval($matches[3]);
                          $used_unit = $matches[4];
                          $percent = intval($matches[7]);
                          
                          $unit_multipliers = [
                              'K' => 1/(1024*1024),
                              'M' => 1/1024,
                              'G' => 1,
                              'T' => 1024,
                              'P' => 1024*1024
                          ];
                          
                          $total_gb = $total_num * ($unit_multipliers[$total_unit] ?? 1);
                          $used_gb = $used_num * ($unit_multipliers[$used_unit] ?? 1);
                          
                          $total_gb = round($total_gb, 2);
                          $used_gb = round($used_gb, 2);
                          
                          if ($total_gb > 0 && $used_gb >= 0 && $percent >= 0 && $percent <= 100) {
                              $diskInfo = "{$used_gb}GB / {$total_gb}GB ({$percent}%)";
                          }
                      }
                  }
              }
              
              if ($diskInfo === '无法获取' && function_exists('shell_exec') && is_callable('shell_exec') && stripos(PHP_OS, 'linux') !== false) {
                  $df_output = @shell_exec('df -B1 / 2>/dev/null | tail -1');
                  if ($df_output) {
                      if (preg_match('/\S+\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+/', $df_output, $matches)) {
                          $total_bytes = floatval($matches[1]);
                          $used_bytes = floatval($matches[2]);
                          $available_bytes = floatval($matches[3]);
                          $percent = intval($matches[4]);
                          
                          $total_gb = round($total_bytes / (1024*1024*1024), 2);
                          $used_gb = round($used_bytes / (1024*1024*1024), 2);
                          
                          if ($total_gb > 0 && $used_gb >= 0 && $percent >= 0 && $percent <= 100) {
                              $diskInfo = "{$used_gb}GB / {$total_gb}GB ({$percent}%)";
                          }
                      }
                  }
              }
              
              if ($diskInfo === '无法获取' && function_exists('disk_total_space') && function_exists('disk_free_space')) {
                  $path = '/';
                  $total_bytes = @disk_total_space($path);
                  $free_bytes = @disk_free_space($path);
                  
                  if ($total_bytes !== false && $free_bytes !== false) {
                      $used_bytes = $total_bytes - $free_bytes;
                      $total_gb = round($total_bytes / (1024*1024*1024), 2);
                      $used_gb = round($used_bytes / (1024*1024*1024), 2);
                      $percent = $total_bytes > 0 ? round(($used_bytes / $total_bytes) * 100) : 0;
                      
                      if ($total_gb > 0 && $used_gb >= 0 && $percent >= 0 && $percent <= 100) {
                          $diskInfo = "{$used_gb}GB / {$total_gb}GB ({$percent}%)";
                      }
                  }
              }
              
              if ($diskInfo === '无法获取' && stripos(PHP_OS, 'win') !== false) {
                  if (function_exists('shell_exec') && is_callable('shell_exec')) {
                      $disk_output = @shell_exec('wmic logicaldisk where "DeviceID=\'C:\'" get Size,FreeSpace 2>nul');
                      if ($disk_output && preg_match('/\s+(\d+)\s+(\d+)/', $disk_output, $matches)) {
                          $total_bytes = floatval($matches[1]);
                          $free_bytes = floatval($matches[2]);
                          
                          if ($total_bytes > 0 && $free_bytes >= 0) {
                              $used_bytes = $total_bytes - $free_bytes;
                              $total_gb = round($total_bytes / (1024*1024*1024), 2);
                              $used_gb = round($used_bytes / (1024*1024*1024), 2);
                              $percent = round(($used_bytes / $total_bytes) * 100);
                              
                              if ($total_gb > 0 && $used_gb >= 0 && $percent >= 0 && $percent <= 100) {
                                  $diskInfo = "{$used_gb}GB / {$total_gb}GB ({$percent}%)";
                              }
                          }
                      }
                  }
                  
                  if ($diskInfo === '无法获取' && function_exists('disk_total_space') && function_exists('disk_free_space')) {
                      $path = 'C:\\';
                      $total_bytes = @disk_total_space($path);
                      $free_bytes = @disk_free_space($path);
                      
                      if ($total_bytes !== false && $free_bytes !== false) {
                          $used_bytes = $total_bytes - $free_bytes;
                          $total_gb = round($total_bytes / (1024*1024*1024), 2);
                          $used_gb = round($used_bytes / (1024*1024*1024), 2);
                          $percent = $total_bytes > 0 ? round(($used_bytes / $total_bytes) * 100) : 0;
                          
                          if ($total_gb > 0 && $used_gb >= 0 && $percent >= 0 && $percent <= 100) {
                              $diskInfo = "{$used_gb}GB / {$total_gb}GB ({$percent}%)";
                          }
                      }
                  }
              }
              
              $sysInfo['磁盘空间'] = $diskInfo;
              
              $sysInfo['PHP内存限制'] = ini_get('memory_limit') ?: '未知';
              $sysInfo['PHP最大执行时间'] = (ini_get('max_execution_time') ?: '未知') . '秒';
              $sysInfo['PHP上传限制'] = ini_get('upload_max_filesize') ?: '未知';
              
              $sysInfo['服务器IP'] = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? '未知');
              $sysInfo['客户端IP'] = $_SERVER['REMOTE_ADDR'] ?? '未知';
              
              $publicIP = '未知';
              if (function_exists('curl_init')) {
                  $apis = [
                      'https://api.ipify.org',
                      'https://icanhazip.com',
                      'https://ipinfo.io/ip',
                      'https://checkip.amazonaws.com'
                  ];
                  
                  foreach ($apis as $api) {
                      $ch = curl_init();
                      curl_setopt_array($ch, [
                          CURLOPT_URL => $api,
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_TIMEOUT => 2,
                          CURLOPT_SSL_VERIFYPEER => false,
                          CURLOPT_SSL_VERIFYHOST => false,
                          CURLOPT_USERAGENT => 'Mozilla/5.0'
                      ]);
                      
                      $response = @curl_exec($ch);
                      if (!curl_errno($ch) && $response) {
                          $ip = trim($response);
                          if (filter_var($ip, FILTER_VALIDATE_IP)) {
                              $publicIP = $ip;
                              curl_close($ch);
                              break;
                          }
                      }
                      curl_close($ch);
                  }
              } elseif (function_exists('file_get_contents')) {
                  $context = stream_context_create([
                      'http' => [
                          'timeout' => 2,
                          'header' => "User-Agent: Mozilla/5.0\r\n"
                      ],
                      'ssl' => [
                          'verify_peer' => false,
                          'verify_peer_name' => false
                      ]
                  ]);
                  
                  $apis = [
                      'https://api.ipify.org',
                      'https://icanhazip.com'
                  ];
                  
                  foreach ($apis as $api) {
                      $response = @file_get_contents($api, false, $context);
                      if ($response) {
                          $ip = trim($response);
                          if (filter_var($ip, FILTER_VALIDATE_IP)) {
                              $publicIP = $ip;
                              break;
                          }
                      }
                  }
              }
              
              $sysInfo['公网IP'] = $publicIP;
              if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                  $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
                  $sysInfo['页面生成时间'] = round($executionTime * 1000, 2) . 'ms';
              }
              
              $sysInfo['服务器时间(北京时间)'] = $server_time;
              
              foreach ($sysInfo as $name => $value) {
                  echo "<tr><td>{$name}</td><td>{$value}</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
  <div class="row mt-3">
  	<div class="col-lg-12">
  	  <div class="card">
  	    <header class="card-header">
  	      <div class="card-title">项目信息</div>
  	    </header>
  		<div class="card-body">
  		  <div class="table-responsive">
  		    <table class="table table-hover">
  		      <thead>
  		        <tr>
  		          <th>#</th>
  		          <th>项目名称</th>
  		          <th>开始日期</th>
  		          <th>截止日期</th>
  		          <th>状态</th>
  		          <th>进度</th>
  		        </tr>
  		      </thead>
			  <tbody>
  			    <tr>
  			      <td>1</td>
  			      <td>细节优化</td>
  			      <td>1/10/2026</td>
  			      <td>1/10/2026</td>
  			      <td><span class="badge bg-success">已完成</span></td>
  			      <td>
  			        <div class="progress progress-xs">
  			          <div class="progress-bar progress-bar-striped bg-success" style="width: 100%;"></div>
  			        </div>
  			      </td>
  			    </tr>
  			    <tr>
  			      <td>2</td>
  			      <td>优化前台后台功能</td>
  			      <td>1/20/2026</td>
  			      <td>2/30/2026</td>
  			      <td><span class="badge bg-success">已完成</span></td>
  			      <td>
  			        <div class="progress progress-xs">
  			          <div class="progress-bar progress-bar-striped bg-success" style="width: 100%;"></div>
  			        </div>
  			      </td>
  			    </tr>
  			  </tbody>
            </table>
  	      </div>
  	    </div>
  	  </div>
    </div>
  </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/chart.min.js"></script>
<script type="text/javascript" src="../assets/js/scroll-numbers.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('apiCallsChart');
    if (ctx) {
        try {
            const chartData = <?php echo $chart_data_json; ?>;
            const sum = chartData.data.reduce((acc, val) => acc + val, 0);
            const average = Math.round(sum / chartData.data.length) || 0;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'API调用量',
                        data: chartData.data,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#4e73df',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('图表初始化错误:', e);
            if (ctx.parentNode) {
                ctx.parentNode.innerHTML = '<div class="alert alert-danger">图表加载失败: ' + e.message + '</div>';
            }
        }
    }
});
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
</body>
</html>