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
$sub_site = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub_site) {
    header('Location: sub_site_create.php');
    exit;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $use_main_payment = isset($_POST['use_main_payment']) ? 1 : 0;
    $epay_url = trim($_POST['epay_url']);
    $epay_id = trim($_POST['epay_id']);
    $epay_key = trim($_POST['epay_key']);
    
    // 验证表单数据
    if (!$use_main_payment) {
        if (empty($epay_url)) {
            $error = "易支付网址不能为空";
        } elseif (!filter_var($epay_url, FILTER_VALIDATE_URL)) {
            $error = "易支付网址格式不正确";
        } elseif (empty($epay_id)) {
            $error = "易支付ID不能为空";
        } elseif (empty($epay_key)) {
            $error = "易支付密钥不能为空";
        }
    }
    
    if (empty($error)) {
        // 更新分站支付设置
        $stmt = $conn->prepare("UPDATE sub_sites SET use_main_payment = ?, epay_url = ?, epay_id = ?, epay_key = ? WHERE user_id = ?");
        $stmt->bind_param("isssi", $use_main_payment, $epay_url, $epay_id, $epay_key, $user_id);
        
        if ($stmt->execute()) {
            $success = "支付设置更新成功！";
            // 记录日志
            log_message("用户 $user_id 更新分站支付设置成功，使用主站支付: " . ($use_main_payment ? "是" : "否"));
        } else {
            $error = "支付设置更新失败，请稍后重试";
            log_message("用户 $user_id 更新分站支付设置失败，错误信息: " . $stmt->error);
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
    <title>分站支付设置</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .payment-form {
            max-width: 600px;
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
            <h2 class="text-center mb-4">分站支付设置</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="use_main_payment" id="use_main_payment" <?php echo $sub_site['use_main_payment'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="use_main_payment">
                            使用主站支付接口
                        </label>
                    </div>
                </div>
                
                <div id="custom_payment" style="display: <?php echo $sub_site['use_main_payment'] ? 'none' : 'block'; ?>">
                    <div class="mb-3">
                        <label for="epay_url" class="form-label">易支付网址</label>
                        <input type="url" class="form-control" id="epay_url" name="epay_url" value="<?php echo htmlspecialchars($sub_site['epay_url'] ?? ''); ?>">
                        <small class="text-muted">例如：https://pay.example.com</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="epay_id" class="form-label">易支付ID</label>
                        <input type="text" class="form-control" id="epay_id" name="epay_id" value="<?php echo htmlspecialchars($sub_site['epay_id'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="epay_key" class="form-label">易支付密钥</label>
                        <input type="text" class="form-control" id="epay_key" name="epay_key" value="<?php echo htmlspecialchars($sub_site['epay_key'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">保存设置</button>
                    <a href="index.php" class="btn btn-secondary">返回首页</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('use_main_payment').addEventListener('change', function() {
            document.getElementById('custom_payment').style.display = this.checked ? 'none' : 'block';
        });
    </script>
</body>
</html> 