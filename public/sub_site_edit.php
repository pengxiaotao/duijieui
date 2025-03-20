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

// 获取分站信息
$stmt = $conn->prepare("SELECT * FROM sub_sites WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}
$sub_site = $result->fetch_assoc();
$stmt->close();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = trim($_POST['domain']);
    $price = floatval($_POST['price']);
    $epay_id = trim($_POST['epay_id']);
    $epay_key = trim($_POST['epay_key']);
    
    // 验证表单数据
    if (empty($domain)) {
        $error = "域名不能为空";
    } elseif (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
        $error = "域名格式不正确";
    } elseif ($price <= 0) {
        $error = "价格必须大于0";
    } elseif (empty($epay_id)) {
        $error = "易支付ID不能为空";
    } elseif (empty($epay_key)) {
        $error = "易支付密钥不能为空";
    } else {
        // 检查域名是否已被其他分站使用
        $stmt = $conn->prepare("SELECT id FROM sub_sites WHERE domain = ? AND id != ?");
        $stmt->bind_param("si", $domain, $sub_site['id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "该域名已被使用";
        } else {
            // 更新分站信息
            $stmt = $conn->prepare("UPDATE sub_sites SET domain = ?, price = ?, epay_id = ?, epay_key = ? WHERE user_id = ?");
            $stmt->bind_param("sdssi", $domain, $price, $epay_id, $epay_key, $user_id);
            
            if ($stmt->execute()) {
                $success = "分站设置更新成功！";
                // 更新本地变量
                $sub_site['domain'] = $domain;
                $sub_site['price'] = $price;
                $sub_site['epay_id'] = $epay_id;
                $sub_site['epay_key'] = $epay_key;
                // 记录日志
                log_message("用户 $user_id 更新分站设置成功，域名: $domain");
            } else {
                $error = "分站设置更新失败，请稍后重试";
                log_message("用户 $user_id 更新分站设置失败，错误信息: " . $stmt->error);
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改分站设置</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .edit-form {
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
        <div class="edit-form">
            <h2 class="text-center mb-4">修改分站设置</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="mb-3">
                    <label for="domain" class="form-label">分站域名</label>
                    <input type="text" class="form-control" id="domain" name="domain" value="<?php echo htmlspecialchars($sub_site['domain']); ?>" required>
                    <small class="text-muted">例如：sub.example.com</small>
                </div>
                
                <div class="mb-3">
                    <label for="price" class="form-label">商品价格</label>
                    <input type="number" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($sub_site['price']); ?>" step="0.01" min="0" required>
                </div>
                
                <div class="mb-3">
                    <label for="epay_id" class="form-label">易支付ID</label>
                    <input type="text" class="form-control" id="epay_id" name="epay_id" value="<?php echo htmlspecialchars($sub_site['epay_id']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="epay_key" class="form-label">易支付密钥</label>
                    <input type="text" class="form-control" id="epay_key" name="epay_key" value="<?php echo htmlspecialchars($sub_site['epay_key']); ?>" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">保存设置</button>
                    <a href="index.php" class="btn btn-secondary">返回首页</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 