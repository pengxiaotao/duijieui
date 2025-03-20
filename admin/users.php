<?php
require_once '../includes/common.php';
check_admin_login();

// 处理删除用户的请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = intval($_POST['user_id']);
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 删除用户相关的所有记录
        $conn->query("DELETE FROM uploaded_images WHERE user_id = $user_id");
        $conn->query("DELETE FROM recharge_logs WHERE user_id = $user_id");
        $conn->query("DELETE FROM comfyui_usage_logs WHERE user_id = $user_id");
        $conn->query("DELETE FROM comfyui_statistics WHERE user_id = $user_id");
        $conn->query("DELETE FROM users WHERE id = $user_id");
        
        // 提交事务
        $conn->commit();
        die(json_encode(['success' => true, 'message' => '删除成功']));
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        die(json_encode(['success' => false, 'message' => '删除失败：' . $e->getMessage()]));
    }
}

// 处理编辑用户信息的请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $user_id = intval($_POST['user_id']);
    $username = trim($_POST['username']);
    $balance = floatval($_POST['balance']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // 检查用户名是否已存在（排除当前用户）
    $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('si', $username, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        die(json_encode(['success' => false, 'message' => '用户名已存在']));
    }
    
    // 检查邮箱是否已存在（排除当前用户）
    $check_email_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
    $check_email_stmt = $conn->prepare($check_email_sql);
    $check_email_stmt->bind_param('si', $email, $user_id);
    $check_email_stmt->execute();
    if ($check_email_stmt->get_result()->num_rows > 0) {
        die(json_encode(['success' => false, 'message' => '邮箱已存在']));
    }
    
    // 构建更新SQL
    if (!empty($password)) {
        // 如果提供了新密码，则更新密码
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET username = ?, balance = ?, email = ?, password = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('sdssi', $username, $balance, $email, $password_hash, $user_id);
    } else {
        // 如果没有提供新密码，则不更新密码
        $update_sql = "UPDATE users SET username = ?, balance = ?, email = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('sdsi', $username, $balance, $email, $user_id);
    }
    
    if ($update_stmt->execute()) {
        die(json_encode(['success' => true, 'message' => '更新成功']));
    } else {
        die(json_encode(['success' => false, 'message' => '更新失败']));
    }
}

// 获取分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取筛选参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 构建查询条件
$where_conditions = [];
$params = [];
$types = '';

if ($search) {
    $where_conditions[] = "username LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取总记录数
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// 获取用户列表
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM uploaded_images WHERE user_id = u.id) as upload_count,
        (SELECT COUNT(*) FROM recharge_logs WHERE user_id = u.id AND is_paid = 1) as recharge_count,
        (SELECT SUM(amount) FROM recharge_logs WHERE user_id = u.id AND is_paid = 1) as total_recharge
        FROM users u 
        $where_clause 
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// 添加分页参数
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取统计数据
$stats_sql = "SELECT 
    COUNT(*) as total_users,
    SUM(balance) as total_balance,
    COUNT(DISTINCT CASE WHEN balance > 0 THEN id END) as active_users
FROM users";
$stats = $conn->query($stats_sql)->fetch_assoc();

// 确保统计数据不为 null
$stats['total_users'] = $stats['total_users'] ?? 0;
$stats['total_balance'] = $stats['total_balance'] ?? 0;
$stats['active_users'] = $stats['active_users'] ?? 0;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 后台管理系统</title>
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
                        <a class="nav-link active" href="users.php">用户管理</a>
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
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">用户管理</h5>
                    </div>
                    <div class="card-body">
                        <!-- 统计信息 -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">总用户数</h6>
                                        <h3 class="card-text"><?php echo $stats['total_users']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">活跃用户数</h6>
                                        <h3 class="card-text"><?php echo $stats['active_users']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">总余额</h6>
                                        <h3 class="card-text">￥<?php echo number_format($stats['total_balance'], 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 搜索表单 -->
                        <form method="get" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label for="search" class="form-label">搜索</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="用户名">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">搜索</button>
                            </div>
                        </form>

                        <!-- 用户列表 -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>用户名</th>
                                        <th>邮箱</th>
                                        <th>余额</th>
                                        <th>上传次数</th>
                                        <th>充值次数</th>
                                        <th>总充值金额</th>
                                        <th>注册时间</th>
                                        <th>最后更新</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                        <td>￥<?php echo number_format($user['balance'], 2); ?></td>
                                        <td><?php echo $user['upload_count']; ?></td>
                                        <td><?php echo $user['recharge_count']; ?></td>
                                        <td>￥<?php echo number_format($user['total_recharge'] ?? 0, 2); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($user['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($user['updated_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary edit-user" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editUserModal"
                                                    data-user='<?php echo json_encode($user); ?>'>
                                                编辑
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger delete-user" 
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                删除
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分页 -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                                        上一页
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                                        下一页
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 编辑用户模态框 -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">编辑用户信息</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">用户名</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">邮箱</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_balance" class="form-label">余额</label>
                            <input type="number" class="form-control" id="edit_balance" name="balance" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">新密码</label>
                            <input type="password" class="form-control" id="edit_password" name="password" 
                                   placeholder="留空表示不修改密码">
                            <div class="form-text">如果不修改密码，请留空</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveUserBtn">保存</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">确认删除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>确定要删除用户 <span id="deleteUsername" class="fw-bold"></span> 吗？</p>
                    <p class="text-danger">此操作将删除该用户的所有数据，包括上传记录、充值记录等，且无法恢复！</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">确认删除</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 删除用户功能
        let deleteUserId = null;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        
        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', function() {
                deleteUserId = this.dataset.userId;
                document.getElementById('deleteUsername').textContent = this.dataset.username;
                deleteModal.show();
            });
        });

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (!deleteUserId) return;

            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', deleteUserId);

            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('删除成功');
                    location.reload();
                } else {
                    alert(data.message || '删除失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('删除失败');
            })
            .finally(() => {
                deleteModal.hide();
            });
        });

        // 编辑用户功能
        document.querySelectorAll('.edit-user').forEach(button => {
            button.addEventListener('click', function() {
                const userData = JSON.parse(this.dataset.user);
                document.getElementById('edit_user_id').value = userData.id;
                document.getElementById('edit_username').value = userData.username;
                document.getElementById('edit_email').value = userData.email || '';
                document.getElementById('edit_balance').value = userData.balance;
                document.getElementById('edit_password').value = ''; // 清空密码字段
            });
        });

        // 保存用户信息
        document.getElementById('saveUserBtn').addEventListener('click', function() {
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);

            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('更新成功');
                    location.reload();
                } else {
                    alert(data.message || '更新失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('更新失败');
            });
        });
    </script>
</body>
</html> 