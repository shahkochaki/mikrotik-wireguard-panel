-- ====================================================
-- WireGuard Panel - Database Schema
-- MikroTik RB951 WireGuard Management Panel
-- ====================================================

CREATE DATABASE IF NOT EXISTS `wireguard_panel`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `wireguard_panel`;

-- --------------------------------------------------
-- Admin Users (panel login)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `email`      VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------
-- WireGuard Peers/Clients
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `wg_users` (
  `id`                INT(11)      NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(100) NOT NULL COMMENT 'نام نمایشی',
  `username`          VARCHAR(50)  NOT NULL COMMENT 'نام کاربری یکتا',
  `mikrotik_peer_id`  VARCHAR(50)  DEFAULT NULL COMMENT 'شناسه پیر در میکروتیک',
  `mikrotik_queue_id` VARCHAR(50)  DEFAULT NULL COMMENT 'شناسه کیو در میکروتیک',
  `public_key`        VARCHAR(255) DEFAULT NULL,
  `private_key`       VARCHAR(255) DEFAULT NULL,
  `preshared_key`     VARCHAR(255) DEFAULT NULL,
  `allowed_address`   VARCHAR(50)  NOT NULL COMMENT 'IP اختصاصی مثل 10.0.0.2/32',
  `download_speed`    VARCHAR(20)  NOT NULL DEFAULT '10M' COMMENT 'سرعت دانلود',
  `upload_speed`      VARCHAR(20)  NOT NULL DEFAULT '10M' COMMENT 'سرعت آپلود',
  `expiry_date`       DATETIME     DEFAULT NULL COMMENT 'تاریخ انقضا',
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `notes`             TEXT         DEFAULT NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `allowed_address` (`allowed_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------
-- Panel Settings
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------
-- Default Settings
-- --------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('wg_interface',        'wireguard1'),
  ('wg_server_public_key',''),
  ('wg_endpoint',         ''),
  ('wg_listen_port',      '13231'),
  ('wg_dns',              '8.8.8.8'),
  ('wg_allowed_ips',      '0.0.0.0/0'),
  ('wg_subnet',           '10.0.0.0/24'),
  ('wg_server_ip',        '10.0.0.1'),
  ('mt_host',             '192.168.88.1'),
  ('mt_user',             'admin'),
  ('mt_pass',             ''),
  ('mt_port',             '8728');

-- --------------------------------------------------
-- Default Admin  (username: admin | password: admin123)
-- --------------------------------------------------
INSERT INTO `admins` (`username`, `password`) VALUES
  ('admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9WMrbu');
