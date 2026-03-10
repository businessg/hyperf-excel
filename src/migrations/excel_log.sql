CREATE TABLE `excel_log` (
     `id` bigint unsigned NOT NULL AUTO_INCREMENT,
     `token` varchar(64) NOT NULL DEFAULT '',
     `type` enum('export','import') NOT NULL DEFAULT 'export' COMMENT '类型:export导出import导入',
     `config_class` varchar(250) NOT NULL DEFAULT '',
     `config` json DEFAULT NULL COMMENT 'config信息',
     `service_name` varchar(20) NOT NULL DEFAULT '' COMMENT '服务名',
     `sheet_progress` json DEFAULT NULL COMMENT '页码进度',
     `progress` json DEFAULT NULL COMMENT '总进度信息',
     `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态:1.待处理2.正在处理3.处理完成4.处理失败5.输出中6.完成',
     `data` json NOT NULL COMMENT '数据信息',
     `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
     `url` varchar(300) NOT NULL DEFAULT '' COMMENT 'url地址',
     `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
     `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
     PRIMARY KEY (`id`),
     UNIQUE KEY `uniq_token` (`token`)
) ENGINE=InnoDB  COMMENT='导入导出日志';