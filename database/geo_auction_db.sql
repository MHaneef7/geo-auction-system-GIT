-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 19, 2026 at 11:14 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `geo_auction_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `model_type` varchar(100) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `model_type`, `model_id`, `ip_address`, `user_agent`, `request_data`, `created_at`) VALUES
(1, 4, 'register', 'User registered', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-29 23:54:38'),
(2, 4, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-29 23:55:44'),
(3, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-29 23:56:10'),
(4, 4, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 00:09:28'),
(5, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 00:09:51'),
(6, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 00:21:57'),
(7, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 00:41:49'),
(8, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 01:19:41'),
(9, 4, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 01:24:46'),
(10, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 01:42:46'),
(11, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 01:58:15'),
(12, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 02:17:56'),
(13, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 04:42:32'),
(14, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 05:15:27'),
(15, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 05:45:57'),
(16, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 06:04:28'),
(17, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 06:21:03'),
(18, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 06:29:38'),
(19, 4, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 06:30:16'),
(20, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 07:15:13'),
(21, 4, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 07:41:53'),
(22, 5, 'register', 'User registered', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:12:51'),
(23, 5, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:47:31'),
(24, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:48:31'),
(25, 5, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:52:25'),
(26, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:52:50'),
(27, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:56:55'),
(28, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:57:21'),
(29, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:58:30'),
(30, 5, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-30 23:58:35'),
(31, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 00:01:49'),
(32, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 00:39:59'),
(33, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 00:40:03'),
(34, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:02:49'),
(35, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:04:08'),
(36, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:10:35'),
(37, 1, 'CREATE_ITEM', 'Created a new auction item: Yamaha MT-15 2024', 'Item', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/138.0.0.0 Safari/537.36', '{\"title\":\"Yamaha MT-15 2024\",\"brand\":\"Yamaha\",\"model\":\"MT-15\",\"year\":2024,\"starting_price\":760000}', '2026-07-31 06:16:38'),
(38, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:18:07'),
(39, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:22:29'),
(40, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:23:22'),
(41, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:30:56'),
(42, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:31:24'),
(43, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:46:41'),
(44, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:53:24'),
(45, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 01:58:46'),
(46, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:01:24'),
(47, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:05:41'),
(48, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:11:56'),
(49, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:12:02'),
(50, 6, 'register', 'User registered', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:20:35'),
(51, 6, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:26:26'),
(52, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:27:34'),
(53, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:40:42'),
(54, 6, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:41:12'),
(55, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:44:27'),
(56, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 02:54:00'),
(57, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 05:17:59'),
(58, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 05:20:57'),
(59, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 05:50:01'),
(60, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:12:03'),
(61, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:19:44'),
(62, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 06:30:59'),
(63, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-31 07:19:17'),
(64, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 00:56:58'),
(65, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 00:58:17'),
(66, 5, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 00:59:03'),
(67, 5, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 00:59:53'),
(68, 7, 'register', 'User registered', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:01:27'),
(69, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:07:28'),
(70, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:10:20'),
(71, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:10:27'),
(72, 7, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:10:51'),
(73, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:13:22'),
(74, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:15:37'),
(75, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:33:24'),
(76, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:48:03'),
(77, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:51:39'),
(78, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 01:59:59'),
(79, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 02:25:36'),
(80, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 02:57:02'),
(81, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 02:57:42'),
(82, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 03:05:59'),
(83, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 03:28:31'),
(84, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 04:40:33'),
(85, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:06:28'),
(86, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:15:12'),
(87, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:15:48'),
(88, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:21:35'),
(89, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:21:39'),
(90, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:29:08'),
(91, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 05:46:13'),
(92, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 06:43:49'),
(93, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 07:06:44'),
(94, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 07:13:39'),
(95, 6, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 23:15:07'),
(96, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-03 23:49:39'),
(97, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-08-04 00:45:43'),
(98, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-09 23:22:04'),
(99, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-11 12:57:08'),
(100, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 06:59:29'),
(101, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 06:59:37'),
(102, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 07:03:34'),
(103, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 07:03:43'),
(104, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-12 23:20:53');

-- --------------------------------------------------------

--
-- Table structure for table `bids`
--

CREATE TABLE `bids` (
  `id` int(11) NOT NULL,
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
  `status` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bids`
--

INSERT INTO `bids` (`id`, `item_id`, `user_id`, `bidder_name`, `bidder_email`, `bid_amount`, `bid_status`, `is_winning`, `is_auto_bid`, `max_bid_amount`, `ip_address`, `user_agent`, `created_at`, `status`) VALUES
(2, 21, 2, 'Test Bidder 1', 'bidder1@test.com', 57500.00, 'active', 0, 0, NULL, '127.0.0.1', 'Mozilla/5.0', '2026-08-18 11:00:00', 'active'),
(3, 21, 3, 'Test Bidder 2', 'bidder2@test.com', 60000.00, 'active', 0, 0, NULL, '127.0.0.1', 'Mozilla/5.0', '2026-08-18 11:30:00', 'active'),
(4, 21, 4, 'Test Bidder 3', 'bidder3@test.com', 62500.00, 'active', 0, 0, NULL, '127.0.0.1', 'Mozilla/5.0', '2026-08-18 12:00:00', 'active'),
(5, 21, 2, 'Test Bidder 1', 'bidder1@test.com', 65000.00, 'active', 1, 0, NULL, '127.0.0.1', 'Mozilla/5.0', '2026-08-18 12:30:00', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-tag',
  `image` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `image`, `parent_id`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Motors', 'motors', 'Cars, bikes, boats, and vehicles', 'fa-car', NULL, NULL, 1, 'active', '2026-07-29 13:27:35', '2026-08-10 04:22:17'),
(2, 'Properties', 'properties', 'Real estate, land, and properties', 'fa-building', NULL, NULL, 2, 'active', '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(3, 'Prestige Plates', 'prestige-plates', 'VIP number plates and registrations', 'fa-hashtag', NULL, NULL, 3, 'active', '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(4, 'Jewelry & Watches', 'jewelry-watches', 'Luxury jewelry, watches, and gems', 'fa-gem', NULL, NULL, 4, 'inactive', '2026-07-29 13:27:35', '2026-08-03 06:54:16'),
(5, 'Electronics', 'electronics', 'Laptops, phones, gadgets, and tech', 'fa-laptop', NULL, NULL, 5, 'active', '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(6, 'Art & Collectibles', 'art-collectibles', 'Art, antiques, and collectible items', 'fa-palette', NULL, NULL, 6, 'active', '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(7, 'Government Surplus', 'government-surplus', 'Government and industrial surplus', 'fa-boxes-stacked', NULL, NULL, 7, 'active', '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(8, 'Sport Bikes', 'sport-bikes', 'High-performance sport motorcycles.', 'fas fa-motorcycle', 'uploads/categories/sport-bikes.jpg', NULL, 1, 'active', '2026-07-31 06:21:15', '2026-07-31 06:21:15'),
(9, 'Cruiser Bikes', 'cruiser-bikes', 'Comfortable motorcycles for long-distance touring.', 'fas fa-road', 'uploads/categories/cruiser-bikes.jpg', NULL, 2, 'inactive', '2026-07-31 06:21:15', '2026-07-31 10:51:04'),
(10, 'Naked Bikes', 'naked-bikes', 'Street motorcycles with aggressive styling.', 'fas fa-biking', 'uploads/categories/naked-bikes.jpg', NULL, 3, 'active', '2026-07-31 06:21:15', '2026-07-31 06:21:15'),
(11, 'Adventure Bikes', 'adventure-bikes', 'Motorcycles designed for on-road and off-road travel.', 'fas fa-mountain', 'uploads/categories/adventure-bikes.jpg', NULL, 4, 'active', '2026-07-31 06:21:15', '2026-07-31 06:21:15'),
(13, 'Electric Bikes', 'electric-bikes', 'Eco-friendly electric motorcycles.', 'fas fa-bolt', 'uploads/categories/electric-bikes.jpg', NULL, 6, 'inactive', '2026-07-31 06:21:15', '2026-08-04 05:00:03'),
(15, 'nisa', '', NULL, 'fa fa-tags', NULL, NULL, 0, 'active', '2026-08-17 04:35:45', '2026-08-17 04:35:45'),
(16, 'Cars & Vehicles', 'cars-vehicles', 'Automobiles and vehicles category', 'fa-tag', NULL, NULL, 0, 'active', '2026-08-18 10:16:33', '2026-08-18 10:16:33');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `replied` tinyint(1) NOT NULL DEFAULT 0,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `replied`, `replied_at`, `created_at`) VALUES
(1, 'Haneef', 'mhaneefalimemon@gmail.com', 'moter bike', 'moter bike', 0, 0, NULL, '2026-07-30 00:12:04'),
(2, 'Muhammad Haneef', 'mhaneefalimemon@gmail.com', 'moter bike', 'dd', 0, 0, NULL, '2026-08-03 01:06:46'),
(3, 'Admin', 'admin@geoauction.com', 'moter bike', 'dd', 0, 0, NULL, '2026-08-03 01:07:04');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
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
  `winner_id` int(11) DEFAULT NULL,
  `winning_bid_id` int(11) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `auction_code`, `title`, `slug`, `description`, `short_description`, `starting_price`, `reserve_price`, `current_price`, `min_increment`, `buy_it_now_price`, `featured_image`, `images`, `condition_type`, `brand`, `model`, `year`, `mileage`, `engine_type`, `color`, `location_address`, `location_city`, `location_state`, `location_country`, `location_lat`, `location_lng`, `start_time`, `end_time`, `extended_time`, `status`, `featured`, `approved`, `views`, `bid_count`, `watcher_count`, `seller_id`, `category_id`, `winner_id`, `winning_bid_id`, `meta_title`, `meta_description`, `meta_keywords`, `created_at`, `updated_at`) VALUES
(6, 'AUC-CD70-1001', 'Honda CD 70 2022', 'honda-cd-70-2022', 'Honda CD 70 2022 model in good condition. The motorcycle is suitable for daily commuting and has been maintained properly.', 'Honda CD 70 2022 model in good condition.', 85000.00, 95000.00, 88000.00, 2000.00, 110000.00, 'uploads/items/cd70-2022.jpg', '[\"uploads/items/cd70-2022-1.jpg\",\"uploads/items/cd70-2022-2.jpg\",\"uploads/items/cd70-2022-3.jpg\"]', 'used', 'Honda', 'CD 70', 2022, '18500', '70cc Petrol', 'Red', 'Latifabad', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-08-03 10:00:00', '2026-08-10 22:00:00', '0000-00-00 00:00:00', 'ended', 1, 1, 133, 4, 12, 2, 1, NULL, NULL, 'Honda CD 70 2022 Auction', 'Buy Honda CD 70 2022 through online auction.', 'Honda CD 70, Honda bike, 70cc, motorcycle auction', '2026-08-03 11:11:30', '2026-08-19 05:31:19'),
(18, 'AUC-TOY-2026-01', 'Toyota Camry SE - Clean Condition', 'toyota-camry-se-clean-condition', 'Well-maintained Toyota Camry SE with low mileage, reliable engine, and clean interior. Perfect daily driver.', 'Reliable and clean Toyota Camry SE available for auction.', 5000.00, 7500.00, 5000.00, 100.00, NULL, 'uploads/items/toyota-camry.jpg', '[\"uploads/items/toyota-camry-1.jpg\",\"uploads/items/toyota-camry-2.jpg\",\"uploads/items/toyota-camry-3.jpg\"]', 'Used', 'Toyota', 'Camry SE', 2021, '35000', '2.5L 4-Cylinder', 'Midnight Black Metallic', '123 Main Street', 'Houston', 'TX', 'USA', NULL, NULL, '2026-08-18 15:16:48', '2026-08-25 15:16:48', NULL, 'active', 1, 1, 0, 0, 0, 1, 1, NULL, NULL, NULL, NULL, NULL, '2026-08-18 10:16:48', '2026-08-19 05:31:19'),
(19, 'TEST-001', 'Test Toyota Corolla', 'test-toyota-corolla', 'This is dummy testing auction item.', 'Test Toyota Corolla', 100000.00, 150000.00, 110000.00, 5000.00, 200000.00, 'uploads/items/test-corolla.jpg', '[\"uploads/items/test-corolla-1.jpg\",\"uploads/items/test-corolla-2.jpg\",\"uploads/items/test-corolla-3.jpg\"]', 'Used', 'Toyota', 'Corolla', 2020, '50000', 'Petrol', 'White', 'Test Address', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-08-18 10:00:00', '2026-08-30 22:00:00', '0000-00-00 00:00:00', 'active', 1, 1, 0, 0, 0, 1, 1, NULL, NULL, 'Test Auction Item', 'Dummy auction item for testing.', 'test, auction, car', '2026-08-18 10:47:53', '2026-08-19 05:31:19'),
(20, 'TEST-002', 'Test Honda Civic', 'test-honda-civic', 'Dummy Honda Civic for testing auction functionality.', 'Test Honda Civic', 200000.00, 250000.00, 210000.00, 5000.00, 300000.00, 'uploads/items/test-honda.jpg', '[\"uploads/items/test-honda-1.jpg\",\"uploads/items/test-honda-2.jpg\",\"uploads/items/test-honda-3.jpg\"]', 'Used', 'Honda', 'Civic', 2021, '40000', 'Petrol', 'Black', 'Test Address 2', 'Karachi', 'Sindh', 'Pakistan', 24.86070000, 67.00110000, '2026-08-18 10:00:00', '2026-09-01 22:00:00', '0000-00-00 00:00:00', 'active', 0, 1, 0, 0, 0, 1, 1, NULL, NULL, 'Test Honda Civic Auction', 'Dummy Honda Civic for testing.', 'test, honda, civic, auction', '2026-08-18 10:47:53', '2026-08-19 05:31:19'),
(21, 'TEST-003', 'Test Suzuki Alto', 'test-suzuki-alto', 'Dummy Suzuki Alto for testing.', 'Test Suzuki Alto', 50000.00, 80000.00, 55000.00, 2500.00, 100000.00, 'uploads/items/test-alto.jpg', '[\"uploads/items/test-alto-1.jpg\",\"uploads/items/test-alto-2.jpg\",\"uploads/items/test-alto-3.jpg\"]', 'Used', 'Suzuki', 'Alto', 2022, '30000', 'Petrol', 'Silver', 'Test Address 3', 'Hyderabad', 'Sindh', 'Pakistan', 25.39600000, 68.35780000, '2026-08-18 10:00:00', '2026-09-05 22:00:00', '0000-00-00 00:00:00', 'active', 0, 1, 0, 0, 0, 1, 1, NULL, NULL, 'Test Suzuki Alto Auction', 'Dummy Suzuki Alto for testing.', 'test, suzuki, alto, auction', '2026-08-18 10:47:53', '2026-08-19 05:31:19'),
(22, 'AUC2026003', 'Hi-Speed 70cc 2023', 'hi-speed-70cc-2023', 'Hi-Speed 70cc motorcycle in good condition. Economical and reliable bike for daily commuting.', 'Hi-Speed 70cc economical motorcycle in good condition.', 95000.00, 120000.00, 95000.00, 3000.00, 140000.00, 'uploads/items/hi-speed-70cc-2023.jpg', '[\"uploads/items/hi-speed-70cc-2023-1.jpg\",\"uploads/items/hi-speed-70cc-2023-2.jpg\"]', 'Good', 'Hi-Speed', '70cc', 2023, '12000', 'Petrol', 'Black', 'Main Road', 'Karachi', 'Sindh', 'Pakistan', 24.86070000, 67.00110000, '2026-08-19 13:00:00', '2026-08-26 20:00:00', '0000-00-00 00:00:00', 'active', 0, 1, 18, 0, 2, 1, 1, NULL, NULL, 'Hi-Speed 70cc 2023 Auction', 'Buy Hi-Speed 70cc 2023 through live online auction.', 'Hi-Speed 70cc, 70cc bike, motorcycle auction, Pakistan', '2026-08-19 06:02:17', '2026-08-19 06:08:31'),
(23, 'AUC2026002', 'Honda 125 2024', 'honda-125-2024', 'Honda 125cc motorcycle in excellent condition. Well maintained and suitable for daily use.', 'Honda 125cc in excellent condition with complete documents.', 180000.00, 220000.00, 180000.00, 5000.00, 250000.00, 'uploads/items/honda-125-2024.jpg', '[\"uploads/items/honda-125-2024-1.jpg\",\"uploads/honda-125-2024-2.jpg\"]', 'Excellent', 'Honda', '125', 2024, '8500', 'Petrol', 'Red', 'Main Road', 'Karachi', 'Sindh', 'Pakistan', 24.86070000, 67.00110000, '2026-08-19 12:00:00', '2026-08-25 20:00:00', '0000-00-00 00:00:00', 'active', 1, 1, 25, 0, 3, 1, 1, NULL, NULL, 'Honda 125 2024 Auction', 'Buy Honda 125 2024 in excellent condition through live auction.', 'Honda 125, Honda bike, 125cc motorcycle, auction', '2026-08-19 06:02:35', '2026-08-19 06:08:54');

-- --------------------------------------------------------

--
-- Table structure for table `item_images`
--

CREATE TABLE `item_images` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_reports`
--

CREATE TABLE `item_reports` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_starred` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `item_id`, `subject`, `message`, `is_read`, `is_starred`, `parent_id`, `read_at`, `created_at`) VALUES
(1, 5, 1, NULL, NULL, 'Asslmaulikum Sir,', 1, 0, NULL, NULL, '2026-07-31 00:39:30'),
(2, 5, 1, NULL, NULL, 'Asslmaulikum Sir,', 1, 0, NULL, NULL, '2026-07-31 00:40:25'),
(3, 5, 1, NULL, NULL, 'Asslmaulikum Sir,', 1, 0, NULL, NULL, '2026-07-31 00:58:41'),
(4, 1, 5, NULL, NULL, 'Hey', 1, 0, NULL, NULL, '2026-07-31 02:01:04'),
(5, 5, 1, NULL, NULL, 'yes', 1, 0, NULL, NULL, '2026-07-31 02:01:47'),
(6, 1, 5, NULL, NULL, 'I Love You', 1, 0, NULL, NULL, '2026-07-31 02:02:38'),
(7, 5, 1, NULL, NULL, 'I love  you 2', 1, 0, NULL, NULL, '2026-07-31 02:03:31'),
(8, 6, 1, NULL, NULL, 'Sir\r\njjjjj', 1, 0, NULL, NULL, '2026-07-31 02:41:07'),
(9, 5, 1, NULL, NULL, 'yes', 1, 0, NULL, NULL, '2026-08-03 00:59:48'),
(10, 1, 6, NULL, NULL, 'hey', 1, 0, NULL, NULL, '2026-08-03 01:13:55'),
(11, 6, 1, NULL, NULL, 'mai pagal hon', 1, 0, NULL, NULL, '2026-08-03 01:14:25'),
(12, 1, 6, NULL, NULL, 'hello', 1, 0, NULL, NULL, '2026-08-03 01:52:04'),
(13, 6, 7, NULL, NULL, 'hell', 0, 0, NULL, NULL, '2026-08-03 05:15:35');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_global` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
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
  `buyer_id` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `report_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `reporter_id`, `reported_user_id`, `item_id`, `report_type`, `description`, `status`, `admin_notes`, `reviewed_by`, `created_at`, `updated_at`) VALUES
(1, 2, 1, NULL, 'Fake Listing', 'The Yamaha MT-15 listing appears to contain misleading information about the bike condition.', 'pending', NULL, NULL, '2026-07-31 06:26:45', '2026-07-31 06:26:45');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `reviewer_id`, `user_id`, `item_id`, `rating`, `review_text`, `is_approved`, `created_at`, `updated_at`) VALUES
(1, 2, 4, NULL, 5, 'Excellent seller! The Yamaha MT-15 2024 was exactly as described. Smooth transaction, quick response, and highly recommended.', 1, '2026-07-31 06:27:47', '2026-07-31 06:27:47');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `group` varchar(50) NOT NULL DEFAULT 'general',
  `type` varchar(20) NOT NULL DEFAULT 'text',
  `label` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `group`, `type`, `label`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Geo Auction System', 'general', 'text', 'Site Name', NULL, 1, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(2, 'site_description', 'Premium online auction platform for premium items', 'general', 'textarea', 'Site Description', NULL, 2, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(3, 'site_email', 'info@geoauction.com', 'general', 'text', 'Site Email', NULL, 3, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(4, 'site_phone', '+1-234-567-8900', 'general', 'text', 'Site Phone', NULL, 4, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(5, 'site_address', '123 Auction Street, New York, NY 10001', 'general', 'textarea', 'Site Address', NULL, 5, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(6, 'currency_symbol', '$', 'general', 'text', 'Currency Symbol', NULL, 6, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(7, 'currency_code', 'USD', 'general', 'text', 'Currency Code', NULL, 7, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(8, 'commission_rate', '5', 'auction', 'text', 'Commission Rate (%)', NULL, 8, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(9, 'min_bid_increment', '1', 'auction', 'text', 'Minimum Bid Increment ($)', NULL, 9, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(10, 'auto_extend_enabled', '1', 'auction', 'boolean', 'Auto Extend on Last Minute Bids', NULL, 10, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(11, 'auto_extend_minutes', '2', 'auction', 'text', 'Auto Extend Minutes', NULL, 11, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(12, 'max_image_size', '5', 'upload', 'text', 'Max Image Size (MB)', NULL, 12, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(13, 'allowed_image_types', 'jpg,jpeg,png,gif,webp', 'upload', 'text', 'Allowed Image Types', NULL, 13, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(14, 'smtp_host', '', 'email', 'text', 'SMTP Host', NULL, 14, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(15, 'smtp_port', '587', 'email', 'text', 'SMTP Port', NULL, 15, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(16, 'smtp_username', '', 'email', 'text', 'SMTP Username', NULL, 16, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(17, 'smtp_password', '', 'email', 'password', 'SMTP Password', NULL, 17, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(18, 'google_maps_api_key', '', 'api', 'text', 'Google Maps API Key', NULL, 18, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(19, 'enable_registration', '1', 'general', 'boolean', 'Enable User Registration', NULL, 19, '2026-07-29 13:27:35', '2026-07-29 13:27:35'),
(20, 'require_email_verification', '0', 'general', 'boolean', 'Require Email Verification', NULL, 20, '2026-07-29 13:27:35', '2026-07-29 13:27:35');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdraw','bid','refund','winning_payment') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_before` decimal(12,2) DEFAULT 0.00,
  `balance_after` decimal(12,2) DEFAULT 0.00,
  `item_id` int(11) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `amount`, `balance_before`, `balance_after`, `item_id`, `reference_id`, `description`, `status`, `created_at`) VALUES
(1, 11, '', 33.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-17 04:25:54'),
(2, 11, '', 10000.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-17 04:26:02'),
(3, 1, '', 22000.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-18 09:37:55'),
(4, 1, '', 500.00, 0.00, 0.00, NULL, NULL, 'Wallet recharge', 'completed', '2026-08-18 09:38:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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
  `balance` int(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `avatar`, `bio`, `gender`, `status`, `email_verified_at`, `reset_token`, `reset_token_expires_at`, `remember_token`, `last_login_at`, `last_login_ip`, `settings`, `created_at`, `updated_at`, `balance`) VALUES
(1, 'Admin User', 'admin@geoauction.com', '', '$2a$12$OnYUAuNp52QU5o9ATAJTtexqhCZXJneqV0BZK4iYjlDwIt3/Llcuu', 'admin', NULL, '', '', 'active', '2026-07-29 13:27:35', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 13:27:35', '2026-08-18 09:38:01', 22500),
(2, 'John Seller', 'seller@geoauction.com', NULL, 'john123', 'user', NULL, NULL, NULL, 'active', '2026-07-29 13:27:35', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 13:27:35', '2026-08-19 20:11:48', 0),
(3, 'Jane Buyer', 'buyer@geoauction.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL, NULL, NULL, 'active', '2026-07-29 13:27:35', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 13:27:35', '2026-08-19 20:11:41', 0),
(4, 'Muhammad Haneef', 'mhaneefalimemon@gmail.com', '+923062255764', '$2y$10$iA2YInkpuJOJ6y2KDyh7D.g4aJv.qlusP.VPDf.WpjDAPv8aHfQwq', 'user', NULL, NULL, 'male', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 23:54:38', '2026-08-17 09:35:14', 0),
(5, 'muskan', 'muskan@gmial.com', '03020202', '$2y$10$5OsAuC6DkOOGiD0FCu6cRuSZOevRkj3ko5XspGcBH39fkgTRa4xia', 'buyer', NULL, '', '', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 23:12:51', '2026-07-31 02:04:38', 0),
(6, 'Najmul Hassan', 'najmulhassan@geoauction.com', '03043059090', '$2y$10$o5OePVzIhFfYifTMXXPTyOZLu/pHsfOu2O/zIbH00CSVpQEwc7NPm', 'seller', NULL, NULL, 'other', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-31 02:20:35', '2026-08-17 09:35:12', 0),
(7, 'Dr Hira Shah', 'drhirashah@gmail.com', '+923062255000', '$2y$10$gcs/QKEp2EadlVSl8V1kJuyXHFItKNOnMa0L5BsElQ7gGyZeg.boW', 'buyer', NULL, NULL, 'female', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-03 01:01:27', '2026-08-17 09:35:10', 0),
(8, 'rose', 'rose@gmail.com', '0304', '$2a$12$eal.jESgCa.nRHLKTjaqdO.gKD.ddjfHMqrlcIrx4mxla83lRaJH6', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-13 08:05:25', '2026-08-17 09:35:09', 0),
(10, 'pawan', 'pawan@gmail.com', '+9230622888888888', '$2y$10$2/pwWdDt0.vUtzNIgTFA4ONNc1MxLVDmMeyu8fMEgWQzPqJFSxbHi', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-16 07:16:37', '2026-08-17 09:35:09', 0),
(11, 'almas', 'almas@gmial.com', '45678', '$2a$12$OtO6RW509Mz2aWDxgiReqObdjncewnFGKRJUi1d220UBOi.l6koPi', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-16 07:26:23', '2026-08-17 09:35:08', 10033),
(12, 'ali', 'ali@gmail.com', '45678', '$2y$10$MSIels4a/6fKvgtxtWjULOdKR3zhngvK7OgaSuUagWIDsnh.Y8uk6', 'admin', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-16 08:28:05', '2026-08-17 09:35:07', 0),
(13, 'aqil', 'aqil@gmail.com', '567888877766', '$2y$10$k5ouQFwv01Au6eA835t9keRHg1SZZZdHYc2JtiPQt9zZw7P/fOsOq', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-17 09:58:46', '2026-08-17 09:58:46', 0),
(14, 'aqil', 'awais@gmail.com', '08080', '$2y$10$8CEAqZcMmC.6e.vCoStWfO7WxLdV3yFlpEfBWcaWyW18.GIBsh6dW', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-17 10:01:28', '2026-08-17 10:01:28', 0),
(15, 'Faisal Raza', 'faisalraza@gmail.com', '03043059090', '$2y$10$6I4M6ArC7zBUnjVMPdeOj.ZztLH8iw2extMQaWR30nlYj9SCVVeoe', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 04:49:53', '2026-08-18 04:49:53', 0),
(16, 'Haneef', 'Haneef007@gmail.com', '+1234567890', '$2a$12$XwKOlLfqLTm0/qi35FyvBeHWJxRACSI6YVlfqi7GkSO8I.qcw.aFW', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 10:16:18', '2026-08-18 10:19:52', 0),
(17, 'aakash', 'aakash@gmail.com', '030202', '$2y$10$ojEjvH1IUBzUdcNgweiGou2JjqZA/o9yeg9UcwyS.1aTqbbeLXlUK', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-19 13:55:44', '2026-08-19 13:55:44', 0),
(18, 'Asad', 'asad@gmail.com', '022222', '$2y$10$KvTx4WMPpVQhmTLE4UQHyuY5hatQlDYu5XS8G389/p/dIkU3hmoDC', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-19 14:03:25', '2026-08-19 14:03:25', 0),
(19, 'auction', 'auction@gmail.com', '0909999', '$2y$10$.afknai3y8xSNFcRcbb/QO.Olb/KMfJsFcVn5e5mu9RgPHhL2JckS', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-19 14:06:34', '2026-08-19 14:06:34', 0),
(20, 'zareena', 'zareena@gmail.com', '090909', '$2y$10$/xirURZSiwTJxcRKjcqxouoUEuTSHRNfk34Ez1hAU09ZhZN8il8xO', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-19 19:58:32', '2026-08-19 19:58:32', 0),
(21, 'dua', 'dua@gmail.com', '99999999', '$2y$10$q2MsdY7hHGq6z2D0davzd.CRMU7423YGe/CL.Nqjhm.t9SAiI8Jla', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-19 20:16:59', '2026-08-19 20:16:59', 0),
(22, 'faisal', 'faisal@gmail.com', '33333', '$2y$10$2xf2IPtiL7p4DYqMNJCmUuYYSRYQzOseqU44xjbCaUOO/.FEFgNA2', 'user', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-19 20:49:31', '2026-08-19 20:49:31', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_bids` tinyint(1) DEFAULT 1,
  `email_outbid` tinyint(1) DEFAULT 1,
  `email_won` tinyint(1) DEFAULT 1,
  `email_newsletter` tinyint(1) DEFAULT 0,
  `sms_bids` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `email_bids`, `email_outbid`, `email_won`, `email_newsletter`, `sms_bids`) VALUES
(1, 1, 1, 1, 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_action` (`action`),
  ADD KEY `idx_activity_created` (`created_at`),
  ADD KEY `idx_activity_model` (`model_type`,`model_id`);

--
-- Indexes for table `bids`
--
ALTER TABLE `bids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bids_item` (`item_id`),
  ADD KEY `idx_bids_user` (`user_id`),
  ADD KEY `idx_bids_amount` (`bid_amount`),
  ADD KEY `idx_bids_status` (`bid_status`),
  ADD KEY `idx_bids_created` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_categories_slug` (`slug`),
  ADD KEY `idx_categories_status` (`status`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact_read` (`is_read`),
  ADD KEY `idx_contact_created` (`created_at`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `auction_code` (`auction_code`),
  ADD KEY `winner_id` (`winner_id`),
  ADD KEY `idx_items_status` (`status`),
  ADD KEY `idx_items_featured` (`featured`),
  ADD KEY `idx_items_end_time` (`end_time`),
  ADD KEY `idx_items_seller` (`seller_id`),
  ADD KEY `idx_items_category` (`category_id`),
  ADD KEY `idx_items_slug` (`slug`);
ALTER TABLE `items` ADD FULLTEXT KEY `idx_items_search` (`title`,`description`);

--
-- Indexes for table `item_images`
--
ALTER TABLE `item_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_images_item` (`item_id`);

--
-- Indexes for table `item_reports`
--
ALTER TABLE `item_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_messages_sender` (`sender_id`),
  ADD KEY `idx_messages_receiver` (`receiver_id`),
  ADD KEY `idx_messages_read` (`receiver_id`,`is_read`),
  ADD KEY `idx_messages_created` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_created` (`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `bid_id` (`bid_id`),
  ADD KEY `idx_payments_user` (`user_id`),
  ADD KEY `idx_payments_status` (`payment_status`),
  ADD KEY `idx_payments_invoice` (`invoice_no`),
  ADD KEY `idx_payments_created` (`created_at`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reported_user_id` (`reported_user_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_reports_status` (`status`),
  ADD KEY `idx_reports_type` (`report_type`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_reviews_user` (`user_id`),
  ADD KEY `idx_reviews_rating` (`rating`),
  ADD KEY `idx_reviews_approved` (`is_approved`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`),
  ADD KEY `idx_settings_key` (`key`),
  ADD KEY `idx_settings_group` (`group`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `type` (`type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_wishlist` (`user_id`,`item_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_wishlist_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `bids`
--
ALTER TABLE `bids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `item_images`
--
ALTER TABLE `item_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `item_reports`
--
ALTER TABLE `item_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bids`
--
ALTER TABLE `bids`
  ADD CONSTRAINT `bids_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bids_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_ibfk_3` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_images`
--
ALTER TABLE `item_images`
  ADD CONSTRAINT `item_images_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_reports`
--
ALTER TABLE `item_reports`
  ADD CONSTRAINT `item_reports_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_reports_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `messages_ibfk_4` FOREIGN KEY (`parent_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`bid_id`) REFERENCES `bids` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
