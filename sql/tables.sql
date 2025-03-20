-- 用户表
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL COMMENT '用户名',
    `password` varchar(255) NOT NULL COMMENT '密码哈希',
    `email` varchar(100) NOT NULL UNIQUE COMMENT '邮箱',
    `balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '账户余额',
    `invite_code` varchar(20) NOT NULL UNIQUE COMMENT '邀请码',
    `invited_by` int(11) DEFAULT NULL COMMENT '邀请人ID',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 上传图片表
CREATE TABLE IF NOT EXISTS `uploaded_images` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL COMMENT '用户ID',
    `image_path` varchar(255) NOT NULL COMMENT '图片路径',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `uploaded_images_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='上传图片表';

-- 充值记录表
CREATE TABLE IF NOT EXISTS `recharge_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL COMMENT '用户ID',
    `amount` decimal(10,2) NOT NULL COMMENT '充值金额',
    `order_no` varchar(50) NOT NULL COMMENT '订单号',
    `is_paid` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已支付',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `order_no` (`order_no`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `recharge_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值记录表';

-- ComfyUI服务器表
CREATE TABLE IF NOT EXISTS `comfyui_servers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT '服务器名称',
    `url` varchar(255) NOT NULL COMMENT '服务器地址',
    `api_key` varchar(255) DEFAULT NULL COMMENT 'API密钥',
    `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1启用，0禁用',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ComfyUI服务器管理表';

-- ComfyUI使用记录表
CREATE TABLE IF NOT EXISTS `comfyui_usage_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL COMMENT '服务器ID',
    `user_id` int(11) NOT NULL COMMENT '用户ID',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `server_id` (`server_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `comfyui_usage_logs_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `comfyui_servers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `comfyui_usage_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ComfyUI使用记录表';

-- ComfyUI统计表
CREATE TABLE IF NOT EXISTS `comfyui_statistics` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL COMMENT '服务器ID',
    `user_id` int(11) NOT NULL COMMENT '用户ID',
    `generation_count` int(11) NOT NULL DEFAULT '0' COMMENT '生成次数',
    `date` date NOT NULL COMMENT '统计日期',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `server_user_date` (`server_id`, `user_id`, `date`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `comfyui_statistics_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `comfyui_servers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `comfyui_statistics_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ComfyUI使用统计表';

-- 管理员表
CREATE TABLE IF NOT EXISTS `admins` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL COMMENT '用户名',
    `password` varchar(255) NOT NULL COMMENT '密码哈希',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

-- 插入默认管理员账号 (密码: admin123)
INSERT INTO `admins` (`username`, `password`) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- 分站表
CREATE TABLE IF NOT EXISTS sub_sites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    domain VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    epay_id VARCHAR(255) NOT NULL,
    epay_key VARCHAR(255) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用，0禁用',
    expire_time DATETIME NOT NULL COMMENT '到期时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_domain (domain),
    UNIQUE KEY unique_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 分站套餐表
CREATE TABLE IF NOT EXISTS sub_site_packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COMMENT '套餐名称',
    price DECIMAL(10,2) NOT NULL COMMENT '套餐价格',
    duration INT NOT NULL COMMENT '时长(天)',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用，0禁用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 插入默认分站套餐
INSERT INTO sub_site_packages (name, price, duration) VALUES 
('月度套餐', 99.00, 30),
('年度套餐', 999.00, 365);

-- 分站订单表
CREATE TABLE IF NOT EXISTS sub_site_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    order_no VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 0 COMMENT '状态：0未支付，1已支付',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (package_id) REFERENCES sub_site_packages(id),
    UNIQUE KEY unique_order_no (order_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 