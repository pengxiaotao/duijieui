<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// 获取当前设置
$stmt = $conn->prepare("SELECT * FROM promotion_settings ORDER BY id DESC LIMIT 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 获取当前推广比例设置
$stmt = $conn->prepare("SELECT referral_rate FROM promotion_settings ORDER BY id DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();
$referral_rate = $settings['referral_rate'] ?? 0;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_referral_rate = floatval($_POST['referral_rate']);
    
    // 验证数据
    if ($new_referral_rate < 0 || $new_referral_rate > 100) {
        $error = "推广比例必须在0-100之间";
    } else {
        // 更新设置
        $stmt = $conn->prepare("INSERT INTO promotion_settings (referral_rate) VALUES (?)");
        $stmt->bind_param("d", $new_referral_rate);
        
        if ($stmt->execute()) {
            $success = "推广比例设置更新成功！";
            // 记录日志
            log_message("管理员更新推广比例设置，推广比例: {$new_referral_rate}%");
        } else {
            $error = "推广比例设置更新失败，请稍后重试";
            log_message("管理员更新推广比例设置失败，错误信息: " . $stmt->error);
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
    <title>推广返利设置 - 管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .settings-form {
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
        <div class="settings-form">
            <h2 class="text-center mb-4">推广返利设置</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="mb-3">
                    <label for="referral_rate" class="form-label">推广比例(%)</label>
                    <input type="number" class="form-control" id="referral_rate" name="referral_rate" 
                           value="<?php echo htmlspecialchars($referral_rate); ?>" 
                           step="0.01" min="0" max="100" required>
                    <small class="text-muted">设置用户充值后，推广人获得的推广比例</small>
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