<?php
require_once '../includes/common.php';
check_admin_login();

// 处理添加/编辑服务器的表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $url = trim($_POST['url']);
    $api_key = trim($_POST['api_key']);
    $status = isset($_POST['status']) ? 1 : 0;
    
    if (empty($name) || empty($url)) {
        $error = "服务器名称和地址不能为空";
    } else {
        if (isset($_POST['id'])) {
            // 更新现有服务器
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE comfyui_servers SET name = ?, url = ?, api_key = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssii", $name, $url, $api_key, $status, $id);
            $success = $stmt->execute();
            if ($success) {
                log_admin_action($_SESSION['admin_id'], 'update_server', "更新服务器: $name");
            }
        } else {
            // 添加新服务器
            $stmt = $conn->prepare("INSERT INTO comfyui_servers (name, url, api_key, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $url, $api_key, $status);
            $success = $stmt->execute();
            if ($success) {
                log_admin_action($_SESSION['admin_id'], 'add_server', "添加服务器: $name");
            }
        }
        
        if ($success) {
            header('Location: comfyui_servers.php');
            exit;
        } else {
            $error = "操作失败：" . $conn->error;
        }
    }
}

// 处理删除服务器的请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM comfyui_servers WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        log_admin_action($_SESSION['admin_id'], 'delete_server', "删除服务器ID: $id");
    }
    header('Location: comfyui_servers.php');
    exit;
}

// 获取要编辑的服务器信息
$server = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM comfyui_servers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $server = $stmt->get_result()->fetch_assoc();
}

// 获取所有服务器列表
$stmt = $conn->prepare("SELECT * FROM comfyui_servers ORDER BY id DESC");
$stmt->execute();
$servers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ComfyUI服务器管理 - 后台管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <a class="nav-link active" href="comfyui_servers.php">ComfyUI服务器</a>
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
        <div class="row">
            <div class="col-md-4">
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
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $server ? htmlspecialchars($server['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="url" class="form-label">服务器地址</label>
                                <input type="url" class="form-control" id="url" name="url" 
                                       value="<?php echo $server ? htmlspecialchars($server['url']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="api_key" class="form-label">API密钥（可选）</label>
                                <input type="text" class="form-control" id="api_key" name="api_key" 
                                       value="<?php echo $server ? htmlspecialchars($server['api_key']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="status" name="status" 
                                       <?php echo $server && $server['status'] ? 'checked' : ''; ?>>
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
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">服务器列表</h5>
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
                                    <?php foreach ($servers as $s): ?>
                                    <tr>
                                        <td><?php echo $s['id']; ?></td>
                                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                                        <td><?php echo htmlspecialchars($s['url']); ?></td>
                                        <td><?php echo $s['status'] ? '启用' : '禁用'; ?></td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $s['id']; ?>" 
                                               class="btn btn-sm btn-info">编辑</a>
                                            <a href="?action=delete&id=<?php echo $s['id']; ?>" 
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 