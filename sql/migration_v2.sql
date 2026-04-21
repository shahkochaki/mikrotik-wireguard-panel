-- ====================================================
-- Migration v2: Bandwidth tracking & data limit quota
-- Run once after upgrading from v1:
--   mysql -u root -p wireguard_panel < sql/migration_v2.sql
-- ====================================================

ALTER TABLE `wg_users`
    ADD COLUMN IF NOT EXISTS `data_limit_gb`          DECIMAL(10,3) DEFAULT NULL
        COMMENT 'حجم مجاز به گیگابایت (NULL = نامحدود)',
    ADD COLUMN IF NOT EXISTS `rx_bytes`               BIGINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'بایت دریافتی (همگام‌سازی از روتر)',
    ADD COLUMN IF NOT EXISTS `tx_bytes`               BIGINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'بایت ارسالی (همگام‌سازی از روتر)',
    ADD COLUMN IF NOT EXISTS `last_handshake`         DATETIME DEFAULT NULL
        COMMENT 'آخرین زمان handshake',
    ADD COLUMN IF NOT EXISTS `endpoint_address`       VARCHAR(100) DEFAULT NULL
        COMMENT 'آدرس endpoint پیکربندی شده',
    ADD COLUMN IF NOT EXISTS `endpoint_port`          SMALLINT UNSIGNED DEFAULT NULL
        COMMENT 'پورت endpoint پیکربندی شده',
    ADD COLUMN IF NOT EXISTS `current_endpoint_address` VARCHAR(100) DEFAULT NULL
        COMMENT 'آدرس IP فعلی کلاینت',
    ADD COLUMN IF NOT EXISTS `current_endpoint_port`  SMALLINT UNSIGNED DEFAULT NULL
        COMMENT 'پورت فعلی کلاینت';
