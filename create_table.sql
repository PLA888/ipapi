-- 创建IP访问记录表
CREATE TABLE IF NOT EXISTS `ip_access` (
  `ip` varchar(45) NOT NULL COMMENT 'IP地址',
  `first_access` datetime NOT NULL COMMENT '首次访问时间',
  `last_access` datetime NOT NULL COMMENT '最后访问时间',
  `access_count` int(11) NOT NULL DEFAULT '1' COMMENT '访问次数',
  `location` varchar(255) DEFAULT NULL COMMENT 'IP地理位置',
  `device_info` varchar(255) DEFAULT NULL COMMENT '设备信息',
  PRIMARY KEY (`ip`),
  KEY `idx_last_access` (`last_access`) COMMENT '最后访问时间索引'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IP访问记录表'; 

-- 添加管理员密码表
CREATE TABLE IF NOT EXISTS `admin_users` (
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认管理员账号，密码为 admin123456
INSERT INTO `admin_users` (`username`, `password`) VALUES 
('admin', '$2b$12$vPMEBxyI5FKF7ju0CQ1UQOCUcyjtiZatwRLug9ixCCzMH4nKVe3ma'); 

-- 添加IP地理位置缓存表
CREATE TABLE IF NOT EXISTS `ip_location_cache` (
  `ip` varchar(45) NOT NULL COMMENT 'IP地址',
  `location` varchar(255) NOT NULL COMMENT '地理位置信息',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`ip`),
  KEY `idx_update_time` (`update_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IP地理位置缓存表'; 