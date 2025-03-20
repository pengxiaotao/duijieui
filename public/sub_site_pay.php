<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 获取订单号
$order_no = isset($_GET['order_no']) ? trim($_GET['order_no']) : '';
if (empty($order_no)) {
    header('Location: sub_site_create.php');
    exit;
}

// 获取订单信息
$stmt = $conn->prepare("SELECT o.*, p.name as package_name, p.duration FROM sub_site_orders o JOIN sub_site_packages p ON o.package_id = p.id WHERE o.order_no = ? AND o.user_id = ? AND o.status = 0");
$stmt->bind_param("si", $order_no, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: sub_site_create.php');
    exit;
}

// 处理支付回调
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $out_trade_no = $_POST['out_trade_no'] ?? '';
    $trade_no = $_POST['trade_no'] ?? '';
    $name = $_POST['name'] ?? '';
    $money = $_POST['money'] ?? '';
    $trade_status = $_POST['trade_status'] ?? '';
    $sign = $_POST['sign'] ?? '';
    
    // 验证签名
    $sign_str = "out_trade_no={$out_trade_no}&trade_no={$trade_no}&type={$type}&name={$name}&money={$money}&trade_status={$trade_status}&key=" . EPAY_KEY;
    $verify_sign = md5($sign_str);
    
    if ($verify_sign === $sign && $trade_status === 'TRADE_SUCCESS') {
        // 开始事务
        $conn->begin_transaction();
        
        try {
            // 更新订单状态
            $stmt = $conn->prepare("UPDATE sub_site_orders SET status = 1 WHERE order_no = ? AND status = 0");
            $stmt->bind_param("s", $out_trade_no);
            $stmt->execute();
            
            // 创建分站
            $expire_time = date('Y-m-d H:i:s', strtotime("+{$order['duration']} days"));
            $stmt = $conn->prepare("INSERT INTO sub_sites (user_id, domain, price, expire_time) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isds", $user_id, $order['domain'], $order['amount'], $expire_time);
            $stmt->execute();
            
            // 提交事务
            $conn->commit();
            
            // 记录日志
            log_message("用户 $user_id 支付分站订单成功，订单号: $out_trade_no，金额: $money");
            
            // 返回成功
            echo "success";
            exit;
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            log_message("用户 $user_id 支付分站订单失败，错误信息: " . $e->getMessage());
            echo "fail";
            exit;
        }
    } else {
        echo "fail";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分站支付</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .payment-form {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-form">
            <h2 class="text-center mb-4">分站支付</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">订单信息</h5>
                    <p class="card-text">订单号：<?php echo htmlspecialchars($order['order_no']); ?></p>
                    <p class="card-text">套餐名称：<?php echo htmlspecialchars($order['package_name']); ?></p>
                    <p class="card-text">支付金额：<?php echo number_format($order['amount'], 2); ?> 元</p>
                </div>
            </div>
            
            <form method="post" action="<?php echo EPAY_URL; ?>" class="text-center">
                <input type="hidden" name="pid" value="<?php echo EPAY_ID; ?>">
                <input type="hidden" name="type" value="alipay">
                <input type="hidden" name="out_trade_no" value="<?php echo $order['order_no']; ?>">
                <input type="hidden" name="notify_url" value="<?php echo SITE_URL; ?>/public/sub_site_pay.php">
                <input type="hidden" name="return_url" value="<?php echo SITE_URL; ?>/public/sub_site_manage.php">
                <input type="hidden" name="name" value="分站开通-<?php echo htmlspecialchars($order['package_name']); ?>">
                <input type="hidden" name="money" value="<?php echo $order['amount']; ?>">
                <button type="submit" class="btn btn-primary btn-lg">立即支付</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>