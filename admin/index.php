<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// 检查数据库连接
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 检查必要的表是否存在
$required_tables = ['users', 'uploaded_images', 'recharge_logs', 'comfyui_servers', 'comfyui_usage_logs', 'comfyui_statistics', 'admins'];
foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        die("错误：数据表 '$table' 不存在，请先执行 sql/tables.sql 创建必要的表");
    }
}

// 查询推广比例
$stmt = $conn->prepare("SELECT promotion_ratio FROM settings");
$stmt->execute();
$result = $stmt->get_result();
$promotion_ratio = $result->fetch_row()[0] ?? 0;

// 处理管理员设置每次扣费金额的表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_fee'])) {
        $upload_fee = floatval($_POST['upload_fee']);
        // 将新的扣费金额保存到配置文件中
        $config_file = '../includes/config.php';
        $config_content = file_get_contents($config_file);

        // 查找并替换 UPLOAD_FEE 的定义
        $pattern = "/define\('UPLOAD_FEE', [\d\.]+(\);)/";
        if (preg_match($pattern, $config_content, $matches)) {
            $old_define = $matches[0];
            $new_define = "define('UPLOAD_FEE', $upload_fee);";
            $config_content = str_replace($old_define, $new_define, $config_content);
            file_put_contents($config_file, $config_content);
        }
    }
    header('Location: index.php');
    exit;
}

// 统计充值成功总计金额
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM recharge_logs WHERE is_paid = 1");
if ($stmt === false) {
    die("准备查询失败: " . $conn->error . "\nSQL: SELECT COALESCE(SUM(amount), 0) FROM recharge_logs WHERE is_paid = 1");
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_row();
$total_success_amount = $row[0] ?? 0;

// 统计被邀请用户的充值总额
$stmt = $conn->prepare("SELECT COALESCE(SUM(r.amount), 0) FROM recharge_logs r JOIN users u ON r.user_id = u.id WHERE u.invited_by IS NOT NULL AND r.is_paid = 1");
if ($stmt === false) {
    die("准备查询失败: " . $conn->error . "\nSQL: SELECT COALESCE(SUM(r.amount), 0) FROM recharge_logs r JOIN users u ON r.user_id = u.id WHERE u.invited_by IS NOT NULL AND r.is_paid = 1");
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_row();
$total_invited_recharge_amount = $row[0] ?? 0;

// 统计用户量
$stmt = $conn->prepare("SELECT COUNT(*) FROM users");
if ($stmt === false) {
    die("准备查询失败: " . $conn->error . "\nSQL: SELECT COUNT(*) FROM users");
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_row();
$total_users = $row[0];

// 统计用户上传图片总量
$stmt = $conn->prepare("SELECT COUNT(*) FROM uploaded_images");
if ($stmt === false) {
    die("准备查询失败: " . $conn->error . "\nSQL: SELECT COUNT(*) FROM uploaded_images");
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_row();
$total_uploaded_images = $row[0];

// 统计ComfyUI服务器数量
$stmt = $conn->prepare("SELECT COUNT(*) FROM comfyui_servers");
if ($stmt === false) {
    die("准备查询失败: " . $conn->error . "\nSQL: SELECT COUNT(*) FROM comfyui_servers");
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_row();
$total_comfyui_servers = $row[0];

// 获取最近的充值记录
$stmt = $conn->prepare("
    SELECT r.*, u.username 
    FROM recharge_logs r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC 
    LIMIT 10
");
if ($stmt === false) {
    die("准备查询失败: " . $conn->error . "\nSQL: SELECT r.*, u.username FROM recharge_logs r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 10");
}
$stmt->execute();
$recent_recharges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取最近的用户上传记录
$stmt = $conn->prepare("
    SELECT ui.*, u.username 
    FROM uploaded_images ui 
    JOIN users u ON ui.user_id = u.id 
    ORDER BY ui.id DESC 
    LIMIT 10
");
if ($stmt === false) {
    die("准备查询失败: " . $conn->error . "\nSQL: SELECT ui.*, u.username FROM uploaded_images ui JOIN users u ON ui.user_id = u.id ORDER BY ui.id DESC LIMIT 10");
}
$stmt->execute();
$recent_uploads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取ComfyUI服务器列表
$stmt = $conn->prepare("SELECT * FROM comfyui_servers ORDER BY id DESC");
if ($stmt === false) {
    die("准备查询失败: " . $conn->error . "\nSQL: SELECT * FROM comfyui_servers ORDER BY id DESC");
}
$stmt->execute();
$comfyui_servers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取当前的扣费金额
$upload_fee = UPLOAD_FEE;



// 处理用户选择并保存到数据库
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['promotion_ratio'])) {
        $new_promotion_ratio = floatval($_POST['promotion_ratio']);
        $stmt = $conn->prepare("UPDATE settings SET promotion_ratio = ?");
        if ($stmt === false) {
            die("准备查询失败: ". $conn->error. "\nSQL: UPDATE settings SET promotion_ratio = ?");
        }
        $stmt->bind_param("d", $new_promotion_ratio);
        if ($stmt->execute() === false) {
            die("执行查询失败: ". $stmt->error. "\nSQL: UPDATE settings SET promotion_ratio = ?");
        }
    }
    header('Location: index.php');
    exit;
}






?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .nav-link { color: #333; }
        .nav-link:hover { color: #007bff; }
        .card { margin-bottom: 20px; }
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">后台管理系统</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">用户管理</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="comfyui_servers.php">ComfyUI服务器</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="comfyui_statistics.php">使用统计</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="recharge_logs.php">充值记录</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload_logs.php">上传记录</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">退出登录</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- 统计概览 -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-box">
                    <h5>总充值金额</h5>
                    <p class="stat-number"><?php echo number_format($total_success_amount, 2); ?> 元</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h5>用户总数</h5>
                    <p class="stat-number"><?php echo $total_users; ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h5>上传图片总数</h5>
                    <p class="stat-number"><?php echo $total_uploaded_images; ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h5>ComfyUI服务器</h5>
                    <p class="stat-number"><?php echo $total_comfyui_servers; ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h5>被邀请用户充值总额</h5>
                    <p class="stat-number"><?php echo number_format($total_invited_recharge_amount, 2); ?> 元</p>
                </div>
            </div>
        </div>

        <!-- 系统设置 -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">系统设置</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="upload_fee" class="form-label">每次扣费金额（元）</label>
                        <input type="number" class="form-control" id="upload_fee" name="upload_fee" 
                               step="0.01" value="<?php echo $upload_fee; ?>" style="width: 200px;">
                    </div>
                    <div class="mb-3">
                        <label for="promotion_ratio" class="form-label">推广比例</label>
                        <select class="form-control" id="promotion_ratio" name="promotion_ratio" style="width: 200px;">
                            <option value="0.1" <?php echo $promotion_ratio == 0.1 ? 'selected' : ''; ?>>10%</option>
                            <option value="0.2" <?php echo $promotion_ratio == 0.2 ? 'selected' : ''; ?>>20%</option>
                            <option value="0.3" <?php echo $promotion_ratio == 0.3 ? 'selected' : ''; ?>>30%</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">保存设置</button>
                </form>
            </div>
        </div>

        <!-- 最近充值记录 -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">最近充值记录</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>用户</th>
                                <th>金额</th>
                                <th>状态</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_recharges as $recharge): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($recharge['username']); ?></td>
                                <td><?php echo number_format($recharge['amount'], 2); ?> 元</td>
                                <td><?php echo $recharge['is_paid'] ? '成功' : '待支付'; ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($recharge['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 最近上传记录 -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">最近上传记录</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>用户</th>
                                <th>图片路径</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_uploads as $upload): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($upload['username']); ?></td>
                                <td><?php echo htmlspecialchars($upload['image_path']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($upload['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ComfyUI服务器列表 -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">ComfyUI服务器列表</h5>
            </div>
            <div class="card-body">
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
                            <?php foreach ($comfyui_servers as $server): ?>
                            <tr>
                                <td><?php echo $server['id']; ?></td>
                                <td><?php echo htmlspecialchars($server['name']); ?></td>
                                <td><?php echo htmlspecialchars($server['url']); ?></td>
                                <td><?php echo $server['status'] ? '启用' : '禁用'; ?></td>
                                <td>
                                    <a href="comfyui_servers.php?action=edit&id=<?php echo $server['id']; ?>" 
                                       class="btn btn-sm btn-info">编辑</a>
                                    <a href="comfyui_servers.php?action=delete&id=<?php echo $server['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('确定要删除这个服务器吗？')">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
