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

// 检查是否已有分站
$stmt = $conn->prepare("SELECT id FROM sub_sites WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    header('Location: index.php');
    exit;
}
$stmt->close();

// 获取可用套餐
$stmt = $conn->prepare("SELECT * FROM sub_site_packages WHERE status = 1 ORDER BY price ASC");
$stmt->execute();
$packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = intval($_POST['package_id']);
    $domain = trim($_POST['domain']);
    
    // 验证表单数据
    if (empty($package_id)) {
        $error = "请选择套餐";
    } elseif (empty($domain)) {
        $error = "域名不能为空";
    } elseif (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
        $error = "域名格式不正确";
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
            // 检查域名是否已被使用
            $stmt = $conn->prepare("SELECT id FROM sub_sites WHERE domain = ?");
            $stmt->bind_param("s", $domain);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "该域名已被使用";
            } else {
                // 创建订单
                $order_no = date('YmdHis') . rand(1000, 9999);
                $stmt = $conn->prepare("INSERT INTO sub_site_orders (user_id, package_id, order_no, amount) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iisd", $user_id, $package_id, $order_no, $package['price']);
                
                if ($stmt->execute()) {
                    // 跳转到支付页面
                    header("Location: sub_site_pay.php?order_no=" . $order_no);
                    exit;
                } else {
                    $error = "订单创建失败，请稍后重试";
                    log_message("用户 $user_id 创建分站订单失败，错误信息: " . $stmt->error);
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开通分站</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .create-form {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="create-form">
            <h2 class="text-center mb-4">开通分站</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <div class="text-center">
                    <a href="index.php" class="btn btn-primary">返回首页</a>
                </div>
            <?php else: ?>
                <form method="post" action="" id="createForm">
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
                    
                    <div class="mb-3">
                        <label for="domain" class="form-label">分站域名</label>
                        <input type="text" class="form-control" id="domain" name="domain" value="<?php echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : ''; ?>" required>
                        <small class="text-muted">例如：sub.example.com</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">开通分站</button>
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