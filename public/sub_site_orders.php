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

// 获取分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 获取总订单数
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sub_site_orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// 获取订单列表
$stmt = $conn->prepare("
    SELECT o.*, p.name as package_name, p.duration 
    FROM sub_site_orders o 
    JOIN sub_site_packages p ON o.package_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $per_page, $offset);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 计算总页数
$total_pages = ceil($total / $per_page);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分站订单</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .orders-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-card {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .order-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .order-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.875rem;
        }
        .status-paid {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-unpaid {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="orders-container">
            <h2 class="text-center mb-4">分站订单</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                    <p class="text-muted">暂无订单记录</p>
                    <a href="sub_site_renew.php" class="btn btn-primary">立即续费</a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">订单号：<?php echo htmlspecialchars($order['order_no']); ?></h5>
                            <span class="order-status <?php echo $order['is_paid'] ? 'status-paid' : 'status-unpaid'; ?>">
                                <?php echo $order['is_paid'] ? '已支付' : '未支付'; ?>
                            </span>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1">套餐名称：<?php echo htmlspecialchars($order['package_name']); ?></p>
                                <p class="mb-1">套餐时长：<?php echo $order['duration']; ?> 天</p>
                                <p class="mb-0">创建时间：<?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1">支付金额：<?php echo number_format($order['amount'], 2); ?> 元</p>
                                <?php if ($order['is_paid']): ?>
                                    <p class="mb-1">支付时间：<?php echo date('Y-m-d H:i:s', strtotime($order['paid_time'])); ?></p>
                                    <p class="mb-0">交易号：<?php echo htmlspecialchars($order['trade_no']); ?></p>
                                <?php else: ?>
                                    <a href="sub_site_pay.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">立即支付</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">上一页</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">下一页</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-secondary">返回首页</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 