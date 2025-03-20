<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 检查用户是否已登录
$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in) {
    // 获取用户信息
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, balance, invite_code, email FROM users WHERE id = ?");
    if (!$stmt) {
        die("查询准备失败: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // 检查用户是否存在
    if (!$user) {
        // 用户不存在，清除session并重定向到登录页
        session_destroy();
        header("Location: login.php");
        exit;
    }
    
    $username = $user['username'] ?? '';
    $balance = $user['balance'] ?? 0;
    $invite_code = $user['invite_code'] ?? '';
    $email = $user['email'] ?? '';

    // 获取推广统计信息
    $stmt = $conn->prepare("SELECT COUNT(*) as total_invites FROM users WHERE invited_by = ?");
    if (!$stmt) {
        die("查询准备失败: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $invite_stats = $stmt->get_result()->fetch_assoc();
    $total_invites = $invite_stats['total_invites'] ?? 0;

    // 获取邀请的用户列表
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.created_at,
        COALESCE(SUM(r.amount), 0) as total_recharge
        FROM users u
        LEFT JOIN recharge_logs r ON u.id = r.user_id AND r.is_paid = 1
        WHERE u.invited_by = ?
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
    if (!$stmt) {
        die("查询准备失败: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $invited_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];

    // 获取分站信息
    $stmt = $conn->prepare("SELECT * FROM sub_sites WHERE user_id = ?");
    if (!$stmt) {
        die("查询准备失败: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $sub_site = $stmt->get_result()->fetch_assoc();

    // 获取最近的上传记录
    $stmt = $conn->prepare("SELECT * FROM uploaded_images WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    if (!$stmt) {
        die("查询准备失败: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recent_uploads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];

    // 获取最近的充值记录
    $stmt = $conn->prepare("SELECT * FROM recharge_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    if (!$stmt) {
        die("查询准备失败: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recent_recharges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];

    // 生成推广链接
    $promote_url = $config['site_url'] . "/public/register.php?invite_code=" . $invite_code;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>图片转文字系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* 统一页面样式，增强一致性 */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .nav-link { color: #333; }
        .nav-link:hover { color: #007bff; }
        .card { margin-bottom: 20px; }
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-number-size: 2 {
            font4px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
        .dashboard-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .invite-link {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            word-break: break-all;
        }
        .copy-btn {
            cursor: pointer;
            color: #007bff;
        }
        .copy-btn:hover {
            color: #0056b3;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-4">
        <?php if ($is_logged_in): ?>
            <div class="row">
                <!-- 用户信息卡片 -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">账户信息</h5>
                            <p class="mb-1">用户名：<?php echo htmlspecialchars($username); ?></p>
                            <p class="mb-1">邮箱：<?php echo htmlspecialchars($email); ?></p>
                            <p class="mb-3">余额：<?php echo number_format($balance, 2); ?> 元</p>
                            <div class="d-grid gap-2">
                                <a href="upload.php" class="btn btn-primary">上传图片识别</a>
                                <a href="recharge.php" class="btn btn-primary">充值</a>
                                <a href="profile.php" class="btn btn-outline-primary">修改资料</a>
                                <a href="logout.php" class="btn btn-danger">退出登录</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 分站信息卡片 -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">分站信息</h5>
                            <?php if ($sub_site): ?>
                                <p class="mb-1">域名：<?php echo htmlspecialchars($sub_site['domain']); ?></p>
                                <p class="mb-1">到期时间：<?php echo date('Y-m-d H:i:s', strtotime($sub_site['expire_time'])); ?></p>
                                <p class="mb-3">状态：<?php echo $sub_site['status'] ? '正常' : '已过期'; ?></p>
                                <div class="d-grid gap-2">
                                    <a href="https://<?php echo htmlspecialchars($sub_site['domain']); ?>" class="btn btn-primary" target="_blank">管理网站</a>
                                    <a href="sub_site_renew.php" class="btn btn-primary">续费</a>
                                    <a href="sub_site_orders.php" class="btn btn-outline-primary">订单记录</a>
                                </div>
                            <?php else: ?>
                                <p class="mb-3">您还没有开通分站</p>
                                <div class="d-grid">
                                    <a href="sub_site_create.php" class="btn btn-primary">开通分站</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 推广信息卡片 -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">推广信息</h5>
                            <p class="mb-1">总邀请人数：<?php echo $total_invites; ?></p>
                            <p class="mb-3">推广链接：<?php echo htmlspecialchars($promote_url); ?></p>
                            <button class="btn btn-outline-primary" onclick="copyPromoteUrl()">复制链接</button>
                        </div>
                    </div>
                </div>

                <!-- 邀请用户列表 -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">邀请的用户</h5>
                            <div class="list-group">
                                <?php foreach ($invited_users as $user): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <small class="text-muted d-block">注册时间：<?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></small>
                                            </div>
                                            <span class="badge bg-primary">消费：<?php echo number_format($user['total_recharge'], 2); ?> 元</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 最近记录卡片 -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">最近记录</h5>
                            <div class="list-group">
                                <?php foreach ($recent_uploads as $upload): ?>
                                    <div class="list-group-item">
                                        <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($upload['created_at'])); ?></small>
                                        <div>上传图片</div>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($recent_recharges as $recharge): ?>
                                    <div class="list-group-item">
                                        <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($recharge['created_at'])); ?></small>
                                        <div>充值 <?php echo number_format($recharge['amount'], 2); ?> 元</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center">
                <h1 class="mb-4">图片转文字系统</h1>
                <div class="d-grid gap-3 col-md-6 mx-auto">
                    <a href="register.php" class="btn btn-primary btn-lg">注册</a>
                    <a href="login.php" class="btn btn-outline-primary btn-lg">登录</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyPromoteUrl() {
            const url = '<?php echo $promote_url; ?>';
            navigator.clipboard.writeText(url).then(() => {
                alert('推广链接已复制到剪贴板');
            }).catch(err => {
                console.error('复制失败:', err);
                alert('复制失败，请手动复制');
            });
        }
    </script>
</body>

</html>