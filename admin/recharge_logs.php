<?php
require_once '../includes/common.php';
check_admin_login();

// 检查表结构
$table_check = $conn->query("SHOW COLUMNS FROM recharge_logs LIKE 'order_no'");
if ($table_check->num_rows == 0) {
    // 如果 order_no 字段不存在，添加该字段
    $conn->query("ALTER TABLE recharge_logs ADD COLUMN order_no varchar(50) NOT NULL COMMENT '订单号' AFTER amount");
    $conn->query("ALTER TABLE recharge_logs ADD UNIQUE KEY order_no (order_no)");
}

// 获取分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取筛选参数
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 构建查询条件
$where_conditions = [];
$params = [];
$types = '';

if ($status !== '') {
    $where_conditions[] = "r.is_paid = ?";
    $params[] = $status;
    $types .= 'i';
}

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR r.order_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取总记录数
$count_sql = "SELECT COUNT(*) as total FROM recharge_logs r JOIN users u ON r.user_id = u.id $where_clause";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// 获取充值记录
$sql = "SELECT r.*, u.username 
        FROM recharge_logs r 
        JOIN users u ON r.user_id = u.id 
        $where_clause 
        ORDER BY r.created_at DESC 
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
$recharge_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取统计数据
$stats_sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN is_paid = 1 THEN 1 ELSE 0 END) as paid_orders,
    SUM(CASE WHEN is_paid = 1 THEN amount ELSE 0 END) as total_amount
FROM recharge_logs";
$stats = $conn->query($stats_sql)->fetch_assoc();

// 确保统计数据不为 null
$stats['total_orders'] = $stats['total_orders'] ?? 0;
$stats['paid_orders'] = $stats['paid_orders'] ?? 0;
$stats['total_amount'] = $stats['total_amount'] ?? 0;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值记录 - 后台管理系统</title>
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
                        <a class="nav-link" href="comfyui_servers.php">ComfyUI服务器</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="comfyui_statistics.php">使用统计</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="recharge_logs.php">充值记录</a>
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
                        <h5 class="card-title mb-0">充值记录</h5>
                    </div>
                    <div class="card-body">
                        <!-- 统计信息 -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">总订单数</h6>
                                        <h3 class="card-text"><?php echo $stats['total_orders']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">已支付订单</h6>
                                        <h3 class="card-text"><?php echo $stats['paid_orders']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">总充值金额</h6>
                                        <h3 class="card-text">￥<?php echo number_format($stats['total_amount'], 2); ?></h3>
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
                                       placeholder="用户名/订单号">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">支付状态</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">全部</option>
                                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>已支付</option>
                                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>未支付</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">搜索</button>
                            </div>
                        </form>

                        <!-- 充值记录表格 -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>订单号</th>
                                        <th>用户名</th>
                                        <th>金额</th>
                                        <th>状态</th>
                                        <th>创建时间</th>
                                        <th>支付时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recharge_logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['order_no'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?></td>
                                        <td>￥<?php echo number_format($log['amount'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php if (isset($log['is_paid']) && $log['is_paid']): ?>
                                                <span class="badge bg-success">已支付</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">未支付</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo isset($log['created_at']) ? date('Y-m-d H:i:s', strtotime($log['created_at'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php echo (isset($log['is_paid']) && $log['is_paid'] && isset($log['updated_at'])) ? date('Y-m-d H:i:s', strtotime($log['updated_at'])) : '-'; ?>
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
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                        上一页
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 