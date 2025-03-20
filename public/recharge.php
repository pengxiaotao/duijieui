<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 获取所有套餐信息
$stmt = $conn->prepare("SELECT * FROM packages");
$stmt->execute();
$result = $stmt->get_result();
$packages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $package_id = $_POST['package_id'];

    // 获取套餐信息
    $stmt = $conn->prepare("SELECT price, bonus_amount FROM packages WHERE id = ?");
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $package = $result->fetch_assoc();
    $amount = $package['price'];
    $bonus_amount = $package['bonus_amount'];

    // 生成唯一的订单号
    $order_id = date('YmdHis') . rand(1000, 9999);
    // 记录充值订单到数据库
    $stmt = $conn->prepare("INSERT INTO recharge_logs (user_id, amount, order_id, is_paid) VALUES (?, ?, ?, 0)");
    $stmt->bind_param("ids", $user_id, $amount, $order_id);
    if (!$stmt->execute()) {
        log_message("订单记录失败，订单号: $order_id，错误信息: " . $stmt->error);
        $error = "订单记录失败，请稍后重试。";
    }
    $stmt->close();

    if (!isset($error)) {
        // 彩虹易支付配置
        $pid = RAINBOW_PAY_ID;
        $pay_key = RAINBOW_PAY_KEY;
        $notify_url = $config['site_url'] . '/notify.php'; // 请替换为实际的异步通知地址
        $return_url = $config['site_url'] . '/recharge_success.php'; // 请替换为实际的跳转通知地址
        $payment_type = 'alipay'; // 可根据需求修改支付方式，如 wechat 等
        $product_name = '电话助手会员充值'; // 商品名称

        // 构造签名所需的参数数组
        $sign_params = [
            'pid' => $pid,
            'type' => $payment_type,
            'out_trade_no' => $order_id,
            'notify_url' => $notify_url,
            'return_url' => $return_url,
            'name' => $product_name,
            'money' => $amount
        ];

        // 按照参数名排序
        ksort($sign_params);

        // 拼接参数
        $sign_str = '';
        foreach ($sign_params as $key => $value) {
            $sign_str .= $key . '=' . $value . '&';
        }
        $sign_str = rtrim($sign_str, '&');
        $sign_str .= $pay_key;

        // 生成签名
        $sign = md5($sign_str);

        // 构造支付请求参数
        $pay_params = [
            'pid' => $pid,
            'type' => $payment_type,
            'out_trade_no' => $order_id,
            'notify_url' => $notify_url,
            'return_url' => $return_url,
            'name' => $product_name,
            'money' => $amount,
            'sign' => $sign,
            'sign_type' => 'MD5'
        ];

        // 使用 POST 方式提交表单到支付接口
        $form = '<form id="payForm" action="https://pay.uomg.cn/submit.php" method="post">';
        foreach ($pay_params as $key => $value) {
            $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
        }
        $form .= '<input type="submit" value="立即支付" style="display:none;"></form>';
        $form .= '<script>document.getElementById("payForm").submit();</script>';

        echo $form;
    }
}

if ($verify_sign === $sign && $trade_status === 'TRADE_SUCCESS') {
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 更新订单状态
        $stmt = $conn->prepare("UPDATE recharge_logs SET is_paid = 1, paid_time = NOW(), trade_no = ? WHERE order_no = ?");
        $stmt->bind_param("ss", $trade_no, $out_trade_no);
        $stmt->execute();
        
        // 获取订单信息
        $stmt = $conn->prepare("SELECT user_id, amount FROM recharge_logs WHERE order_no = ?");
        $stmt->bind_param("s", $out_trade_no);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        // 更新用户余额
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $order['amount'], $order['user_id']);
        $stmt->execute();
        
        // 处理推广返利
        // 获取推广设置
        $stmt = $conn->prepare("SELECT * FROM promotion_settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        
        if ($settings) {
            // 获取推广人信息
            $stmt = $conn->prepare("SELECT invited_by FROM users WHERE id = ?");
            $stmt->bind_param("i", $order['user_id']);
            $stmt->execute();
            $inviter = $stmt->get_result()->fetch_assoc();
            
            if ($inviter && $inviter['invited_by']) {
                // 计算返利金额
                $reward_amount = $order['amount'] * ($settings['recharge_rate'] / 100);
                
                // 更新推广人余额
                $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->bind_param("di", $reward_amount, $inviter['invited_by']);
                $stmt->execute();
                
                // 记录返利记录
                $stmt = $conn->prepare("INSERT INTO promotion_rewards (user_id, from_user_id, amount, recharge_amount) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iidd", $inviter['invited_by'], $order['user_id'], $reward_amount, $order['amount']);
                $stmt->execute();
            }
        }
        
        // 提交事务
        $conn->commit();
        
        // 记录日志
        log_message("用户 {$order['user_id']} 充值成功，订单号: $out_trade_no，金额: $money");
        
        // 返回成功
        echo "success";
        exit;
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        log_message("用户 {$order['user_id']} 充值失败，错误信息: " . $e->getMessage());
        echo "fail";
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>用户充值</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .recharge-form {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .package-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .package-card:hover {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .package-card.selected {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="recharge-form">
            <h2 class="text-center mb-4">用户充值</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" id="rechargeForm">
                <div class="mb-4">
                    <label class="form-label">选择充值套餐：</label>
                    <?php foreach ($packages as $package): ?>
                        <div class="package-card" onclick="selectPackage(<?php echo $package['id']; ?>)">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="package_id" 
                                       id="package_<?php echo $package['id']; ?>" 
                                       value="<?php echo $package['id']; ?>" required>
                                <label class="form-check-label" for="package_<?php echo $package['id']; ?>">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($package['name']); ?></h5>
                                    <p class="mb-1">价格：<?php echo number_format($package['price'], 2); ?> 元</p>
                                    <p class="mb-0 text-success">赠送：<?php echo number_format($package['bonus_amount'], 2); ?> 元</p>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">立即充值</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPackage(packageId) {
            // 取消所有套餐的选中状态
            document.querySelectorAll('.package-card').forEach(card => {
                card.classList.remove('selected');
            });
            // 选中当前点击的套餐
            event.currentTarget.classList.add('selected');
            // 选中对应的单选按钮
            document.getElementById('package_' + packageId).checked = true;
        }
    </script>
</body>
</html>