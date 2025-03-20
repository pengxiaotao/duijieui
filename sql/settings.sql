-- settings 表
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `upload_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '上传扣费金额',
    `promotion_ratio` decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '推广比例',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';

-- 添加默认设置数据
INSERT INTO `settings` (`upload_fee`, `promotion_ratio`) VALUES (0.10, 0.10);