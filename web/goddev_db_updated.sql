-- phpMyAdmin SQL Dump
-- version 5.2.1-1.el8
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Апр 07 2026 г., 15:21
-- Версия сервера: 8.0.45-cll-lve
-- Версия PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `goddev_db`
--

-- --------------------------------------------------------

--
-- Структура таблицы `admin_action_logs`
--

CREATE TABLE `admin_action_logs` (
  `id` int NOT NULL,
  `admin_user_id` int DEFAULT NULL,
  `target_user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `bans`
--

CREATE TABLE `bans` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `hwid` varchar(64) DEFAULT NULL,
  `reason` text,
  `banned_by` int DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `bans`
--

INSERT INTO `bans` (`id`, `user_id`, `hwid`, `reason`, `banned_by`, `expires_at`, `created_at`) VALUES
(1, 9, NULL, 'ладно давай пока', 4, '2026-04-06 15:41:17', '2026-04-06 15:24:44');

-- --------------------------------------------------------

--
-- Структура таблицы `licenses`
--

CREATE TABLE `licenses` (
  `id` int NOT NULL,
  `license_key` varchar(64) NOT NULL,
  `user_id` int DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('starter','standard','pro') DEFAULT 'standard',
  `duration_days` int NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activated_at` timestamp NULL DEFAULT NULL,
  `last_used` timestamp NULL DEFAULT NULL,
  `hwid_locked` varchar(64) DEFAULT NULL,
  `status` enum('active','expired','revoked') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `licenses`
--

INSERT INTO `licenses` (`id`, `license_key`, `user_id`, `product_name`, `product_type`, `duration_days`, `expires_at`, `created_at`, `activated_at`, `last_used`, `hwid_locked`, `status`) VALUES
(1, 'SOUL-TEST-AAAA-1111', NULL, 'GOD DEV Premium', 'standard', 30, '2026-05-02 19:22:43', '2026-04-02 19:22:43', NULL, NULL, NULL, 'active'),
(2, 'SOUL-TEST-BBBB-2222', NULL, 'GOD DEV Pro', 'pro', 90, '2026-07-01 19:22:43', '2026-04-02 19:22:43', NULL, NULL, NULL, 'active'),
(3, 'SOUL-TEST-CCCC-3333', NULL, 'GOD DEV Starter', 'starter', 7, '2026-04-09 19:22:43', '2026-04-02 19:22:43', NULL, NULL, NULL, 'active'),
(4, 'SOUL-4B3C-46E2-B249-1641', NULL, 'GOD DEV Standard', 'standard', 30, '2026-05-02 19:42:19', '2026-04-02 19:42:19', NULL, NULL, NULL, 'active'),
(5, 'GOD-1983-7964-26F4-2C98', 4, 'GOD DEV Standard (30 days)', 'standard', 30, '2026-05-03 03:57:49', '2026-04-03 03:57:49', NULL, NULL, NULL, 'active'),
(6, 'GOD-FF45-7B7C-E842-DE00', 4, 'GOD DEV Pro (90 days)', 'pro', 180, '2026-09-30 19:33:09', '2026-04-03 19:33:09', NULL, NULL, NULL, 'active'),
(7, 'GOD-BB11-FF0D-7DB8-1DED', 4, 'GOD DEV Pro (90 days)', 'pro', 90, '2026-07-03 17:58:58', '2026-04-04 17:58:58', NULL, NULL, NULL, 'active'),
(8, 'GOD-A197-AD5C-E9EF-F309', 4, 'GOD DEV Standard (30 days)', 'standard', 30, '2026-05-05 12:13:03', '2026-04-05 12:13:03', NULL, NULL, NULL, 'active'),
(9, 'GOD-2B3C-4FDF-4493-FC47', 4, 'GOD DEV Standard (30 days)', 'standard', 60, '2026-06-04 12:26:17', '2026-04-05 12:26:17', NULL, NULL, NULL, 'active'),
(10, 'GOD-9802-615D-F981-B2C7', 6, 'GOD DEV Pro (90 днів)', 'pro', 90, '2026-07-04 20:11:16', '2026-04-05 20:11:16', NULL, NULL, NULL, 'active');

-- --------------------------------------------------------

--
-- Структура таблицы `loader_logs`
--

CREATE TABLE `loader_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `hwid` varchar(64) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `loader_logs`
--

INSERT INTO `loader_logs` (`id`, `user_id`, `username`, `hwid`, `action`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'haunt', '', 'failed_login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 19:24:45'),
(2, 2, 'hauntz', '', 'successful_login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 19:24:50'),
(3, NULL, 'hauntz', '', 'failed_login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:51:37'),
(4, NULL, 'admin', '', 'failed_login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:52:35'),
(5, 3, 'test', '', 'successful_login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:52:53'),
(6, NULL, 'hauntz', '', 'failed_login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:56:24'),
(7, NULL, 'hauntz', '', 'failed_login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:56:39'),
(8, NULL, 'hauntz', '', 'failed_login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:56:41'),
(9, 3, 'test', '8F582142FC0FAE1AF8F98DBB3EB36349', 'successful_login', '188.163.26.49', 'unknown', '2026-04-03 08:52:04'),
(10, 3, 'test', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:53:25'),
(11, 4, 'hauntz', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:53:53'),
(12, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-03 08:54:28'),
(13, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-03 08:57:54'),
(14, 4, 'hauntz', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:32:41'),
(15, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-03 19:33:55'),
(16, 4, 'hauntz', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 08:21:38'),
(17, 4, 'hauntz', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 09:27:34'),
(18, 4, 'hauntz', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 17:54:32'),
(19, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 20:58:10'),
(20, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:03:50'),
(21, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:16:33'),
(22, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:18:54'),
(23, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:20:10'),
(24, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:30:58'),
(25, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:35:31'),
(26, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:41:52'),
(27, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:46:58'),
(28, NULL, 'рфгтя', '8F582142FC0FAE1AF8F98DBB3EB36349', 'failed_login', '188.163.26.49', '', '2026-04-04 21:53:17'),
(29, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-04 21:53:23'),
(30, 4, 'hauntz', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 11:27:26'),
(31, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-05 14:11:46'),
(32, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-05 14:13:40'),
(33, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-05 14:17:50'),
(34, 4, 'hauntz', '8F582142FC0FAE1AF8F98DBB3EB36349', 'login', '188.163.26.49', '', '2026-04-05 14:22:10'),
(35, 6, 'Kyba1337', '', 'login', '195.234.29.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 OPR/129.0.0.0', '2026-04-05 14:51:30'),
(36, 4, 'hauntz', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 20:10:43'),
(37, 4, 'hauntz', '', 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 15:14:05'),
(38, 8, 'Capybarus242', '', 'login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 15:21:35'),
(39, NULL, 'Capybarus242', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:33:38'),
(40, NULL, 'Capybarus242', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:33:42'),
(41, NULL, 'Capybarus32', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:33:46'),
(42, NULL, 'Capybarus32', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:33:49'),
(43, NULL, 'Capybarus32', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:34:05'),
(44, NULL, 'Capybarus32', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:34:08'),
(45, NULL, 'Capybarus242', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:34:13'),
(46, NULL, 'Capybarus242', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:34:18'),
(47, 4, 'hauntz', NULL, 'login', '188.163.26.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 20:35:17'),
(48, NULL, 'Capybarus242', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:40:58'),
(49, NULL, 'Capybarus242', NULL, 'failed_login', '185.6.186.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-06 20:41:45'),
(50, NULL, 'Capybarus242', NULL, 'failed_login', '31.144.40.189', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', '2026-04-07 06:47:19');

-- --------------------------------------------------------

--
-- Структура таблицы `purchases`
--

CREATE TABLE `purchases` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `license_id` int DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'balance',
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('completed','pending','failed') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `purchases`
--

INSERT INTO `purchases` (`id`, `user_id`, `license_id`, `product_name`, `amount`, `payment_method`, `transaction_id`, `status`, `created_at`) VALUES
(2, 4, NULL, 'GOD DEV Standard (30 days)', 15.00, 'balance', NULL, 'completed', '2026-04-03 03:57:49'),
(3, 4, NULL, 'GOD DEV Pro (90 days)', 40.00, 'balance', NULL, 'completed', '2026-04-03 19:33:09'),
(4, 4, NULL, 'GOD DEV Pro (90 days)', 40.00, 'balance', NULL, 'completed', '2026-04-04 09:27:38'),
(5, 4, NULL, 'GOD DEV Pro (90 days)', 40.00, 'balance', NULL, 'completed', '2026-04-04 17:58:58'),
(6, 4, NULL, 'GOD DEV Standard (30 days)', 15.00, 'balance', NULL, 'completed', '2026-04-05 12:13:03'),
(7, 4, NULL, 'GOD DEV Standard (30 days)', 15.00, 'balance', NULL, 'completed', '2026-04-05 12:26:17'),
(8, 4, NULL, 'GOD DEV Standard (30 days)', 15.00, 'balance', NULL, 'completed', '2026-04-05 12:26:21'),
(9, 6, NULL, 'GOD DEV Pro (90 днів)', 40.00, 'balance', NULL, 'completed', '2026-04-05 20:11:16');

-- --------------------------------------------------------

--
-- Структура таблицы `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `subject` varchar(140) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `status` enum('open','answered','closed') NOT NULL DEFAULT 'open',
  `last_reply_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `user_id`, `subject`, `category`, `priority`, `status`, `last_reply_at`, `created_at`, `updated_at`) VALUES
(1, 4, '123123', 'general', 'normal', 'answered', '2026-04-06 15:22:26', '2026-04-06 15:20:08', '2026-04-06 15:22:26'),
(2, 8, 'я дед инсайд я мертв внутри мне 10 лет', 'payment', 'high', 'closed', '2026-04-06 15:22:42', '2026-04-06 15:22:33', '2026-04-06 15:22:55');

-- --------------------------------------------------------

--
-- Структура таблицы `support_ticket_messages`
--

CREATE TABLE `support_ticket_messages` (
  `id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `sender_role` enum('user','admin','system') NOT NULL DEFAULT 'user',
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `support_ticket_messages`
--

INSERT INTO `support_ticket_messages` (`id`, `ticket_id`, `user_id`, `sender_role`, `message`, `created_at`) VALUES
(1, 1, 4, 'user', 'фыв', '2026-04-06 15:20:08'),
(2, 1, 4, 'user', 'рроро', '2026-04-06 15:21:16'),
(3, 1, 4, 'admin', 'иди нахуй', '2026-04-06 15:22:26'),
(4, 2, 8, 'user', 'какашки', '2026-04-06 15:22:33'),
(5, 2, 4, 'admin', 'клянись нахуй', '2026-04-06 15:22:42');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT 'default.png',
  `balance` decimal(10,2) DEFAULT '0.00',
  `preferred_currency` enum('usd','eur','rub','uah','byn','ils') NOT NULL DEFAULT 'usd',
  `hwid` varchar(64) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT '0',
  `admin_level` int DEFAULT '0',
  `can_ban` tinyint(1) DEFAULT '0',
  `can_manage_users` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_tickets` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_balance` tinyint(1) NOT NULL DEFAULT '0',
  `is_banned` tinyint(1) DEFAULT '0',
  `ban_reason` text,
  `admin_note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `avatar`, `balance`, `preferred_currency`, `hwid`, `is_admin`, `admin_level`, `can_ban`, `can_manage_users`, `can_manage_tickets`, `can_edit_balance`, `is_banned`, `ban_reason`, `admin_note`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@soulware.space', '$2y$10$YourHashHere', 'default.png', 9999.99, 'usd', NULL, 1, 3, 1, 0, 0, 0, 0, NULL, NULL, '2026-04-02 19:22:43', '2026-04-02 19:22:43'),
(4, 'hauntz', 'sky7ovecoder@gmail.com', '$2y$10$eHIENTala7D4YufNAEbteexzBdwWTObkV2jXRBLh9pShZujKZ6e0a', 'user_4_1775400162.jfif', 9819.00, 'usd', '8F582142FC0FAE1AF8F98DBB3EB36349', 1, 10, 1, 1, 1, 1, 0, NULL, NULL, '2026-04-03 03:57:05', '2026-04-06 15:26:58'),
(5, 'Prostofeelya', 'aballtuss@gmail.com', '$2y$10$.mdgxGB7VLRdxbEHS/6c8eZfs1bkHz1tCjT7JQVJJP9Pf0EP9kO6i', 'default.png', 1111.00, 'usd', NULL, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2026-04-03 04:32:38', '2026-04-05 13:34:02'),
(6, 'Kyba1337', 'kibailia21@gmail.com', '$2y$10$uKzB5FI8pP.wmDlaAqM8HeUN/2XQ9DXvgi/h1//kqfLGaKWZbWsFm', 'user_6_1775395909.jpg', 9848.00, 'usd', NULL, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2026-04-05 13:31:15', '2026-04-05 20:11:16'),
(7, 'uiban67676767', 'chundrachuchundra657@gmail.com', '$2y$10$03Ebw8R27Wy/DFYcFuEyjOTsLach2NZq2f8y6OGza5iovCkW6Vbf6', 'default.png', 0.00, 'usd', NULL, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2026-04-05 14:50:38', '2026-04-05 14:50:38'),
(8, 'Capybarus242', 'capybarasasha@gmail.com', '123123', 'default.png', 10000.00, 'usd', NULL, 1, 2, 0, 0, 0, 0, 0, NULL, NULL, '2026-04-05 21:21:44', '2026-04-06 20:38:05'),
(9, '123123', '123123@gmail.com', '$2y$10$txgPcyoBsEGCMhIKHcb4A.wjPj1IKCJXdxtVoKjo7b5WU0jlYldEe', 'default.png', 0.00, 'usd', NULL, 0, 0, 0, 0, 0, 0, 0, 'ладно давай пока', NULL, '2026-04-06 15:24:18', '2026-04-06 15:41:17');

-- --------------------------------------------------------

--
-- Структура таблицы `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_logs_admin_user_id` (`admin_user_id`),
  ADD KEY `idx_admin_logs_target_user_id` (`target_user_id`),
  ADD KEY `idx_admin_logs_action` (`action`);

--
-- Индексы таблицы `bans`
--
ALTER TABLE `bans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banned_by` (`banned_by`),
  ADD KEY `idx_hwid` (`hwid`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Индексы таблицы `licenses`
--
ALTER TABLE `licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`),
  ADD KEY `idx_license_key` (`license_key`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Индексы таблицы `loader_logs`
--
ALTER TABLE `loader_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_hwid` (`hwid`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Индексы таблицы `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `license_id` (`license_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Индексы таблицы `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_user_id` (`user_id`),
  ADD KEY `idx_ticket_status` (`status`),
  ADD KEY `idx_ticket_updated_at` (`updated_at`);

--
-- Индексы таблицы `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_messages_ticket_id` (`ticket_id`),
  ADD KEY `idx_ticket_messages_user_id` (`user_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_hwid` (`hwid`),
  ADD KEY `idx_is_banned` (`is_banned`);

--
-- Индексы таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `bans`
--
ALTER TABLE `bans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `licenses`
--
ALTER TABLE `licenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `loader_logs`
--
ALTER TABLE `loader_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT для таблицы `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  ADD CONSTRAINT `admin_action_logs_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admin_action_logs_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `bans`
--
ALTER TABLE `bans`
  ADD CONSTRAINT `bans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bans_ibfk_2` FOREIGN KEY (`banned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `licenses`
--
ALTER TABLE `licenses`
  ADD CONSTRAINT `licenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD CONSTRAINT `support_ticket_messages_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_ticket_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- --------------------------------------------------------

--
-- Структура таблицы `password_reset_codes`
--

CREATE TABLE IF NOT EXISTS `password_reset_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(100) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_reset_user_id` (`user_id`),
  KEY `idx_password_reset_email` (`email`),
  KEY `idx_password_reset_expires_at` (`expires_at`),
  KEY `idx_password_reset_used_at` (`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
