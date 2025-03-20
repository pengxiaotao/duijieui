-- 创建管理员日志表
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL COMMENT '管理员ID',
    `action` varchar(255) NOT NULL COMMENT '操作类型',
    `details` text COMMENT '详细信息',
    `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `admin_id` (`admin_id`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员操作日志表'; 