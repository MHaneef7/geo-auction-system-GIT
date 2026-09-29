-- ========================================================
-- Geo Auction System - Complete Database Backup
-- Database: `geo_auction_db`
-- Export Date: 2026-09-21 13:21:17
-- Server OS: WINNT
-- PHP Version: 8.2.12
-- MySQL Version: 10.4.32-MariaDB
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = '+00:00';


-- --------------------------------------------------------
-- Structure for table `activity_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `model_type` varchar(100) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_action` (`action`),
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_model` (`model_type`,`model_id`)
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `activity_logs`
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `model_type`, `model_id`, `ip_address`, `user_agent`, `request_data`, `created_at`) VALUES
(1, 4, 'register', 'User registered', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 04:54:38'),
(2, 4, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 04:55:44'),
(3, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 04:56:10'),
(4, 4, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 05:09:28'),
(5, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 05:09:51'),
(6, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 05:21:57'),
(10, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 06:42:46'),
(11, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 06:58:15'),
(14, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 10:15:27'),
(15, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 10:45:57'),
(26, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 04:52:50'),
(27, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 04:56:55'),
(28, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 04:57:21'),
(29, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 04:58:30'),
(30, 5, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 04:58:35'),
(31, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 05:01:49'),
(32, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 05:39:59'),
(33, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 05:40:03'),
(34, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:02:49'),
(35, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:04:08'),
(36, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:10:35'),
(37, 1, 'CREATE_ITEM', 'Created a new auction item: Yamaha MT-15 2024', 'Item', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/138.0.0.0 Safari/537.36', '{\"title\":\"Yamaha MT-15 2024\",\"brand\":\"Yamaha\",\"model\":\"MT-15\",\"year\":2024,\"starting_price\":760000}', '2026-07-31 11:16:38'),
(38, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:18:07'),
(39, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:22:29'),
(40, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:23:22'),
(41, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:30:56'),
(42, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:31:24'),
(43, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:46:41'),
(44, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:53:24'),
(45, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:58:46'),
(46, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:01:24'),
(47, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:05:41'),
(48, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:11:56'),
(49, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:12:02'),
(50, 6, 'register', 'User registered', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:20:35'),
(51, 6, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:26:26'),
(52, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:27:34'),
(53, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:40:42'),
(54, 6, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:41:12'),
(55, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:44:27'),
(56, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:54:00'),
(57, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 10:17:59'),
(58, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 10:20:57'),
(59, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 10:50:01'),
(60, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 11:12:03'),
(61, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 11:19:44'),
(62, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 11:30:59'),
(63, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 12:19:17'),
(64, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:56:58'),
(65, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:58:17'),
(66, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:59:03'),
(67, 5, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:59:53'),
(68, 7, 'register', 'User registered', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:01:27'),
(69, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:07:28'),
(70, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:10:20'),
(71, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:10:27'),
(72, 7, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:10:51'),
(73, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:13:22'),
(74, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:15:37'),
(75, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:33:24'),
(76, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:48:03'),
(77, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:51:39'),
(78, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:59:59'),
(79, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 07:25:36'),
(80, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 07:57:02'),
(81, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 07:57:42'),
(82, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 08:05:59'),
(83, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 08:28:31'),
(84, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 09:40:33'),
(85, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 10:06:28'),
(86, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 10:15:12'),
(87, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 10:15:48'),
(88, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 10:21:35'),
(89, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 10:21:39'),
(90, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 10:29:08'),
(91, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 10:46:13'),
(92, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 11:43:49'),
(93, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 12:06:44'),
(94, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 12:13:39'),
(95, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-04 04:15:07'),
(96, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-04 04:49:39'),
(97, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-04 05:45:43'),
(98, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-10 04:22:04'),
(99, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-11 17:57:08'),
(100, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 11:59:29'),
(101, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 11:59:37'),
(102, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 12:03:34'),
(103, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 12:03:43'),
(104, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-13 04:20:53');


-- --------------------------------------------------------
-- Structure for table `bids`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `bids`;
CREATE TABLE `bids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bidder_name` varchar(255) DEFAULT NULL,
  `bidder_email` varchar(255) DEFAULT NULL,
  `bid_amount` decimal(12,2) NOT NULL,
  `bid_status` enum('active','outbid','winning','won','lost','cancelled') NOT NULL DEFAULT 'active',
  `is_winning` tinyint(1) NOT NULL DEFAULT 0,
  `is_auto_bid` tinyint(1) NOT NULL DEFAULT 0,
  `max_bid_amount` decimal(12,2) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bids_item` (`item_id`),
  KEY `idx_bids_user` (`user_id`),
  KEY `idx_bids_amount` (`bid_amount`),
  KEY `idx_bids_status` (`bid_status`),
  KEY `idx_bids_created` (`created_at`),
  CONSTRAINT `bids_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bids_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `bids`
INSERT INTO `bids` (`id`, `item_id`, `user_id`, `bidder_name`, `bidder_email`, `bid_amount`, `bid_status`, `is_winning`, `is_auto_bid`, `max_bid_amount`, `ip_address`, `user_agent`, `created_at`, `status`) VALUES
(2, 21, 2, 'Test Bidder 1', 'bidder1@test.com', 57500.00, 'active', 0, 0, NULL, '127.0.0.1', 'Mozilla/5.0', '2026-08-18 16:00:00', 'active'),
(3, 21, 3, 'Test Bidder 2', 'bidder2@test.com', 60000.00, 'active', 0, 0, NULL, '127.0.0.1', 'Mozilla/5.0', '2026-08-18 16:30:00', 'active'),
(5, 21, 2, 'Test Bidder 1', 'bidder1@test.com', 65000.00, 'active', 1, 0, NULL, '127.0.0.1', 'Mozilla/5.0', '2026-08-18 17:30:00', 'active'),
(14, 23, 3, NULL, NULL, 2000000.00, 'active', 0, 0, NULL, NULL, NULL, '2026-08-22 20:04:19', ''),
(15, 60, 3, NULL, NULL, 888899990.00, 'active', 1, 0, NULL, NULL, NULL, '2026-08-22 21:56:57', 'accepted'),
(16, 61, 3, NULL, NULL, 777783.00, 'active', 1, 0, NULL, NULL, NULL, '2026-08-22 22:34:08', 'accepted'),
(17, 62, 3, NULL, NULL, 7050000.00, 'active', 1, 0, NULL, NULL, NULL, '2026-08-23 16:32:39', 'accepted'),
(18, 58, 3, 'aakash', 'aakash@gmail.com', 8500800.00, 'active', 0, 0, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-25 12:31:30', 'active'),
(19, 64, 1, 'Admin User', 'admin@gmail.com', 22233444.00, 'active', 0, 0, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:32:52', 'rejected'),
(20, 64, 1, 'Admin User', 'admin@gmail.com', 9999999999.99, 'active', 0, 0, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-10 11:35:54', 'rejected'),
(21, 64, 1, 'Admin User', 'admin@gmail.com', 9999999999.99, 'active', 0, 0, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-10 11:53:25', 'rejected');


-- --------------------------------------------------------
-- Structure for table `categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-tag',
  `image` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  KEY `idx_categories_slug` (`slug`),
  KEY `idx_categories_status` (`status`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `categories`
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `image`, `parent_id`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Motors', 'motors', 'Cars, bikes, boats, and vehicles', 'fa-car', NULL, NULL, 1, 'active', '2026-07-29 18:27:35', '2026-08-10 09:22:17'),
(2, 'Properties', 'properties', 'Real estate, land, and properties', 'fa-building', NULL, NULL, 2, 'active', '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(3, 'Prestige Plates', 'prestige-plates', 'VIP number plates and registrations', 'fa-hashtag', NULL, NULL, 3, 'active', '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(4, 'Jewelry & Watches', 'jewelry-watches', 'Luxury jewelry, watches, and gems', 'fa-gem', NULL, NULL, 4, 'inactive', '2026-07-29 18:27:35', '2026-08-03 11:54:16'),
(5, 'Electronics', 'electronics', 'Laptops, phones, gadgets, and tech', 'fa-laptop', NULL, NULL, 5, 'active', '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(6, 'Art & Collectibles', 'art-collectibles', 'Art, antiques, and collectible items', 'fa-palette', NULL, NULL, 6, 'active', '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(7, 'Government Surplus', 'government-surplus', 'Government and industrial surplus', 'fa-boxes-stacked', NULL, NULL, 7, 'active', '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(8, 'Sport Bikes', 'sport-bikes', 'High-performance sport motorcycles.', 'fas fa-motorcycle', 'uploads/categories/sport-bikes.jpg', NULL, 1, 'active', '2026-07-31 11:21:15', '2026-07-31 11:21:15'),
(9, 'Cruiser Bikes', 'cruiser-bikes', 'Comfortable motorcycles for long-distance touring.', 'fas fa-road', 'uploads/categories/cruiser-bikes.jpg', NULL, 2, 'inactive', '2026-07-31 11:21:15', '2026-07-31 15:51:04'),
(10, 'Naked Bikes', 'naked-bikes', 'Street motorcycles with aggressive styling.', 'fas fa-biking', 'uploads/categories/naked-bikes.jpg', NULL, 3, 'active', '2026-07-31 11:21:15', '2026-07-31 11:21:15'),
(11, 'Adventure Bikes', 'adventure-bikes', 'Motorcycles designed for on-road and off-road travel.', 'fas fa-mountain', 'uploads/categories/adventure-bikes.jpg', NULL, 4, 'active', '2026-07-31 11:21:15', '2026-07-31 11:21:15'),
(13, 'Electric Bikes', 'electric-bikes', 'Eco-friendly electric motorcycles.', 'fas fa-bolt', 'uploads/categories/electric-bikes.jpg', NULL, 6, 'inactive', '2026-07-31 11:21:15', '2026-08-04 10:00:03'),
(18, 'Test Vehicles', 'test-vehicles', 'Testing vehicles category', 'fa-car', NULL, NULL, 10, 'active', '2026-08-20 11:43:20', '2026-08-20 11:43:20'),
(19, 'Test Electronics', 'test-electronics', 'Testing electronics category', 'fa-laptop', NULL, NULL, 11, 'active', '2026-08-20 11:43:20', '2026-08-20 11:43:20'),
(20, 'Test Properties', 'test-properties', 'Testing properties category', 'fa-building', NULL, NULL, 12, 'active', '2026-08-20 11:43:20', '2026-08-20 11:43:20'),
(21, 'sadf', '', NULL, 'fa-tag-star', NULL, NULL, 0, 'active', '2026-09-18 15:30:45', '2026-09-18 15:30:45');


-- --------------------------------------------------------
-- Structure for table `contact_messages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `replied` tinyint(1) NOT NULL DEFAULT 0,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_read` (`is_read`),
  KEY `idx_contact_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `contact_messages`
INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `replied`, `replied_at`, `created_at`) VALUES
(1, 'Haneef', 'mhaneefalimemon@gmail.com', 'moter bike', 'moter bike', 0, 0, NULL, '2026-07-30 05:12:04'),
(2, 'Muhammad Haneef', 'mhaneefalimemon@gmail.com', 'moter bike', 'dd', 0, 0, NULL, '2026-08-03 06:06:46'),
(3, 'Admin', 'admin@geoauction.com', 'moter bike', 'dd', 0, 0, NULL, '2026-08-03 06:07:04');


-- --------------------------------------------------------
-- Structure for table `item_images`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `item_images`;
CREATE TABLE `item_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_item_images_item` (`item_id`),
  CONSTRAINT `item_images_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `item_images`
INSERT INTO `item_images` (`id`, `item_id`, `image_path`, `is_primary`, `sort_order`, `created_at`) VALUES
(19, 24, 'uploads/items/item_1787201830_6336_0.webp', 0, 0, '2026-08-20 09:57:11'),
(20, 24, 'uploads/items/item_1787201830_8036_1.webp', 0, 0, '2026-08-20 09:57:11'),
(21, 24, 'uploads/items/item_1787201831_2906_2.webp', 0, 0, '2026-08-20 09:57:11'),
(22, 25, 'uploads/items/item_1787207426_5218_0.webp', 1, 0, '2026-08-20 11:30:26'),
(26, 26, 'uploads/items/honda-civic-2022.jpg', 1, 1, '2026-08-20 11:51:35'),
(27, 26, 'uploads/items/honda-civic-2022-2.jpg', 0, 2, '2026-08-20 11:51:35'),
(28, 26, 'uploads/items/honda-civic-2022-3.jpg', 0, 3, '2026-08-20 11:51:35'),
(29, 59, 'uploads/items/item_1787415803_9474_0.jfif', 1, 0, '2026-08-22 21:23:23'),
(30, 59, 'uploads/items/item_1787415803_3796_1.jfif', 0, 0, '2026-08-22 21:23:23'),
(31, 60, 'uploads/items/item_1787417757_7118_0.jfif', 1, 0, '2026-08-22 21:55:57'),
(32, 60, 'uploads/items/item_1787417757_4362_1.jfif', 0, 0, '2026-08-22 21:55:57'),
(33, 61, 'uploads/items/item_1787419976_3107_0.jfif', 1, 0, '2026-08-22 22:32:56'),
(34, 61, 'uploads/items/item_1787419976_2307_1.jfif', 0, 0, '2026-08-22 22:32:56'),
(35, 62, 'uploads/items/item_1787484524_5165_0.jfif', 1, 0, '2026-08-23 16:28:44'),
(36, 62, 'uploads/items/item_1787484524_9665_1.jfif', 0, 0, '2026-08-23 16:28:44'),
(37, 63, 'uploads/items/item_1787776903_7659_0.webp', 1, 0, '2026-08-27 01:41:43'),
(38, 63, 'uploads/items/item_1787776903_5044_1.jpg', 0, 0, '2026-08-27 01:41:43'),
(39, 63, 'uploads/items/item_1787776903_6866_2.webp', 0, 0, '2026-08-27 01:41:43'),
(40, 64, 'uploads/items/item_1788325046_8743_0.jfif', 1, 0, '2026-09-02 09:57:26'),
(41, 64, 'uploads/items/item_1788325046_4732_1.jfif', 0, 0, '2026-09-02 09:57:26'),
(42, 64, 'uploads/items/item_1788325046_5527_2.jfif', 0, 0, '2026-09-02 09:57:26');


-- --------------------------------------------------------
-- Structure for table `item_reports`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `item_reports`;
CREATE TABLE `item_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `item_reports_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_reports_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `item_reports`

-- --------------------------------------------------------
-- Structure for table `items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auction_code` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `starting_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reserve_price` decimal(12,2) DEFAULT NULL,
  `current_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_increment` decimal(12,2) NOT NULL DEFAULT 1.00,
  `buy_it_now_price` decimal(12,2) DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `condition_type` varchar(50) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `mileage` varchar(50) DEFAULT NULL,
  `engine_type` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `location_address` varchar(255) DEFAULT NULL,
  `location_city` varchar(100) DEFAULT NULL,
  `location_state` varchar(100) DEFAULT NULL,
  `location_country` varchar(100) DEFAULT NULL,
  `location_lat` decimal(10,8) DEFAULT NULL,
  `location_lng` decimal(11,8) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `extended_time` datetime DEFAULT NULL,
  `status` enum('pending','active','paused','ended','cancelled','sold') NOT NULL DEFAULT 'pending',
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `bid_count` int(11) NOT NULL DEFAULT 0,
  `watcher_count` int(11) NOT NULL DEFAULT 0,
  `seller_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `area` varchar(100) NOT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `winning_bid_id` int(11) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `auction_code` (`auction_code`),
  KEY `winner_id` (`winner_id`),
  KEY `idx_items_status` (`status`),
  KEY `idx_items_featured` (`featured`),
  KEY `idx_items_end_time` (`end_time`),
  KEY `idx_items_seller` (`seller_id`),
  KEY `idx_items_category` (`category_id`),
  KEY `idx_items_slug` (`slug`),
  FULLTEXT KEY `idx_items_search` (`title`,`description`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `items_ibfk_3` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `items`
INSERT INTO `items` (`id`, `auction_code`, `title`, `slug`, `description`, `short_description`, `starting_price`, `reserve_price`, `current_price`, `min_increment`, `buy_it_now_price`, `featured_image`, `images`, `condition_type`, `brand`, `model`, `year`, `mileage`, `engine_type`, `color`, `location_address`, `location_city`, `location_state`, `location_country`, `location_lat`, `location_lng`, `start_time`, `end_time`, `extended_time`, `status`, `featured`, `approved`, `views`, `bid_count`, `watcher_count`, `seller_id`, `category_id`, `city`, `area`, `winner_id`, `winning_bid_id`, `meta_title`, `meta_description`, `meta_keywords`, `created_at`, `updated_at`) VALUES
(2, 'AUC-CD70-1001', 'Honda CD 70 2022', 'honda-cd-70-2022', 'Honda CD 70 2022 model in good condition. The motorcycle is suitable for daily commuting and has been maintained properly.', 'Honda CD 70 2022 model in good condition.', 85000.00, 95000.00, 88000.00, 2000.00, 110000.00, 'uploads/items/cd70-2022-1.jpg', '[\"uploads/items/cd70-2022-1.jpg\",\"uploads/items/cd70-2022-2.jpg\",\"uploads/items/cd70-2022-3.jpg\"]', 'used', 'Honda', 'CD 70', 2022, 18500, '70cc Petrol', 'Red', 'Latifabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-08-03 10:00:00', '2026-08-10 22:00:00', '0000-00-00 00:00:00', 'ended', 1, 1, 133, 4, 12, 2, 1, '', '', NULL, NULL, 'Honda CD 70 2022 Auction', 'Buy Honda CD 70 2022 through online auction.', 'Honda CD 70, Honda bike, 70cc, motorcycle auction', '2026-08-03 16:11:30', '2026-09-21 16:15:57'),
(18, 'AUC-TOY-2026-01', 'Toyota Camry SE - Clean Condition', 'toyota-camry-se-clean-condition', 'Well-maintained Toyota Camry SE with low mileage, reliable engine, and clean interior. Perfect daily driver.', 'Reliable and clean Toyota Camry SE available for auction.', 5000.00, 7500.00, 5000.00, 100.00, NULL, 'uploads/items/toyota-camry-1.jpg', '[\"uploads/items/toyota-camry-1.jpg\",\"uploads/items/toyota-camry-2.jpg\",\"uploads/items/toyota-camry-3.jpg\"]', 'Used', 'Toyota', 'Camry SE', 2021, 35000, '2.5L 4-Cylinder', 'Midnight Black Metallic', '123 Main Street', 'Houston', 'TX', 'USA', NULL, NULL, '2026-09-20 15:59:28', '2026-09-26 15:59:28', NULL, 'active', 1, 1, 0, 0, 0, 1, 1, '', '', NULL, NULL, NULL, NULL, NULL, '2026-08-18 15:16:48', '2026-09-21 16:15:57'),
(19, 'TEST-001', 'Test Toyota Corolla', 'test-toyota-corolla', 'This is dummy testing auction item.', 'Test Toyota Corolla', 100000.00, 150000.00, 110000.00, 5000.00, 200000.00, 'uploads/items/test-corolla-1.jpg', '[\"uploads/items/test-corolla-1.jpg\",\"uploads/items/test-corolla-2.jpg\",\"uploads/items/test-corolla-3.jpg\"]', 'Used', 'Toyota', 'Corolla', 2020, 50000, 'Petrol', 'White', 'Test Address', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-27 15:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 0, 0, 0, 1, 1, '', '', NULL, NULL, 'Test Auction Item', 'Dummy auction item for testing.', 'test, auction, car', '2026-08-18 15:47:53', '2026-09-21 16:15:57'),
(20, 'TEST-002', 'Test Honda Civic', 'test-honda-civic', 'Dummy Honda Civic for testing auction functionality.', 'Test Honda Civic', 200000.00, 250000.00, 210000.00, 5000.00, 300000.00, 'uploads/items/test-honda-1.jpg', '[\"uploads/items/test-honda-1.jpg\",\"uploads/items/test-honda-2.jpg\",\"uploads/items/test-honda-3.jpg\"]', 'Used', 'Honda', 'Civic', 2021, 40000, 'Petrol', 'Black', 'Test Address 2', 'Karachi', 'Sindh', 'Pakistan', 24.86070000, 67.00110000, '2026-09-20 15:59:28', '2026-09-28 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 0, 0, 0, 1, 1, '', '', NULL, NULL, 'Test Honda Civic Auction', 'Dummy Honda Civic for testing.', 'test, honda, civic, auction', '2026-08-18 15:47:53', '2026-09-21 16:15:57'),
(21, 'TEST-003', 'Test Suzuki Alto', 'test-suzuki-alto', 'Dummy Suzuki Alto for testing.', 'Test Suzuki Alto', 50000.00, 80000.00, 55000.00, 2500.00, 100000.00, 'uploads/items/test-alto-1.jpg', '[\"uploads/items/test-alto-1.jpg\",\"uploads/items/test-alto-2.jpg\",\"uploads/items/test-alto-3.jpg\"]', 'Used', 'Suzuki', 'Alto', 2022, 30000, 'Petrol', 'Silver', 'Test Address 3', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-21 16:44:28', '0000-00-00 00:00:00', 'active', 0, 1, 0, 0, 0, 1, 1, '', '', NULL, NULL, 'Test Suzuki Alto Auction', 'Dummy Suzuki Alto for testing.', 'test, suzuki, alto, auction', '2026-08-18 15:47:53', '2026-09-21 16:15:57'),
(22, 'AUC2026003', 'Hi-Speed 70cc 2023', 'hi-speed-70cc-2023', 'Hi-Speed 70cc motorcycle in good condition. Economical and reliable bike for daily commuting.', 'Hi-Speed 70cc economical motorcycle in good condition.', 95000.00, 120000.00, 95000.00, 3000.00, 140000.00, 'uploads/items/hi-speed-70cc-2023-1.jpg', '[\"uploads/items/hi-speed-70cc-2023-1.jpg\",\"uploads/items/hi-speed-70cc-2023-2.jpg\"]', 'Good', 'Hi-Speed', '70cc', 2023, 12000, 'Petrol', 'Black', 'Main Road', 'Karachi', 'Sindh', 'Pakistan', 24.86070000, 67.00110000, '2026-09-20 15:59:28', '2026-09-23 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 18, 0, 2, 1, 1, '', '', NULL, NULL, 'Hi-Speed 70cc 2023 Auction', 'Buy Hi-Speed 70cc 2023 through live online auction.', 'Hi-Speed 70cc, 70cc bike, motorcycle auction, Pakistan', '2026-08-19 11:02:17', '2026-09-21 16:15:57'),
(23, 'AUC2026002', 'Honda 125 2024', 'honda-125-2024', 'Honda 125cc motorcycle in excellent condition. Well maintained and suitable for daily use.', 'Honda 125cc in excellent condition with complete documents.', 180000.00, 220000.00, 2000000.00, 5000.00, 250000.00, 'uploads/items/honda-125-2024-1.jpg', '[\"uploads/items/honda-125-2024-1.jpg\",\"uploads/honda-125-2024-2.jpg\"]', 'Excellent', 'Honda', 125, 2024, 8500, 'Petrol', 'Red', 'Main Road', 'Karachi', 'Sindh', 'Pakistan', 24.86070000, 67.00110000, '2026-09-20 15:59:28', '2026-09-21 16:44:28', '0000-00-00 00:00:00', 'active', 1, 1, 25, 1, 3, 1, 1, '', '', 3, 14, 'Honda 125 2024 Auction', 'Buy Honda 125 2024 in excellent condition through live auction.', 'Honda 125, Honda bike, 125cc motorcycle, auction', '2026-08-19 11:02:35', '2026-09-21 16:15:57'),
(24, 'GA-3KEAGR', 'Honda CG 125', '', 'Honda CG 125 2013 in neat & clean condition, tastefully upgraded for a modern look. \r\n\r\n Lahore Registered\r\n Original Documents Available\r\n Digital Meter Installed\r\n Stylish Front & Indicators\r\n Premium Tank Graphics\r\n Original Tank Also Available\r\n Clean Seat & Chrome\r\n Smooth Running / Ready to Ride\r\n\r\nBike can be inspected in Lahore. \r\nDemand: Rs. 104,900 — Slightly Negotiable\r\n\r\nReasonable negotiation only after inspection. Serious buyers please contact.', NULL, 140000.00, NULL, 140000.00, 1.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 15:59:28', '2026-09-24 15:59:28', NULL, 'active', 0, 0, 0, 0, 0, 1, 1, '', '', NULL, NULL, NULL, NULL, NULL, '2026-08-20 09:43:46', '2026-09-21 15:59:28'),
(25, 'GA-WFJ53C', 'Infinix Hot 11 Play', '', 'wwww', NULL, 222222.00, NULL, 222222.00, 1.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 15:59:28', '2026-09-26 15:59:28', NULL, 'active', 0, 0, 0, 0, 0, 1, 4, '', '', NULL, NULL, NULL, NULL, NULL, '2026-08-20 11:30:26', '2026-09-21 15:59:28'),
(26, 'TEST-AUC-001', 'Honda Civic 2022', 'honda-civic-2022', 'Honda Civic 2022 in excellent condition. Full documents available and well maintained.', 'Honda Civic 2022 - Excellent Condition', 3500000.00, 4000000.00, 3500000.00, 50000.00, 4500000.00, 'uploads/items/honda-civic-2022.jpg', '[\"uploads/items/honda-civic-2022.jpg\",\"uploads/items/honda-civic-2022-2.jpg\"]', 'Excellent', 'Honda', 'Civic', 2022, '25000 KM', 'Petrol', 'White', 'Auto Bhan Road', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-27 15:59:28', NULL, 'active', 1, 1, 0, 0, 0, 2, 1, '', '', NULL, NULL, 'Honda Civic 2022 Auction', 'Buy Honda Civic 2022 through live auction', 'honda,civic,car,auction', '2026-08-20 11:43:30', '2026-09-21 16:15:57'),
(27, 'TEST-AUC-002', 'Yamaha YBR 125 2024', 'yamaha-ybr-125-2024', 'Yamaha YBR 125 2024 model motorcycle in excellent condition.', 'Yamaha YBR 125 2024', 420000.00, 450000.00, 420000.00, 5000.00, 500000.00, 'uploads/items/yamaha-ybr-125.jpg', '[\"uploads/items/yamaha-ybr-125.jpg\",\"uploads/items/yamaha-ybr-125-2.jpg\"]', 'Excellent', 'Yamaha', 'YBR 125', 2024, '8000 KM', 'Petrol', 'Black', NULL, 'Hyderabad', 'Sindh', 'Pakistan', NULL, NULL, '2026-09-20 15:59:28', '2026-09-28 15:59:28', NULL, 'active', 1, 1, 0, 0, 0, 2, 1, '', '', NULL, NULL, 'Yamaha YBR 125 2024 Auction', 'Live auction for Yamaha YBR 125 2024', 'yamaha,ybr,motorcycle,auction', '2026-08-20 11:44:44', '2026-09-21 16:15:57'),
(28, 'TEST-AUC-003', 'Honda CG 125 2024', 'honda-cg-125-2024', 'Honda CG 125 2024 model motorcycle in excellent condition.', 'Honda CG 125 2024', 350000.00, 380000.00, 350000.00, 5000.00, 450000.00, 'uploads/items/honda-cg-125.jpg', '[\"uploads/items/honda-cg-125.jpg\"]', 'Excellent', 'Honda', 'CG 125', 2024, '5000 KM', 'Petrol', 'Red', NULL, 'Hyderabad', 'Sindh', 'Pakistan', NULL, NULL, '2026-09-20 15:59:28', '2026-09-22 15:59:28', NULL, 'active', 1, 1, 0, 0, 0, 2, 1, '', '', NULL, NULL, 'Honda CG 125 2024 Auction', 'Live auction for Honda CG 125 2024', 'honda,cg125,motorcycle,auction', '2026-08-21 00:52:10', '2026-09-21 16:15:57'),
(33, 'GA-PLT-001', 'Premium Number Plate 786', 'premium-number-plate-786', 'Premium vehicle registration number plate 786 for auction.', 'Prestige registration number 786.', 180000.00, 250000.00, 180000.00, 10000.00, 350000.00, 'uploads/items/plate-786-1.jpg', '[\"uploads/items/plate-786-1.jpg\"]', 'new', NULL, NULL, NULL, NULL, NULL, 'Black', 'Hyderabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-27 15:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 312, 12, 22, 2, 3, '', '', NULL, NULL, 'Premium 786 Number Plate Auction', 'Premium prestige number plate 786 available in Hyderabad.', '786 number plate, prestige plate, Hyderabad', '2026-08-21 17:20:00', '2026-09-21 16:15:57'),
(40, 'GA-ART-002', 'Vintage Decorative Vase', 'vintage-decorative-vase', 'Vintage decorative vase suitable for home decoration and collectors.', 'Classic vintage collectible vase.', 25000.00, 35000.00, 25000.00, 2500.00, 50000.00, 'uploads/items/vintage-vase-1.jpg', '[\"uploads/items/vintage-vase-1.jpg\"]', 'used', NULL, NULL, 1998, NULL, NULL, 'Brown', 'Latifabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.38300000, 68.37300000, '2026-09-20 15:59:28', '2026-09-27 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 76, 3, 5, 2, 6, '', '', NULL, NULL, 'Vintage Vase Auction Hyderabad', 'Vintage decorative vase available for auction in Hyderabad.', 'vintage vase, collectible, art auction', '2026-08-21 17:55:00', '2026-09-21 16:15:57'),
(41, 'GA-GOV-001', 'Government Office Furniture Lot', 'government-office-furniture-lot', 'Used office furniture lot including desks and chairs available for auction.', 'Government surplus office furniture.', 85000.00, 110000.00, 85000.00, 5000.00, 140000.00, 'uploads/items/office-furniture-1.jpg', '[\"uploads/items/office-furniture-1.jpg\"]', 'used', NULL, NULL, 2020, NULL, NULL, 'Brown', 'Government Colony', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-28 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 91, 4, 6, 2, 7, '', '', NULL, NULL, 'Government Office Furniture Auction', 'Government surplus office furniture available for auction.', 'government surplus, furniture auction, Hyderabad', '2026-08-21 18:00:00', '2026-09-21 16:15:57'),
(42, 'GA-GOV-002', 'Industrial Generator 25KVA', 'industrial-generator-25kva', 'Used 25KVA industrial generator available as surplus equipment.', 'Reliable 25KVA generator for industrial use.', 320000.00, 390000.00, 320000.00, 10000.00, 450000.00, 'uploads/items/25kva-generator-1.jpg', '[\"uploads/items/25kva-generator-1.jpg\"]', 'used', NULL, '25KVA', 2019, NULL, 'Diesel', 'White', 'Industrial Area', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-22 15:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 143, 5, 10, 2, 7, '', '', NULL, NULL, '25KVA Generator Government Surplus Auction', '25KVA diesel generator available for auction in Hyderabad.', 'generator, government surplus, industrial equipment', '2026-08-21 18:05:00', '2026-09-21 16:15:57'),
(43, 'GA-SPB-001', 'Yamaha YZF R15', 'yamaha-yzf-r15', 'Yamaha R15 sport bike in excellent condition available in Hyderabad.', 'High performance Yamaha R15 motorcycle.', 520000.00, 600000.00, 520000.00, 10000.00, 680000.00, 'uploads/items/yamaha-r15-1.jpg', '[\"uploads/items/yamaha-r15-1.jpg\",\"uploads/items/yamaha-r15-2.jpg\"]', 'used', 'Yamaha', 'YZF R15', 2022, 18000, 'Petrol', 'Blue', 'Autobhan Road', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-21 17:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 422, 16, 31, 2, 8, '', '', NULL, NULL, 'Yamaha R15 Sport Bike Auction', 'Yamaha R15 sport bike available for auction in Hyderabad Sindh.', 'Yamaha R15, sport bike, Hyderabad auction', '2026-08-21 18:10:00', '2026-09-21 16:15:57'),
(44, 'GA-SPB-002', 'Suzuki GSX 150', 'suzuki-gsx-150', 'Suzuki GSX 150 motorcycle with excellent performance and condition.', 'Sporty Suzuki motorcycle ready for auction.', 390000.00, 450000.00, 390000.00, 10000.00, 520000.00, 'uploads/items/suzuki-gsx-150-1.jpg', '[\"uploads/items/suzuki-gsx-150-1.jpg\"]', 'used', 'Suzuki', 'GSX 150', 2021, 24000, 'Petrol', 'Black', 'Qasimabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.34300000, '2026-09-20 15:59:28', '2026-09-24 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 198, 7, 12, 2, 8, '', '', NULL, NULL, 'Suzuki GSX 150 Auction Hyderabad', 'Suzuki GSX 150 sport motorcycle available in Hyderabad.', 'Suzuki GSX 150, bike auction, Hyderabad', '2026-08-21 18:15:00', '2026-09-21 16:15:57'),
(45, 'GA-CRU-001', 'Royal Enfield Classic 350', 'royal-enfield-classic-350', 'Royal Enfield Classic 350 cruiser motorcycle in good condition.', 'Classic cruiser motorcycle for enthusiasts.', 780000.00, 900000.00, 780000.00, 20000.00, 1000000.00, 'uploads/items/royal-enfield-350-1.jpg', '[\"uploads/items/royal-enfield-350-1.jpg\"]', 'used', 'Royal Enfield', 'Classic 350', 2022, 12000, 'Petrol', 'Black', 'Latifabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.38300000, 68.37300000, '2026-09-20 15:59:28', '2026-09-21 17:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 210, 8, 17, 2, 9, '', '', NULL, NULL, 'Royal Enfield Classic 350 Auction', 'Royal Enfield Classic 350 available for auction in Hyderabad.', 'Royal Enfield, Classic 350, cruiser bike', '2026-08-21 18:20:00', '2026-09-21 16:15:57'),
(46, 'GA-CRU-002', 'Benelli 502C Cruiser', 'benelli-502c-cruiser', 'Benelli 502C premium cruiser motorcycle.', 'Premium cruiser bike with powerful engine.', 1450000.00, 1650000.00, 1450000.00, 30000.00, 1850000.00, 'uploads/items/benelli-502c-1.jpg', '[\"uploads/items/benelli-502c-1.jpg\"]', 'used', 'Benelli', '502C', 2023, 9000, 'Petrol', 'Black', 'Auto Bhan Road', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-26 15:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 287, 11, 20, 2, 9, '', '', NULL, NULL, 'Benelli 502C Cruiser Auction', 'Benelli 502C cruiser bike available for live auction.', 'Benelli 502C, cruiser motorcycle, Hyderabad', '2026-08-21 18:25:00', '2026-09-21 16:15:57'),
(47, 'GA-NAK-001', 'Yamaha MT-15', 'yamaha-mt-15', 'Yamaha MT-15 naked street motorcycle in excellent condition.', 'Stylish Yamaha MT-15 street bike.', 550000.00, 650000.00, 550000.00, 10000.00, 720000.00, 'uploads/items/yamaha-mt15-1.jpg', '[\"uploads/items/yamaha-mt15-1.jpg\"]', 'used', 'Yamaha', 'MT-15', 2022, 15000, 'Petrol', 'Black', 'Qasimabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.34300000, '2026-09-20 15:59:28', '2026-09-27 15:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 301, 12, 23, 2, 10, '', '', NULL, NULL, 'Yamaha MT-15 Auction Hyderabad', 'Yamaha MT-15 naked bike available for auction.', 'Yamaha MT15, naked bike, Hyderabad auction', '2026-08-21 18:30:00', '2026-09-21 16:15:57'),
(48, 'GA-NAK-002', 'KTM Duke 390', 'ktm-duke-390', 'KTM Duke 390 high performance naked motorcycle.', 'Powerful KTM Duke 390 street bike.', 1050000.00, 1200000.00, 1050000.00, 25000.00, 1350000.00, 'uploads/items/ktm-duke-390-1.jpg', '[\"uploads/items/ktm-duke-390-1.jpg\"]', 'used', 'KTM', 'Duke 390', 2021, 17000, 'Petrol', 'Orange', 'Latifabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.38300000, 68.37300000, '2026-09-20 15:59:28', '2026-09-28 15:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 350, 13, 28, 2, 10, '', '', NULL, NULL, 'KTM Duke 390 Auction Hyderabad', 'KTM Duke 390 naked motorcycle available for auction.', 'KTM Duke 390, motorcycle, Hyderabad', '2026-08-21 18:35:00', '2026-09-21 16:15:57'),
(49, 'GA-ADV-001', 'BMW G 310 GS', 'bmw-g-310-gs', 'BMW G 310 GS adventure motorcycle suitable for touring and off-road use.', 'BMW adventure motorcycle in excellent condition.', 1650000.00, 1850000.00, 1650000.00, 30000.00, 2100000.00, 'uploads/items/bmw-g310gs-1.jpg', '[\"uploads/items/bmw-g310gs-1.jpg\"]', 'used', 'BMW', 'G 310 GS', 2022, 11000, 'Petrol', 'White', 'Auto Bhan Road', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-22 15:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 267, 9, 19, 2, 11, '', '', NULL, NULL, 'BMW G 310 GS Adventure Auction', 'BMW G 310 GS adventure motorcycle available in Hyderabad.', 'BMW G310GS, adventure bike, Hyderabad', '2026-08-21 18:40:00', '2026-09-21 16:15:57'),
(50, 'GA-ADV-002', 'Honda CB 500X', 'honda-cb-500x', 'Honda CB 500X adventure touring motorcycle.', 'Comfortable Honda adventure bike for long trips.', 1450000.00, 1650000.00, 1450000.00, 25000.00, 1850000.00, 'uploads/items/honda-cb500x-1.jpg', '[\"uploads/items/honda-cb500x-1.jpg\"]', 'used', 'Honda', 'CB 500X', 2021, 19000, 'Petrol', 'Red', 'Qasimabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.34300000, '2026-09-20 15:59:28', '2026-09-23 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 154, 6, 12, 2, 11, '', '', NULL, NULL, 'Honda CB 500X Auction Hyderabad', 'Honda CB 500X adventure bike available for auction.', 'Honda CB500X, adventure motorcycle, Sindh', '2026-08-21 18:45:00', '2026-09-21 16:15:57'),
(51, 'GA-EBK-001', 'Yadea Electric Bike', 'yadea-electric-bike', 'Modern electric bike suitable for city commuting in Hyderabad.', 'Eco-friendly electric motorcycle.', 180000.00, 220000.00, 180000.00, 5000.00, 260000.00, 'uploads/items/yadea-electric-bike-1.jpg', '[\"uploads/items/yadea-electric-bike-1.jpg\"]', 'used', 'Yadea', 'Electric Bike', 2024, 5000, 'Electric', 'Black', 'Qasimabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.34300000, '2026-09-20 15:59:28', '2026-09-24 15:59:28', '0000-00-00 00:00:00', 'active', 1, 1, 222, 8, 15, 2, 13, '', '', NULL, NULL, 'Yadea Electric Bike Auction', 'Yadea electric bike available for auction in Hyderabad Sindh.', 'Yadea, electric bike, Hyderabad auction', '2026-08-21 18:50:00', '2026-09-21 16:15:57'),
(52, 'GA-EBK-002', 'Metro T9 Electric Bike', 'metro-t9-electric-bike', 'Metro T9 electric bike with rechargeable battery.', 'Affordable electric bike for daily commuting.', 145000.00, 180000.00, 145000.00, 5000.00, 210000.00, 'uploads/items/metro-t9-1.jpg', '[\"uploads/items/metro-t9-1.jpg\"]', 'used', 'Metro', 'T9', 2024, 4000, 'Electric', 'Red', 'Latifabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.38300000, 68.37300000, '2026-09-20 15:59:28', '2026-09-25 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 132, 5, 9, 2, 13, '', '', NULL, NULL, 'Metro T9 Electric Bike Auction', 'Metro T9 electric bike available in Hyderabad.', 'Metro T9, electric bike, Sindh', '2026-08-21 18:55:00', '2026-09-21 16:15:57'),
(53, 'GA-TST-001', 'Suzuki Alto Test Vehicle', 'suzuki-alto-test-vehicle', 'Test vehicle record for development and auction system testing.', 'Test vehicle item for auction system.', 1500000.00, 1800000.00, 1500000.00, 25000.00, 2000000.00, 'uploads/items/test-alto-1.jpg', '[\"uploads/items/test-alto-1.jpg\"]', 'used', 'Suzuki', 'Alto', 2020, 45000, 'Petrol', 'White', 'Hyderabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-09-20 15:59:28', '2026-09-26 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 45, 2, 4, 2, 18, '', '', NULL, NULL, 'Test Suzuki Alto Auction', 'Test vehicle item for auction system development.', 'test vehicle, Suzuki Alto, Hyderabad', '2026-08-21 19:00:00', '2026-09-21 16:15:57'),
(54, 'GA-TST-002', 'Toyota Yaris Test Vehicle', 'toyota-yaris-test-vehicle', 'Test Toyota Yaris item for auction system.', 'Test vehicle for category demonstration.', 2500000.00, 2900000.00, 2500000.00, 50000.00, 3300000.00, 'uploads/items/test-yaris-1.jpg', '[\"uploads/items/test-yaris-1.jpg\"]', 'used', 'Toyota', 'Yaris', 2021, 32000, 'Petrol', 'Silver', 'Qasimabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.34300000, '2026-09-20 15:59:28', '2026-09-27 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 52, 3, 6, 2, 18, '', '', NULL, NULL, 'Test Toyota Yaris Auction', 'Test Toyota Yaris item for auction system testing.', 'test vehicle, Toyota Yaris, Hyderabad', '2026-08-21 19:05:00', '2026-09-21 16:15:57'),
(55, 'GA-TEL-001', 'Test HP Laptop', 'test-hp-laptop', 'Test laptop item for auction system testing.', 'Test electronics auction item.', 50000.00, 65000.00, 50000.00, 2500.00, 80000.00, 'uploads/items/test-hp-laptop-1.jpg', '[\"uploads/items/test-hp-laptop-1.jpg\"]', 'used', 'HP', 'ProBook', 2021, NULL, 'Core i5', 'Silver', 'Latifabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.38300000, 68.37300000, '2026-09-20 15:59:28', '2026-09-28 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 37, 2, 3, 2, 19, '', '', NULL, NULL, 'Test HP Laptop Auction', 'Test HP laptop for auction system testing.', 'test electronics, HP laptop, Hyderabad', '2026-08-21 19:10:00', '2026-09-21 16:15:57'),
(56, 'GA-TEL-002', 'Test Samsung Smartphone', 'test-samsung-smartphone', 'Test Samsung smartphone item for auction system.', 'Test smartphone for development.', 35000.00, 45000.00, 35000.00, 2000.00, 55000.00, 'uploads/items/test-samsung-1.jpg', '[\"uploads/items/test-samsung-1.jpg\"]', 'used', 'Samsung', 'Galaxy', 2022, NULL, NULL, 'Black', 'Qasimabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.34300000, '2026-09-20 15:59:28', '2026-09-22 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 29, 1, 2, 2, 19, '', '', NULL, NULL, 'Test Samsung Smartphone Auction', 'Test Samsung smartphone for auction system testing.', 'test smartphone, Samsung, Hyderabad', '2026-08-21 19:15:00', '2026-09-21 16:15:57'),
(57, 'GA-TPR-001', 'Test 120 Sq Yard Plot', 'test-120-yard-plot', 'Test property item for auction system development.', 'Test residential property listing.', 3000000.00, 3500000.00, 3000000.00, 50000.00, 4000000.00, 'uploads/items/test-plot-120-1.jpg', '[\"uploads/items/test-plot-120-1.jpg\"]', 'new', NULL, NULL, NULL, NULL, NULL, 'North Facing', 'Qasimabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.34300000, '2026-09-20 15:59:28', '2026-09-23 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 41, 2, 4, 2, 20, '', '', NULL, NULL, 'Test 120 Yard Plot Auction', 'Test property listing for auction system.', 'test property, plot, Hyderabad', '2026-08-21 19:20:00', '2026-09-21 16:15:57'),
(58, 'GA-TPR-002', 'Test 240 Sq Yard House', 'test-240-yard-house', 'Test residential house item for auction system.', 'Test property listing for development.', 8500000.00, 9500000.00, 8500800.00, 100000.00, 11000000.00, 'uploads/items/test-house-240-1.jpg', '[\"uploads/items/test-house-240-1.jpg\"]', 'used', NULL, NULL, 2020, NULL, NULL, 'White', 'Latifabad Unit 6', 'Hyderabad', 'Sindh', 'Pakistan', 25.38300000, 68.37300000, '2026-09-20 15:59:28', '2026-09-24 15:59:28', '0000-00-00 00:00:00', 'active', 0, 1, 36, 3, 3, 2, 20, '', '', NULL, NULL, 'Test 240 Yard House Auction', 'Test residential property for auction system testing.', 'test house, property, Hyderabad', '2026-08-21 19:25:00', '2026-09-21 16:15:57'),
(59, 'GA-ESMKGR', 'Mahran', '', 'The Suzuki Mehran is a iconic 5-door hatchback produced by Pak', NULL, 600000.00, NULL, 600000.00, 1.00, NULL, 'uploads/items/item_1787415803_9474_0.jfif', '[\"uploads\\/items\\/item_1787415803_9474_0.jfif\",\"uploads\\/items\\/item_1787415803_3796_1.jfif\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 15:59:28', '2026-09-25 15:59:28', NULL, 'active', 0, 1, 0, 0, 0, 2, 1, '', '', NULL, NULL, NULL, NULL, NULL, '2026-08-22 21:23:23', '2026-09-21 16:15:57'),
(60, 'GA-DGYHXA', 'mahran', '', 'The Suzuki Mehran is a iconic 5-door hatchback produced by Pak', NULL, 770000.00, NULL, 888899990.00, 1.00, NULL, 'uploads/items/item_1787417757_7118_0.jfif', '[\"uploads\\/items\\/item_1787417757_7118_0.jfif\",\"uploads\\/items\\/item_1787417757_4362_1.jfif\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 15:59:28', '2026-09-26 15:59:28', NULL, 'active', 0, 1, 0, 1, 0, 2, 1, '', '', 3, 15, NULL, NULL, NULL, '2026-08-22 21:55:57', '2026-09-21 16:15:57'),
(61, 'GA-B6NJUX', 'mahran', '', 'The Suzuki Mehran is a iconic 5-door hatchback produced by Pak', NULL, 777779.00, NULL, 777783.00, 1.00, NULL, 'uploads/items/item_1787419976_3107_0.jfif', '[\"uploads\\/items\\/item_1787419976_3107_0.jfif\",\"uploads\\/items\\/item_1787419976_2307_1.jfif\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 15:59:28', '2026-09-27 15:59:28', NULL, 'active', 0, 1, 0, 1, 0, 2, 2, '', '', 3, 16, NULL, NULL, NULL, '2026-08-22 22:32:56', '2026-09-21 16:15:57'),
(62, 'GA-R9PFA3', 'mahran', '', 'the mahrasn simple car', NULL, 700000.00, NULL, 7050000.00, 1.00, NULL, 'uploads/items/item_1787484524_5165_0.jfif', '[\"uploads\\/items\\/item_1787484524_5165_0.jfif\",\"uploads\\/items\\/item_1787484524_9665_1.jfif\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 15:59:28', '2026-09-28 15:59:28', NULL, 'active', 0, 1, 0, 1, 0, 2, 1, '', '', 3, 17, NULL, NULL, NULL, '2026-08-23 16:28:44', '2026-09-21 16:15:57'),
(63, 'GA-WXBK9E', 'Infinix Smart 20', '', '6.78 inchesDisplay 4GB UP TO 8GB (UP TO 8GB)RAM battery-specs-image 5200 mAhBattery backcamera-specs-image. Display Screen Size 6.78 inches Screen Resolution ...', NULL, 450000.00, NULL, 450000.00, 1.00, NULL, 'uploads/items/item_1787776903_7659_0.webp', '[\"uploads\\/items\\/item_1787776903_7659_0.webp\",\"uploads\\/items\\/item_1787776903_5044_1.jpg\",\"uploads\\/items\\/item_1787776903_6866_2.webp\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 15:59:28', '2026-09-22 15:59:28', NULL, 'active', 0, 1, 3, 0, 0, 2, 19, '', '', NULL, NULL, NULL, NULL, NULL, '2026-08-27 01:41:43', '2026-09-21 16:15:57'),
(64, 'GA-JPD78V', 'Yaris', '', 'You can view more details and local updates directly on geo-auction or the Toyota Indus portal. The price of Toyota Yaris Sedan 2026 in Pakistan ranges from PKR 4,649,000 for the base variant GLI MT 1.3 to PKR 6,049,000 for the top of the line ATIV X CVT 1.5 Black Interior variant.', NULL, 4600000.00, NULL, 9999999999.99, 1.00, NULL, 'uploads/items/item_1788325046_8743_0.jfif', '[\"uploads\\/items\\/item_1788325046_8743_0.jfif\",\"uploads\\/items\\/item_1788325046_4732_1.jfif\",\"uploads\\/items\\/item_1788325046_5527_2.jfif\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-20 15:59:28', '2026-09-24 15:59:28', NULL, 'active', 0, 1, 14, 3, 0, 2, 1, 'karachi', 'maleer', NULL, NULL, NULL, NULL, NULL, '2026-09-02 09:57:26', '2026-09-21 16:18:30');


-- --------------------------------------------------------
-- Structure for table `messages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_starred` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `parent_id` (`parent_id`),
  KEY `idx_messages_sender` (`sender_id`),
  KEY `idx_messages_receiver` (`receiver_id`),
  KEY `idx_messages_read` (`receiver_id`,`is_read`),
  KEY `idx_messages_created` (`created_at`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_ibfk_4` FOREIGN KEY (`parent_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `messages`

-- --------------------------------------------------------
-- Structure for table `notifications`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_global` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_read` (`user_id`,`is_read`),
  KEY `idx_notifications_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `notifications`
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `icon`, `is_read`, `is_global`, `read_at`, `created_at`) VALUES
(6, 2, 'bid', 'New Bid Alert', 'New bid of Rs. 8500800 placed on your item!', 'live_auction.php?id=58', 'gavel', 0, 0, NULL, '2026-08-25 12:31:30'),
(7, 2, 'bid', 'New Bid Alert', 'New bid of Rs. 22233444 placed on your item!', 'live_auction.php?id=64', 'gavel', 0, 0, NULL, '2026-09-09 09:32:52'),
(8, 2, 'bid', 'New Bid Alert', 'New bid of Rs. 13333333333 placed on your item!', 'live_auction.php?id=64', 'gavel', 0, 0, NULL, '2026-09-10 11:35:54'),
(9, 2, 'bid', 'New Bid Alert', 'New bid of Rs. 13333333333 placed on your item!', 'live_auction.php?id=64', 'gavel', 0, 0, NULL, '2026-09-10 11:53:25');


-- --------------------------------------------------------
-- Structure for table `payments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `bid_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_type` varchar(50) NOT NULL DEFAULT 'auction_win',
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `billing_name` varchar(100) DEFAULT NULL,
  `billing_email` varchar(100) DEFAULT NULL,
  `billing_phone` varchar(20) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `billing_city` varchar(100) DEFAULT NULL,
  `billing_state` varchar(100) DEFAULT NULL,
  `billing_zip` varchar(20) DEFAULT NULL,
  `billing_country` varchar(100) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `buyer_id` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `item_id` (`item_id`),
  KEY `bid_id` (`bid_id`),
  KEY `idx_payments_user` (`user_id`),
  KEY `idx_payments_status` (`payment_status`),
  KEY `idx_payments_invoice` (`invoice_no`),
  KEY `idx_payments_created` (`created_at`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`bid_id`) REFERENCES `bids` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `payments`

-- --------------------------------------------------------
-- Structure for table `reports`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `report_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reporter_id` (`reporter_id`),
  KEY `reported_user_id` (`reported_user_id`),
  KEY `item_id` (`item_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_reports_status` (`status`),
  KEY `idx_reports_type` (`report_type`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `reports`
INSERT INTO `reports` (`id`, `reporter_id`, `reported_user_id`, `item_id`, `report_type`, `description`, `status`, `admin_notes`, `reviewed_by`, `created_at`, `updated_at`) VALUES
(1, 2, 1, NULL, 'Fake Listing', 'The Yamaha MT-15 listing appears to contain misleading information about the bike condition.', 'pending', NULL, NULL, '2026-07-31 11:26:45', '2026-07-31 11:26:45');


-- --------------------------------------------------------
-- Structure for table `reviews`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reviewer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_reviews_user` (`user_id`),
  KEY `idx_reviews_rating` (`rating`),
  KEY `idx_reviews_approved` (`is_approved`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `reviews`

-- --------------------------------------------------------
-- Structure for table `roles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `roles`

-- --------------------------------------------------------
-- Structure for table `settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `group` varchar(50) NOT NULL DEFAULT 'general',
  `type` varchar(20) NOT NULL DEFAULT 'text',
  `label` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  KEY `idx_settings_key` (`key`),
  KEY `idx_settings_group` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `settings`
INSERT INTO `settings` (`id`, `key`, `value`, `group`, `type`, `label`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Geo Auction System', 'general', 'text', 'Site Name', NULL, 1, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(2, 'site_description', 'Premium online auction platform for premium items', 'general', 'textarea', 'Site Description', NULL, 2, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(3, 'site_email', 'info@geoauction.com', 'general', 'text', 'Site Email', NULL, 3, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(4, 'site_phone', '+1-234-567-8900', 'general', 'text', 'Site Phone', NULL, 4, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(5, 'site_address', '123 Auction Street, New York, NY 10001', 'general', 'textarea', 'Site Address', NULL, 5, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(6, 'currency_symbol', 'Rs ', 'general', 'text', 'Currency Symbol', NULL, 6, '2026-07-29 18:27:35', '2026-08-20 16:29:52'),
(7, 'currency_code', 'PKR', 'general', 'text', 'Currency Code', NULL, 7, '2026-07-29 18:27:35', '2026-08-20 16:29:52'),
(8, 'commission_rate', 5, 'auction', 'text', 'Commission Rate (%)', NULL, 8, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(9, 'min_bid_increment', 1, 'auction', 'text', 'Minimum Bid Increment ($)', NULL, 9, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(10, 'auto_extend_enabled', 1, 'auction', 'boolean', 'Auto Extend on Last Minute Bids', NULL, 10, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(11, 'auto_extend_minutes', 2, 'auction', 'text', 'Auto Extend Minutes', NULL, 11, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(12, 'max_image_size', 5, 'upload', 'text', 'Max Image Size (MB)', NULL, 12, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(13, 'allowed_image_types', 'jpg,jpeg,png,gif,webp', 'upload', 'text', 'Allowed Image Types', NULL, 13, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(14, 'smtp_host', '', 'email', 'text', 'SMTP Host', NULL, 14, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(15, 'smtp_port', 587, 'email', 'text', 'SMTP Port', NULL, 15, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(16, 'smtp_username', '', 'email', 'text', 'SMTP Username', NULL, 16, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(17, 'smtp_password', '', 'email', 'password', 'SMTP Password', NULL, 17, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(18, 'google_maps_api_key', '', 'api', 'text', 'Google Maps API Key', NULL, 18, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(19, 'enable_registration', 1, 'general', 'boolean', 'Enable User Registration', NULL, 19, '2026-07-29 18:27:35', '2026-07-29 18:27:35'),
(20, 'require_email_verification', 0, 'general', 'boolean', 'Require Email Verification', NULL, 20, '2026-07-29 18:27:35', '2026-07-29 18:27:35');


-- --------------------------------------------------------
-- Structure for table `transactions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdraw','bid','refund','winning_payment') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_before` decimal(12,2) DEFAULT 0.00,
  `balance_after` decimal(12,2) DEFAULT 0.00,
  `item_id` int(11) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `item_id` (`item_id`),
  KEY `type` (`type`),
  KEY `status` (`status`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `transactions`
INSERT INTO `transactions` (`id`, `user_id`, `type`, `amount`, `balance_before`, `balance_after`, `item_id`, `reference_id`, `description`, `status`, `created_at`) VALUES
(3, 1, '', 22000.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-18 14:37:55'),
(4, 1, '', 500.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-18 14:38:01'),
(5, 2, '', 500.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-20 22:16:12'),
(6, 2, '', 2000.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-22 21:16:23'),
(7, 2, '', 200000.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-22 21:16:35'),
(8, 1, '', 5000.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-31 14:46:05');


-- --------------------------------------------------------
-- Structure for table `user_notifications`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email_bids` tinyint(1) DEFAULT 1,
  `email_outbid` tinyint(1) DEFAULT 1,
  `email_won` tinyint(1) DEFAULT 1,
  `email_newsletter` tinyint(1) DEFAULT 0,
  `sms_bids` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `user_notifications`
INSERT INTO `user_notifications` (`id`, `user_id`, `email_bids`, `email_outbid`, `email_won`, `email_newsletter`, `sms_bids`) VALUES
(1, 1, 1, 1, 1, 0, 0);


-- --------------------------------------------------------
-- Structure for table `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'buyer',
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expires_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `balance` int(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `avatar`, `bio`, `gender`, `status`, `email_verified_at`, `reset_token`, `reset_token_expires_at`, `remember_token`, `last_login_at`, `last_login_ip`, `settings`, `created_at`, `updated_at`, `balance`) VALUES
(1, 'Admin User', 'admin@gmail.com', '', '$2a$12$OnYUAuNp52QU5o9ATAJTtexqhCZXJneqV0BZK4iYjlDwIt3/Llcuu', 'admin', 'uploads/avatars/avatar_1_1787205445.png', NULL, 'male', 'active', '2026-07-29 18:27:35', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 18:27:35', '2026-08-31 14:46:05', 27500),
(2, 'pawan', 'pawan@gmail.com', '0322233344', '$2a$12$itAogsUdaTUlzF7uAXOKV.BndvE46IpX1IbpRoxhBekn.0jO6mpLC', 'seller ', 'uploads/avatars/avatar_2_1787347496.jfif', '', 'male', 'active', '2026-07-29 18:27:35', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 18:27:35', '2026-08-22 21:16:35', 202500),
(3, 'aakash', 'aakash@gmail.com', NULL, '$2a$12$h9akJRIBio2/fg.HAB3ZsucIundZ6BD37M/o5u3qn1TtBjM6sSoJW', 'buyer', NULL, NULL, 'male', 'active', '2026-07-29 18:27:35', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 18:27:35', '2026-08-21 23:26:51', 0),
(4, 'Ali Khan', 'ali.khan@example.com', +923001234567, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2...', 'user', NULL, 'Looking to buy cars and bikes.', 'male', 'active', '2026-08-01 10:00:00', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-01 10:00:00', '2026-08-20 10:00:00', 5000),
(5, 'Sara Ahmed', 'sara.ahmed@example.com', +923219876543, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2...', 'user', NULL, 'Frequent seller on geo auction.', 'female', 'active', '2026-08-05 12:30:00', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 12:30:00', '2026-08-20 10:00:00', 15001),
(6, 'Michael Smith', 'michael.smith@example.com', +15550199, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2...', 'user', NULL, 'Car enthusiast from US.', 'male', 'active', '2026-08-10 15:20:00', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-10 15:20:00', '2026-08-20 10:00:00', 1200),
(29, 'dua memon', 'dua@gmail.com', '090990909', '$2y$10$MZo5Vb6TVZzkOpSPJTF4Peit8PdSDvAAYu9Stk/Ynb9/OV5OgLhxG', 'seller', 'assets/image/male.png', NULL, 'male', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-22 01:47:37', '2026-08-22 01:47:37', 0),
(30, 'shifa', 'shifa@gmail.com', 77777, '$2y$10$ZSbVPChQiElHvpO4XPMf5eXm9ls73.3O7EiEyl6RyHO5s4Vt36eK6', 'seller', 'assets/image/female.png', NULL, 'female', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-22 01:49:24', '2026-08-22 01:49:24', 0),
(31, 'rani', 'rani@gmail.com', 88888888, '$2y$10$j/qKhSXNcgEmAYMdblZk2uDb6GCcr/DdlWNn/yEslOheE8X3P4C7S', 'seller', 'assets/image/female.png', NULL, 'female', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-22 01:51:15', '2026-08-22 01:51:15', 0),
(32, 'almas', 'almas@gmail.com', 888888888, '$2y$10$hNklEU1BVP6JuYozmDUpseKz0/PcExnyZXqTgzfQwdGwUlRhnP2lK', 'buyer', 'assets/image/female.png', NULL, 'female', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-22 02:37:36', '2026-08-22 02:37:36', 0),
(33, 'komal', 'komal@gmail.com', 56565, '$2y$10$tuoVi80DZa.7CTSN3SXqXeCgdPBP0brtc32qk9zDE/w.ouMABrgRG', 'buyer', 'assets/image/male.png', NULL, 'male', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-22 02:40:30', '2026-08-22 23:08:54', 0),
(34, 'raheel', 'raheel@gmail.com', 66666666, '$2y$10$BD6wfvKKfxzRkb7H31MwxuvTamrItwi87uFLdj1WmFNouKJqPZWlq', 'buyer', 'assets/image/male.png', NULL, 'male', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-23 17:33:58', '2026-08-23 17:33:58', 0),
(35, 'kumar', 'kumar@gmail.com', 12345678, '$2y$10$C0EoPCSxGfEM8zW1VvSL5e4IQr7GCdGD7xBNhyRIgy1pV6HLHhYHC', 'buyer', 'assets/image/male.png', NULL, 'male', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-27 15:10:34', '2026-08-27 15:10:34', 0);


-- --------------------------------------------------------
-- Structure for table `wishlist`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wishlist`;
CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wishlist` (`user_id`,`item_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_wishlist_user` (`user_id`),
  CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `wishlist`
INSERT INTO `wishlist` (`id`, `user_id`, `item_id`, `created_at`) VALUES
(26, 3, 60, '2026-08-22 22:02:33'),
(27, 3, 61, '2026-08-22 22:34:45'),
(28, 3, 62, '2026-08-23 16:36:01');

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
-- ========================================================
-- End of Database Backup
-- ========================================================
