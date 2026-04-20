CREATE DATABASE IF NOT EXISTS `loader_db`;
USE `loader_db`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL, 
    `email` VARCHAR(100) DEFAULT NULL,
    `discord_id` VARCHAR(30) DEFAULT NULL, 
    `hwid` VARCHAR(64) DEFAULT NULL,       
    `reg_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `is_banned` TINYINT(1) NOT NULL DEFAULT 0,
    `ban_reason` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX (`username`),
    INDEX (`hwid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL, 
    `admin_level` INT(1) NOT NULL DEFAULT 1,
    `can_ban` TINYINT(1) NOT NULL DEFAULT 0,
    `can_announce` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Хеш = password_hash("admin123", PASSWORD_DEFAULT)
INSERT INTO `users` (`username`, `password`, `email`, `reg_date`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@goddev.com', NOW());

INSERT INTO `admins` (`user_id`, `admin_level`, `can_ban`, `can_announce`) 
SELECT id, 2, 1, 1 FROM `users` WHERE `username` = 'admin';