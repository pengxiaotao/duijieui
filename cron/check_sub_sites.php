<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// 检查过期的分站
$stmt = $conn->prepare("UPDATE sub_sites SET status = 0 WHERE status = 1 AND expire_time < NOW()");
$stmt->execute();
$affected_rows = $stmt->affected_rows;
$stmt->close();

if ($affected_rows > 0) {
    log_message("已禁用 {$affected_rows} 个过期分站");
}

// 获取即将过期的分站（7天内）
$stmt = $conn->prepare("
    SELECT s.*, u.username, u.email 
    FROM sub_sites s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.status = 1 
    AND s.expire_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
");
$stmt->execute();
$expiring_sites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 发送提醒邮件
foreach ($expiring_sites as $site) {
    $days_left = ceil((strtotime($site['expire_time']) - time()) / (24 * 3600));
    $subject = "分站即将过期提醒";
    $message = "尊敬的用户 {$site['username']}：\n\n";
    $message .= "您的分站 {$site['domain']} 将在 {$days_left} 天后过期。\n";
    $message .= "过期时间：" . date('Y-m-d H:i:s', strtotime($site['expire_time'])) . "\n\n";
    $message .= "请及时续费以避免服务中断。\n";
    $message .= "如有任何问题，请联系客服。\n\n";
    $message .= "祝您使用愉快！";
    
    // 发送邮件
    send_email($site['email'], $subject, $message);
    log_message("已发送过期提醒邮件给用户 {$site['username']}，域名: {$site['domain']}");
} 