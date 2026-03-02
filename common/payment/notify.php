<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!file_exists('../../config.php')) { die("fail"); }
if (!file_exists('lib/epaycore.php')) { die("fail"); }
require_once '../../config.php';
require_once 'lib/epaycore.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings WHERE setting_key IN ('epay_pid', 'epay_key', 'epay_url')");
    $epay_db_config = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $epay_sdk_config = [
        'pid' => $epay_db_config['epay_pid'],
        'key' => $epay_db_config['epay_key'],
        'apiurl' => $epay_db_config['epay_url']
    ];
    $epay = new EpayCore($epay_sdk_config);
    $verify_result = $epay->verifyNotify();

    if ($verify_result) {
        $out_trade_no = $_GET['out_trade_no'];
        $trade_no = $_GET['trade_no'];
        $trade_status = $_GET['trade_status'];

        if ($trade_status == 'TRADE_SUCCESS') {
            $pdo->beginTransaction();
            $stmt_order = $pdo->prepare("SELECT * FROM sl_orders WHERE order_id = ? AND status = 'pending' FOR UPDATE");
            $stmt_order->execute([$out_trade_no]);
            $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
            if ($order) {
                $stmt_plan = $pdo->prepare("SELECT balance_to_add FROM sl_billing_plans WHERE id = ?");
                $stmt_plan->execute([$order['plan_id']]);
                $balance_to_add = $stmt_plan->fetchColumn();
                if ($balance_to_add) {
                    $stmt_update_user = $pdo->prepare("UPDATE sl_users SET balance = balance + ? WHERE id = ?");
                    $stmt_update_user->execute([$balance_to_add, $order['user_id']]);
                    $stmt_update_order = $pdo->prepare("UPDATE sl_orders SET status = 'paid', paid_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt_update_order->execute([$order['id']]);
                    $pdo->commit();
                } else { $pdo->rollBack(); }
            } else { $pdo->commit(); }
        }
        echo "success";
    } else {
        echo "fail";
    }
} catch (Exception $e) { if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); } echo "fail"; }
exit;