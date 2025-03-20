<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin/login.php');
    exit;
}

// 处理服务器添加/编辑表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $server_name = $_POST['server_name'] ?? '';
    $server_url = $_POST['server_url'] ?? '';
    $max_users = isset($_POST['max_users']) ? intval($_POST['max_users']) : 0;
    $status = isset($_POST['status']) ? 1 : 0;
    
    if (isset($_POST['server_id'])) {
        // 更新现有服务器
        $server_id = intval($_POST['server_id']);
        $stmt = $conn->prepare("UPDATE comfyui_servers SET server_name = ?, server_url = ?, max_users = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssiii", $server_name, $server_url, $max_users, $status, $server_id);
    } else {
        // 添加新服务器
        $stmt = $conn->prepare("INSERT INTO comfyui_servers (server_name, server_url, max_users, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $server_name, $server_url, $max_users, $status);
    }
    $stmt->execute();
    $stmt->close();
}

// 处理删除服务器
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $server_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM comfyui_servers WHERE id = ?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $stmt->close();
}

// 获取所有服务器信息
$stmt = $conn->prepare("SELECT * FROM comfyui_servers ORDER BY id DESC");
$stmt->execute();
$result = $stmt->get_result();
$servers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 获取服务器统计数据
$stats = [];
foreach ($servers as $server) {
    // 获取总用户数
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as total_users FROM comfyui_usage WHERE server_id = ?");
    $stmt->bind_param("i", $server['id']);
    $stmt->execute();
    $total_users = $stmt->get_result()->fetch_assoc()['total_users'];
    
    // 获取今日生成量
    $stmt = $conn->prepare("SELECT COUNT(*) as daily_generations FROM comfyui_usage WHERE server_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->bind_param("i", $server['id']);
    $stmt->execute();
    $daily_generations = $stmt->get_result()->fetch_assoc()['daily_generations'];
    
    $stats[$server['id']] = [
        'total_users' => $total_users,
        'daily_generations' => $daily_generations
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>ComfyUI服务器管理</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin-bottom: 15px; }
        .status-active { color: green; }
        .status-inactive { color: red; }
    </style>
</head>
<body>
    <h1>ComfyUI服务器管理</h1>
    
    <form method="post">
        <div class="form-group">
            <label for="server_name">服务器名称：</label>
            <input type="text" id="server_name" name="server_name" required>
        </div>
        <div class="form-group">
            <label for="server_url">服务器地址：</label>
            <input type="url" id="server_url" name="server_url" required>
        </div>
        <div class="form-group">
            <label for="max_users">最大用户数：</label>
            <input type="number" id="max_users" name="max_users" min="0" required>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="status" value="1" checked>
                启用状态
            </label>
        </div>
        <input type="submit" value="添加服务器">
    </form>

    <h2>服务器列表</h2>
    <?php if (count($servers) > 0): ?>
        <table>
            <tr>
                <th>服务器名称</th>
                <th>服务器地址</th>
                <th>最大用户数</th>
                <th>当前用户数</th>
                <th>今日生成量</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
            <?php foreach ($servers as $server): ?>
                <tr>
                    <td><?php echo htmlspecialchars($server['server_name']); ?></td>
                    <td><?php echo htmlspecialchars($server['server_url']); ?></td>
                    <td><?php echo $server['max_users']; ?></td>
                    <td><?php echo $stats[$server['id']]['total_users']; ?></td>
                    <td><?php echo $stats[$server['id']]['daily_generations']; ?></td>
                    <td class="<?php echo $server['status'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $server['status'] ? '启用' : '禁用'; ?>
                    </td>
                    <td>
                        <a href="?edit=<?php echo $server['id']; ?>">编辑</a>
                        <a href="?delete=<?php echo $server['id']; ?>" onclick="return confirm('确定要删除此服务器吗？')">删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>暂无服务器信息。</p>
    <?php endif; ?>
</body>
</html> 