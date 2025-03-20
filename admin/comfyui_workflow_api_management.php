<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// 后续添加具体的添加、设置、付费、修改、编辑和删除逻辑

// 添加工作流API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_api'])) {
    $name = $_POST['name'];
    $url = $_POST['url'];
    $price = $_POST['price'];

    $stmt = $conn->prepare("INSERT INTO comfyui_workflow_apis (name, url, price) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $name, $url, $price);
    $stmt->execute();
    $stmt->close();
}

// 设置付费
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_price'])) {
    $api_id = $_POST['api_id'];
    $new_price = $_POST['new_price'];

    $stmt = $conn->prepare("UPDATE comfyui_workflow_apis SET price = ? WHERE id = ?");
    $stmt->bind_param("di", $new_price, $api_id);
    $stmt->execute();
    $stmt->close();
}

// 修改API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_api'])) {
    $api_id = $_POST['api_id'];
    $new_name = $_POST['new_name'];
    $new_url = $_POST['new_url'];

    $stmt = $conn->prepare("UPDATE comfyui_workflow_apis SET name = ?, url = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_name, $new_url, $api_id);
    $stmt->execute();
    $stmt->close();
}

// 删除API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_api'])) {
    $api_id = $_POST['api_id'];

    $stmt = $conn->prepare("DELETE FROM comfyui_workflow_apis WHERE id = ?");
    $stmt->bind_param("i", $api_id);
    $stmt->execute();
    $stmt->close();
}

// 获取所有工作流API
$stmt = $conn->prepare("SELECT * FROM comfyui_workflow_apis");
$stmt->execute();
$apis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ComfyUI工作流API管理</title>
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
                        <a class="nav-link" href="comfyui_servers.php">ComfyUI服务器</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="comfyui_workflow_api_management.php">ComfyUI工作流API管理</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h2>添加ComfyUI工作流API</h2>
        <form method="post">
            <div class="mb-3">
                <label for="name" class="form-label">名称</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="url" class="form-label">URL</label>
                <input type="text" class="form-control" id="url" name="url" required>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">价格</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" required>
            </div>
            <button type="submit" class="btn btn-primary" name="add_api">添加</button>
        </form>
        <h2>ComfyUI工作流API列表</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>URL</th>
                    <th>价格</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apis as $api): ?>
                    <tr>
                        <td><?php echo $api['id']; ?></td>
                        <td><?php echo $api['name']; ?></td>
                        <td><?php echo $api['url']; ?></td>
                        <td><?php echo $api['price']; ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="api_id" value="<?php echo $api['id']; ?>">
                                <input type="number" name="new_price" step="0.01" placeholder="新价格">
                                <button type="submit" class="btn btn-sm btn-warning" name="set_price">设置付费</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $api['id']; ?>">修改</button>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="api_id" value="<?php echo $api['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" name="delete_api" onclick="return confirm('确定要删除这个API吗？')">删除</button>
                            </form>
                        </td>
                    </tr>
                    <!-- 修改模态框 -->
                    <div class="modal fade" id="editModal<?php echo $api['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $api['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editModalLabel<?php echo $api['id']; ?>">修改API</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="post">
                                        <input type="hidden" name="api_id" value="<?php echo $api['id']; ?>">
                                        <div class="mb-3">
                                            <label for="new_name" class="form-label">新名称</label>
                                            <input type="text" class="form-control" id="new_name" name="new_name" value="<?php echo $api['name']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_url" class="form-label">新URL</label>
                                            <input type="text" class="form-control" id="new_url" name="new_url" value="<?php echo $api['url']; ?>" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary" name="edit_api">保存修改</button>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>