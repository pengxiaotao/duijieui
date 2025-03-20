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

<!-- 修改密码功能 -->
<div class="col-md-4">
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">修改密码</h5>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <p class="success"><?php echo $success; ?></p>
            <?php endif; ?>
            <form method="post">
                <label for="old_password">旧密码：</label><br>
                <input type="password" id="old_password" name="old_password" required><br>
                <label for="new_password">新密码：</label><br>
                <input type="password" id="new_password" name="new_password" required><br>
                <label for="confirm_password">确认密码：</label><br>
                <input type="password" id="confirm_password" name="confirm_password" required><br>
                <input type="submit" value="修改密码">
            </form>
        </div>
    </div>
</div>

<!-- ComfyUI服务器管理功能 -->
<div class="col-md-4">
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">ComfyUI服务器管理</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名称</th>
                            <th>地址</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $s): ?>
                        <tr>
                            <td><?php echo $s['id']; ?></td>
                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><?php echo htmlspecialchars($s['url']); ?></td>
                            <td><?php echo $s['status'] ? '启用' : '禁用'; ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-info">编辑</a>
                                <a href="?action=delete&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除这个服务器吗？')">删除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?php echo $server ? '编辑服务器' : '添加服务器'; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <?php if ($server): ?>
                            <input type="hidden" name="id" value="<?php echo $server['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="name" class="form-label">服务器名称</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $server ? htmlspecialchars($server['name']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="url" class="form-label">服务器地址</label>
                            <input type="url" class="form-control" id="url" name="url" value="<?php echo $server ? htmlspecialchars($server['url']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="api_key" class="form-label">API密钥（可选）</label>
                            <input type="text" class="form-control" id="api_key" name="api_key" value="<?php echo $server ? htmlspecialchars($server['api_key']) : ''; ?>">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="status" name="status" <?php echo $server && $server['status'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">启用服务器</label>
                        </div>
                        <button type="submit" class="btn btn-primary">保存</button>
                        <?php if ($server): ?>
                            <a href="comfyui_servers.php" class="btn btn-secondary">取消</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ComfyUI使用统计功能 -->
<div class="col-md-4">
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">ComfyUI使用统计</h5>
            <form method="get" class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">开始日期</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">结束日期</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block">查询</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>服务器名称</th>
                            <th>总用户数</th>
                            <th>总生成量</th>
                            <th>最后更新</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stat['name']); ?></td>
                            <td><?php echo $stat['total_users'] ?? 0; ?></td>
                            <td><?php echo $stat['total_generations'] ?? 0; ?></td>
                            <td><?php echo $stat['last_update'] ?? '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <canvas id="dailyStatsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ComfyUI工作流API管理功能 -->
<div class="col-md-4">
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">ComfyUI工作流API管理</h5>
            <h2>添加ComfyUI工作流API</h2>
            <form method="post">
                <div class="mb-3">
                    <label for="name" class="form-label">名称</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-