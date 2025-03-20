<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// 检查数据库连接是否成功
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "数据库连接失败: ". $conn->connect_error
    ]);
    exit;
}

// 统一错误处理函数
function outputError($message) {
    echo json_encode([
        "status" => "error",
        "message" => $message
    ]);
    exit;
}

// 检查请求方法是否为 POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查 Content-Type 是否为 application/x-www-form-urlencoded
    $contentType = isset($_SERVER["CONTENT_TYPE"]) 
       ? strtolower($_SERVER["CONTENT_TYPE"]) 
        : '';
    if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
        $token = $_POST['token']?? null;
        $package_id = $_POST['package_id']?? null;

        // 验证 token
        if ($token === null ||!verifyToken($token)) {
            outputError("无效的身份验证 token");
        }

        // 从验证通过的 token 中获取用户 ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE token =?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $userInfo = $result->fetch_assoc();
        if ($userInfo === null) {
            outputError("无法获取用户 ID，请检查 token 的有效性");
        }
        $user_id = $userInfo['id'];

        if ($package_id === null ||!is_numeric($package_id) || intval($package_id) <= 0) {
            outputError("缺少必要的参数 package_id 或 package_id 无效");
        }
        $package_id = intval($package_id);

        try {
            // 获取套餐信息
            $stmt = $conn->prepare("SELECT price, bonus_amount FROM packages WHERE id =?");
            $stmt->bind_param("i", $package_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $package = $result->fetch_assoc();
            if ($package === null) {
                throw new Exception("无法获取套餐信息，请检查 package_id 的有效性");
            }
            $amount = $package['price'];
            $bonus_amount = $package['bonus_amount'];

            // 生成唯一的订单号
            $order_id = uniqid();
            // 记录充值订单到数据库，初始 is_paid 为 0 表示未支付
            $stmt = $conn->prepare("INSERT INTO recharge_logs (user_id, amount, order_id, is_paid) VALUES (?,?,?, 0)");
            $stmt->bind_param("ids", $user_id, $amount, $order_id);
            if (!$stmt->execute()) {
                throw new Exception("订单记录失败: ". $stmt->error);
            }

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

            // 生成支付链接
            $payment_url = 'https://pay.uomg.cn/submit.php?' . http_build_query($pay_params);

            echo json_encode([
                "status" => "success",
                "message" => "充值请求已提交",
                "result" => $payment_url
            ]);
        } catch (Exception $e) {
            outputError($e->getMessage());
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    } else {
        outputError("不支持的 Content-Type，仅支持 application/x-www-form-urlencoded");
    }
} else {
    outputError("仅支持 POST 请求");
}

$conn->close();
?>