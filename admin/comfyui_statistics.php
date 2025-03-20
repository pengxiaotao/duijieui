<?php
require_once '../includes/common.php';
check_admin_login();

// 获取日期范围
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// 获取服务器列表
$stmt = $conn->prepare("SELECT * FROM comfyui_servers ORDER BY name");
$stmt->execute();
$servers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取统计数据
$stmt = $conn->prepare("
    SELECT 
        s.name,
        COUNT(DISTINCT st.user_id) as total_users,
        SUM(st.generation_count) as total_generations,
        MAX(st.date) as last_update
    FROM comfyui_servers s
    LEFT JOIN comfyui_statistics st ON s.id = st.server_id
    WHERE st.date BETWEEN ? AND ?
    GROUP BY s.id
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 获取每日生成量统计
$stmt = $conn->prepare("
    SELECT 
        date,
        SUM(generation_count) as total_generations,
        COUNT(DISTINCT user_id) as total_users
    FROM comfyui_statistics
    WHERE date BETWEEN ? AND ?
    GROUP BY date
    ORDER BY date DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ComfyUI使用统计 - 后台管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a class="nav-link active" href="comfyui_statistics.php">使用统计</a>
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
                        <h5 class="card-title mb-0">使用统计</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">开始日期</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">结束日期</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $end_date; ?>">
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 准备图表数据
    const dates = <?php echo json_encode(array_column($daily_stats, 'date')); ?>;
    const generations = <?php echo json_encode(array_column($daily_stats, 'total_generations')); ?>;
    const users = <?php echo json_encode(array_column($daily_stats, 'total_users')); ?>;

    // 创建图表
    const ctx = document.getElementById('dailyStatsChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: '每日生成量',
                    data: generations,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                },
                {
                    label: '每日用户数',
                    data: users,
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    </script>
</body>
</html> 