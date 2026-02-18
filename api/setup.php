<?php
/* ===================================================
   QR Code Generator — Database Setup
   Run this ONCE to create the required table.
   Access: https://qrcode.anasabdur.com/api/setup.php
   DELETE this file after setup!
   =================================================== */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    // Links table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `qr_links` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(10) NOT NULL UNIQUE,
            `target_url` TEXT NOT NULL,
            `hits` INT UNSIGNED DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `last_hit_at` DATETIME NULL,
            INDEX `idx_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Scan tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `qr_scans` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `link_id` INT UNSIGNED NOT NULL,
            `scanned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `country` VARCHAR(100) DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `region` VARCHAR(100) DEFAULT NULL,
            `latitude` DECIMAL(10,7) DEFAULT NULL,
            `longitude` DECIMAL(10,7) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `browser` VARCHAR(100) DEFAULT NULL,
            `browser_version` VARCHAR(50) DEFAULT NULL,
            `os` VARCHAR(100) DEFAULT NULL,
            `os_version` VARCHAR(50) DEFAULT NULL,
            `device_type` VARCHAR(20) DEFAULT NULL,
            `referer` TEXT DEFAULT NULL,
            `language` VARCHAR(50) DEFAULT NULL,
            INDEX `idx_link_id` (`link_id`),
            INDEX `idx_scanned_at` (`scanned_at`),
            INDEX `idx_country` (`country`),
            INDEX `idx_device` (`device_type`),
            CONSTRAINT `fk_scans_link` FOREIGN KEY (`link_id`) REFERENCES `qr_links`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Page visitors tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `page_visitors` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `session_id` VARCHAR(64) DEFAULT NULL,
            `page_url` VARCHAR(500) NOT NULL,
            `page_title` VARCHAR(255) DEFAULT NULL,
            `visited_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `country` VARCHAR(100) DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `region` VARCHAR(100) DEFAULT NULL,
            `latitude` DECIMAL(10,7) DEFAULT NULL,
            `longitude` DECIMAL(10,7) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `browser` VARCHAR(100) DEFAULT NULL,
            `browser_version` VARCHAR(50) DEFAULT NULL,
            `os` VARCHAR(100) DEFAULT NULL,
            `os_version` VARCHAR(50) DEFAULT NULL,
            `device_type` VARCHAR(20) DEFAULT NULL,
            `referer` TEXT DEFAULT NULL,
            `language` VARCHAR(50) DEFAULT NULL,
            `screen_width` INT UNSIGNED DEFAULT NULL,
            `screen_height` INT UNSIGNED DEFAULT NULL,
            `timezone` VARCHAR(100) DEFAULT NULL,
            INDEX `idx_visited_at` (`visited_at`),
            INDEX `idx_ip` (`ip_address`),
            INDEX `idx_country` (`country`),
            INDEX `idx_page` (`page_url`(191)),
            INDEX `idx_device` (`device_type`),
            INDEX `idx_session` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo '<h1 style="font-family:sans-serif;color:green;">&#10004; Database setup complete!</h1>';
    echo '<p style="font-family:sans-serif;">Tables created: <code>qr_links</code>, <code>qr_scans</code>, <code>page_visitors</code></p>';
    echo '<p style="font-family:sans-serif;color:red;"><strong>&#9888; DELETE this file (setup.php) now for security!</strong></p>';

} catch (Exception $e) {
    echo '<h1 style="font-family:sans-serif;color:red;">❌ Setup failed</h1>';
    echo '<p style="font-family:sans-serif;">' . htmlspecialchars($e->getMessage()) . '</p>';
}
