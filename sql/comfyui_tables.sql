-- ComfyUI服务器表
CREATE TABLE IF NOT EXISTS `comfyui_servers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL COMMENT '服务器名称',
    `url` varchar(255) NOT NULL COMMENT '服务器地址',
    `api_key` varchar(255) DEFAULT NULL COMMENT 'API密钥',
    `status` tinyint(1) DEFAULT 1 COMMENT '状态：1启用，0禁用',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ComfyUI服务器管理表';

-- ComfyUI使用统计表
CREATE TABLE IF NOT EXISTS `comfyui_statistics` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL COMMENT '服务器ID',
    `user_id` int(11) NOT NULL COMMENT '用户ID',
    `generation_count` int(11) DEFAULT 0 COMMENT '生成次数',
    `date` date NOT NULL COMMENT '统计日期',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_server_date` (`server_id`, `date`),
    KEY `idx_user_date` (`user_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ComfyUI使用统计表'; 