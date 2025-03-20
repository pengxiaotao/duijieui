<?php
/* *
 * 功能：彩虹易支付异步通知页面
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */

// 引入必要的文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once("lib/epay.config.php");
require_once("lib/EpayCore.class.php");
// 记录日志函数
function log_message($message) {
    $log_file = 'notify.log';
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// 计算得出通知验证结果
$epay = new EpayCore($epay_config);
$verify_result = $epay->verifyNotify();

if ($verify_result) { // 验证成功
    // 商户订单号
    $out_trade_no = $_GET['out_trade_no'];

    // 彩虹易支付交易号
    $trade_no = $_GET['trade_no'];

    // 交易状态
    $trade_status = $_GET['trade_status'];

    // 支付方式
    $type = $_GET['type'];

    // 支付金额
    $money = $_GET['money'];

    if ($trade_status == 'TRADE_SUCCESS') {
        // 判断该笔订单是否在商户网站中已经做过处理
        $stmt = $conn->prepare("SELECT id, user_id, amount, is_paid FROM recharge_logs WHERE order_id = ?");
        $stmt->bind_param("s", $out_trade_no);
        $stmt->execute();
        $result = $stmt->get_result();
        $recharge_log = $result->fetch_assoc();

        if ($recharge_log && $recharge_log['is_paid'] == 0) {
            // 更新充值记录的状态为已支付
            $stmt = $conn->prepare("UPDATE recharge_logs SET is_paid = 1, status = 'success' WHERE order_id = ?");
            $stmt->bind_param("s", $out_trade_no);
            if (!$stmt->execute()) {
                log_message("更新充值记录状态失败，订单号: $out_trade_no，错误信息: " . $stmt->error);
                echo 'fail';
                exit;
            }

            // 获取用户 ID 和充值金额
            $user_id = $recharge_log['user_id'];
            $amount = $recharge_log['amount'];

            // 更新用户余额
            $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE username = (SELECT username FROM users WHERE id = ?)");
            $stmt->bind_param("di", $amount, $user_id);
            if (!$stmt->execute()) {
                log_message("更新用户余额失败，订单号: $out_trade_no，错误信息: " . $stmt->error);
                echo 'fail';
                exit;
            }
        }
    }

    // 验证成功返回
    echo "success";
} else {
    // 记录验证失败信息
    log_message("验证失败，支付平台返回参数：" . print_r($_GET, true));
    // 验证失败
    echo "fail";
}
?>
