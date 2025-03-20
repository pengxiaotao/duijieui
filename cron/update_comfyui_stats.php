<?php
require_once('../includes/common.php');

// 获取昨天的日期
$yesterday = date('Y-m-d', strtotime('-1 day'));

// 获取所有启用的服务器
$servers = $db->query("SELECT id FROM comfyui_servers WHERE status = 1")->fetchAll(PDO::FETCH_ASSOC);

foreach ($servers as $server) {
    // 获取昨天的使用记录
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT user_id) as user_count,
            COUNT(*) as generation_count
        FROM comfyui_usage_logs
        WHERE server_id = ? 
        AND DATE(created_at) = ?
    ");
    $stmt->execute([$server['id'], $yesterday]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 更新或插入统计数据
    $stmt = $db->prepare("
        INSERT INTO comfyui_statistics (server_id, date, generation_count)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
        generation_count = VALUES(generation_count)
    ");
    $stmt->execute([
        $server['id'],
        $yesterday,
        $stats['generation_count']
    ]);
}

// 记录日志
$log_file = __DIR__ . '/../logs/comfyui_stats.log';
$log_message = date('Y-m-d H:i:s') . " - Updated statistics for {$yesterday}\n";
file_put_contents($log_file, $log_message, FILE_APPEND); 