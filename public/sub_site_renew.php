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

// 获取用户的分站信息
$stmt = $conn->prepare("SELECT * FROM sub_sites WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sub_site = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub_site) {
    header('Location: index.php');
    exit;
}

// 获取可用套餐
$stmt = $conn->prepare("SELECT * FROM sub_site_packages WHERE status = 1 ORDER BY price ASC");
$stmt->execute();
$packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = intval($_POST['package_id']);
    
    // 验证表单数据
    if (empty($package_id)) {
        $error = "请选择套餐";
    } else {
        // 获取套餐信息
        $stmt = $conn->prepare("SELECT * FROM sub_site_packages WHERE id = ? AND status = 1");
        $stmt->bind_param("i", $package_id);
        $stmt->execute();
        $package = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$package) {
            $error = "套餐不存在或已下架";
        } else {
            // 生成订单号
            $order_no = date('YmdHis') . rand(1000, 9999);
            
            // 创建订单
            $stmt = $conn->prepare("INSERT INTO sub_site_orders (user_id, package_id, order_no, amount) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisd", $user_id, $package_id, $order_no, $package['price']);
            
            if ($stmt->execute()) {
                // 更新分站到期时间
                $expire_time = max(
                    strtotime($sub_site['expire_time']),
                    time()
                );
                $expire_time = date('Y-m-d H:i:s', $expire_time + ($package['duration'] * 24 * 3600));
                
                $stmt = $conn->prepare("UPDATE sub_sites SET expire_time = ?, status = 1 WHERE user_id = ?");
                $stmt->bind_param("si", $expire_time, $user_id);
                
                if ($stmt->execute()) {
                    $success = "续费成功！";
                    // 记录日志
                    log_message("用户 $user_id 续费成功，域名: {$sub_site['domain']}，套餐: {$package['name']}");
                    
                    // 刷新分站信息
                    $stmt = $conn->prepare("SELECT * FROM sub_sites WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $sub_site = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                } else {
                    $error = "续费失败，请稍后重试";
                    log_message("用户 $user_id 续费失败，错误信息: " . $stmt->error);
                }
            } else {
                $error = "订单创建失败，请稍后重试";
                log_message("用户 $user_id 创建续费订单失败，错误信息: " . $stmt->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分站续费</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .renew-form {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .package-card {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .package-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .package-card.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .site-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="renew-form">
            <h2 class="text-center mb-4">分站续费</h2>
            
            <div class="site-info">
                <h5>当前分站信息</h5>
                <p class="mb-1">域名：<?php echo htmlspecialchars($sub_site['domain']); ?></p>
                <p class="mb-1">到期时间：<?php echo date('Y-m-d H:i:s', strtotime($sub_site['expire_time'])); ?></p>
                <p class="mb-0">状态：<?php echo $sub_site['status'] ? '正常' : '已过期'; ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <div class="text-center">
                    <a href="index.php" class="btn btn-primary">返回首页</a>
                </div>
            <?php else: ?>
                <form method="post" action="" id="renewForm">
                    <div class="mb-4">
                        <label class="form-label">选择套餐</label>
                        <?php foreach ($packages as $package): ?>
                            <div class="package-card" onclick="selectPackage(<?php echo $package['id']; ?>)">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="package_id" id="package_<?php echo $package['id']; ?>" value="<?php echo $package['id']; ?>" required>
                                    <label class="form-check-label" for="package_<?php echo $package['id']; ?>">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($package['name']); ?></h5>
                                        <p class="mb-1">价格：<?php echo number_format($package['price'], 2); ?> 元</p>
                                        <p class="mb-0">时长：<?php echo $package['duration']; ?> 天</p>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">立即续费</button>
                        <a href="index.php" class="btn btn-secondary">返回首页</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPackage(packageId) {
            // 取消所有套餐的选中状态
            document.querySelectorAll('.package-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // 选中当前套餐
            const card = document.querySelector(`#package_${packageId}`).closest('.package-card');
            card.classList.add('selected');
            
            // 选中单选框
            document.querySelector(`#package_${packageId}`).checked = true;
        }
    </script>
</body>
</html> 