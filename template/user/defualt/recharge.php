<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');

// 1. 严格权限与参数校验（增强安全性）
if (!isset($_SESSION['user_id'])) {
    $_SESSION['feedback_msg'] = '请先登录后再进行充值操作';
    $_SESSION['feedback_type'] = 'error';
    header('Location: login.php');
    exit;
}

// 校验核心参数，缺失则跳转并提示
$requiredParams = ['plan_id', 'payment_method'];
foreach ($requiredParams as $param) {
    if (!isset($_POST[$param]) || empty(trim($_POST[$param]))) {
        $_SESSION['feedback_msg'] = '充值参数不完整，请重新选择充值方案';
        $_SESSION['feedback_type'] = 'error';
        header('Location: index.php');
        exit;
    }
}

// 2. 路径与配置加载（保持统一）
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失，无法完成充值");
}
require_once ROOT_PATH . 'config.php';

// 3. 变量过滤与初始化（防注入+规范格式）
$plan_id = intval($_POST['plan_id']);
$payment_method = trim($_POST['payment_method']);
$user_id = $_SESSION['user_id'];
$allowedPayMethods = ['alipay', 'wxpay', 'qqpay']; // 允许的支付方式白名单
$site_name = $settings['site_name'] ?? '白子API';

try {
    // 4. 数据库连接与配置校验
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 校验支付网关配置
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings WHERE setting_key IN ('epay_pid', 'epay_key', 'epay_url')");
    $epay_config = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($epay_config['epay_pid']) || empty($epay_config['epay_key']) || empty($epay_config['epay_url'])) {
        throw new Exception("支付网关未配置，请联系管理员");
    }

    // 校验支付方式是否支持
    if (!in_array($payment_method, $allowedPayMethods)) {
        throw new Exception("所选支付方式暂不支持");
    }

    // 校验充值方案有效性（双重校验：ID+状态）
    $stmt_plan = $pdo->prepare("SELECT * FROM sl_billing_plans WHERE id = ? AND is_active = 1 AND price > 0");
    $stmt_plan->execute([$plan_id]);
    $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        throw new Exception("所选充值方案无效、已下架或金额异常");
    }

    // 5. 订单创建（规范订单号+防重复）
    $order_id = date('YmdHis') . mt_rand(10000, 99999); // 订单号：时间戳+随机数
    $amount = number_format($plan['price'], 2); // 金额保留2位小数，符合支付规范

    // 插入订单记录（添加创建时间，便于后续排查）
    $sql = "INSERT INTO sl_orders (order_id, user_id, plan_id, amount, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
    $stmt_insert = $pdo->prepare($sql);
    $stmt_insert->execute([$order_id, $user_id, $plan_id, $amount]);

    // 6. 支付参数组装（规范URL+签名安全）
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";
    $notify_url = $base_url . '/payment/notify.php'; // 异步回调地址
    $return_url = $base_url . '/payment/return.php'; // 同步回调地址

    // 支付参数（按支付网关要求排序）
    $params = [
        "pid" => $epay_config['epay_pid'],
        "type" => $payment_method,
        "out_trade_no" => $order_id,
        "notify_url" => $notify_url,
        "return_url" => $return_url,
        "name" => $plan['name'] . "（{$site_name}）", // 订单名称添加站点标识
        "money" => $amount,
        "sitename" => $site_name
    ];

    // 生成签名（严格按MD5规则，防篡改）
    ksort($params);
    $string_to_sign = "";
    foreach ($params as $key => $val) {
        if ($val !== '' && $key !== 'sign' && $key !== 'sign_type') {
            $string_to_sign .= "{$key}={$val}&";
        }
    }
    $string_to_sign = rtrim($string_to_sign, '&');
    $sign = md5($string_to_sign . $epay_config['epay_key']);
    $params['sign'] = $sign;
    $params['sign_type'] = 'MD5';

    // 7. 跳转支付页面（规范URL拼接）
    $payment_url = rtrim($epay_config['epay_url'], '/') . '/submit.php?' . http_build_query($params);
    header("Location: {$payment_url}");
    exit;

} catch (Exception $e) {
    // 错误统一处理，与用户中心反馈风格一致
    $_SESSION['feedback_msg'] = '创建充值订单失败：' . $e->getMessage();
    $_SESSION['feedback_type'] = 'error';
    header('Location: index.php');
    exit;
}
?>
