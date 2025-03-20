<?php
require_once '../includes/common.php';
check_admin_login();

// 获取当前域名
$current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

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
    $where_conditions[] = "u.username LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取总记录数
$count_sql = "SELECT COUNT(*) as total FROM uploaded_images ui JOIN users u ON ui.user_id = u.id $where_clause";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// 获取上传记录
$sql = "SELECT ui.*, u.username 
        FROM uploaded_images ui 
        JOIN users u ON ui.user_id = u.id 
        $where_clause 
        ORDER BY ui.created_at DESC 
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
$upload_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取统计数据
$stats_sql = "SELECT 
    COUNT(*) as total_uploads,
    COUNT(DISTINCT user_id) as total_users
FROM uploaded_images";
$stats = $conn->query($stats_sql)->fetch_assoc();

// 确保统计数据不为 null
$stats['total_uploads'] = $stats['total_uploads'] ?? 0;
$stats['total_users'] = $stats['total_users'] ?? 0;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>上传记录 - 后台管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .image-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            cursor: pointer;
        }
        .modal-image {
            max-width: 100%;
            max-height: 80vh;
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
                        <a class="nav-link active" href="upload_logs.php">上传记录</a>
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
                        <h5 class="card-title mb-0">上传记录</h5>
                    </div>
                    <div class="card-body">
                        <!-- 统计信息 -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">总上传数</h6>
                                        <h3 class="card-text"><?php echo $stats['total_uploads']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">上传用户数</h6>
                                        <h3 class="card-text"><?php echo $stats['total_users']; ?></h3>
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

                        <!-- 上传记录表格 -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>预览图</th>
                                        <th>用户名</th>
                                        <th>上传时间</th>
                                        <th>更新时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upload_logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td>
                                            <img src="<?php echo $current_domain . htmlspecialchars($log['image_path']); ?>" 
                                                 class="image-preview" 
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#imageModal" 
                                                 data-image="<?php echo $current_domain . htmlspecialchars($log['image_path']); ?>"
                                                 alt="预览图">
                                        </td>
                                        <td><?php echo htmlspecialchars($log['username']); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['updated_at'])); ?></td>
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

    <!-- 图片预览模态框 -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">图片预览</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" class="modal-image" alt="预览图">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 图片预览功能
        document.querySelectorAll('.image-preview').forEach(img => {
            img.addEventListener('click', function() {
                const modalImage = document.querySelector('.modal-image');
                modalImage.src = this.dataset.image;
            });
        });
    </script>
</body>
</html> 