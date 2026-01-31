-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 19, 2026 at 12:19 PM
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
-- Database: `digital_marketing_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `email` varchar(254) NOT NULL,
  `role` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `full_name`, `email`, `role`, `created_at`) VALUES
(1, 'Rostom', 'Rostom@gmail.com', 'admin', '2026-01-11 08:51:03');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `attendance_id` bigint(20) UNSIGNED NOT NULL,
  `schedule_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('PRESENT','ABSENT') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`attendance_id`, `schedule_id`, `student_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 59, 1, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(2, 59, 2, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(3, 59, 3, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(4, 59, 4, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(5, 59, 5, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(6, 59, 6, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(7, 59, 7, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(8, 59, 8, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(9, 59, 9, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(10, 59, 10, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(11, 59, 11, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(12, 59, 12, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:18'),
(13, 59, 13, 'ABSENT', '2026-01-15 10:18:05', '2026-01-15 20:39:19'),
(14, 59, 14, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(15, 59, 15, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(16, 59, 16, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(17, 59, 17, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(18, 59, 18, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(19, 59, 19, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(20, 59, 20, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(21, 59, 21, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(22, 59, 22, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(23, 59, 23, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(24, 59, 24, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(25, 59, 25, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(26, 59, 26, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(27, 59, 27, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(28, 59, 28, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(29, 59, 29, 'ABSENT', '2026-01-15 10:18:06', '2026-01-15 20:39:19'),
(238, 101, 4, 'ABSENT', '2026-01-19 08:10:30', '2026-01-19 08:10:32'),
(240, 107, 36, 'ABSENT', '2026-01-19 10:22:58', '2026-01-19 10:23:04'),
(241, 107, 38, 'PRESENT', '2026-01-19 10:23:01', '2026-01-19 10:23:01'),
(243, 107, 40, 'PRESENT', '2026-01-19 10:23:09', '2026-01-19 10:23:09'),
(244, 107, 34, 'PRESENT', '2026-01-19 10:23:11', '2026-01-19 10:23:11'),
(245, 107, 42, 'PRESENT', '2026-01-19 10:23:15', '2026-01-19 10:23:15'),
(246, 107, 35, 'PRESENT', '2026-01-19 10:23:17', '2026-01-19 10:23:17'),
(247, 107, 31, 'PRESENT', '2026-01-19 10:23:23', '2026-01-19 10:23:23'),
(248, 107, 33, 'PRESENT', '2026-01-19 10:23:27', '2026-01-19 10:23:27'),
(249, 108, 38, 'PRESENT', '2026-01-19 10:23:44', '2026-01-19 10:23:44'),
(250, 108, 40, 'PRESENT', '2026-01-19 10:23:47', '2026-01-19 10:23:47'),
(251, 108, 34, 'PRESENT', '2026-01-19 10:23:49', '2026-01-19 10:23:49'),
(252, 108, 42, 'PRESENT', '2026-01-19 10:23:53', '2026-01-19 10:23:53'),
(253, 108, 35, 'PRESENT', '2026-01-19 10:23:54', '2026-01-19 10:23:54'),
(254, 108, 31, 'PRESENT', '2026-01-19 10:23:57', '2026-01-19 10:23:57'),
(255, 108, 30, 'PRESENT', '2026-01-19 10:24:00', '2026-01-19 10:24:00'),
(256, 108, 33, 'PRESENT', '2026-01-19 10:24:02', '2026-01-19 10:24:02'),
(257, 109, 36, 'PRESENT', '2026-01-19 10:24:10', '2026-01-19 10:24:16'),
(259, 109, 38, 'PRESENT', '2026-01-19 10:24:14', '2026-01-19 10:24:16'),
(260, 109, 39, 'ABSENT', '2026-01-19 10:24:14', '2026-01-19 10:24:22'),
(261, 109, 40, 'PRESENT', '2026-01-19 10:24:14', '2026-01-19 10:24:16'),
(262, 109, 34, 'PRESENT', '2026-01-19 10:24:14', '2026-01-19 10:24:16'),
(263, 109, 41, 'ABSENT', '2026-01-19 10:24:14', '2026-01-19 10:24:27'),
(264, 109, 42, 'PRESENT', '2026-01-19 10:24:14', '2026-01-19 10:24:16'),
(265, 109, 35, 'PRESENT', '2026-01-19 10:24:14', '2026-01-19 10:24:16'),
(266, 109, 31, 'PRESENT', '2026-01-19 10:24:14', '2026-01-19 10:24:17'),
(267, 109, 30, 'PRESENT', '2026-01-19 10:24:14', '2026-01-19 10:24:17'),
(268, 109, 33, 'PRESENT', '2026-01-19 10:24:14', '2026-01-19 10:24:17'),
(282, 110, 36, 'PRESENT', '2026-01-19 10:24:55', '2026-01-19 10:24:58'),
(283, 110, 38, 'PRESENT', '2026-01-19 10:24:55', '2026-01-19 10:24:58'),
(284, 110, 39, 'ABSENT', '2026-01-19 10:24:55', '2026-01-19 10:25:03'),
(285, 110, 40, 'PRESENT', '2026-01-19 10:24:55', '2026-01-19 10:24:58'),
(286, 110, 34, 'PRESENT', '2026-01-19 10:24:55', '2026-01-19 10:24:58'),
(287, 110, 41, 'ABSENT', '2026-01-19 10:24:55', '2026-01-19 10:25:08'),
(288, 110, 42, 'PRESENT', '2026-01-19 10:24:55', '2026-01-19 10:24:58'),
(289, 110, 35, 'PRESENT', '2026-01-19 10:24:55', '2026-01-19 10:24:58'),
(290, 110, 31, 'PRESENT', '2026-01-19 10:24:55', '2026-01-19 10:24:58'),
(291, 110, 30, 'PRESENT', '2026-01-19 10:24:56', '2026-01-19 10:24:58'),
(292, 110, 33, 'PRESENT', '2026-01-19 10:24:56', '2026-01-19 10:24:58'),
(306, 58, 36, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(307, 58, 38, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(308, 58, 39, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(309, 58, 40, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(310, 58, 34, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(311, 58, 41, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(312, 58, 42, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(313, 58, 35, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(314, 58, 31, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(315, 58, 30, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42'),
(316, 58, 33, 'PRESENT', '2026-01-19 10:35:42', '2026-01-19 10:35:42');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `audit_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `week_id` bigint(20) UNSIGNED DEFAULT NULL,
  `schedule_id` bigint(20) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cancelled_doctor_schedules`
--

CREATE TABLE `cancelled_doctor_schedules` (
  `cancelled_id` bigint(20) UNSIGNED NOT NULL,
  `week_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `day_of_week` enum('Sun','Mon','Tue','Wed','Thu') NOT NULL,
  `slot_number` tinyint(3) UNSIGNED DEFAULT NULL COMMENT '1-5, NULL when scope = day',
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `room_code` varchar(50) NOT NULL,
  `counts_towards_hours` tinyint(1) NOT NULL DEFAULT 1,
  `cancelled_scope` enum('day','slot') NOT NULL,
  `cancelled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_categories`
--

CREATE TABLE `evaluation_categories` (
  `category_key` varchar(40) NOT NULL,
  `label` varchar(120) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`category_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_configs`
--

CREATE TABLE `evaluation_configs` (
  `config_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `uq_eval_config` (`course_id`,`doctor_id`),
  KEY `idx_eval_config_course` (`course_id`),
  KEY `idx_eval_config_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_config_items`
--

CREATE TABLE `evaluation_config_items` (
  `item_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id` bigint(20) UNSIGNED NOT NULL,
  `category_key` varchar(40) NOT NULL,
  `item_label` varchar(120) NOT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 0.00,
  `sort_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_id`),
  KEY `idx_eval_item_config` (`config_id`),
  KEY `idx_eval_item_category` (`category_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_grades`
--

CREATE TABLE `evaluation_grades` (
  `grade_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_score` decimal(5,2) DEFAULT NULL,
  `final_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`grade_id`),
  UNIQUE KEY `uq_eval_grade` (`course_id`,`doctor_id`,`student_id`),
  KEY `idx_eval_grade_course` (`course_id`),
  KEY `idx_eval_grade_doctor` (`doctor_id`),
  KEY `idx_eval_grade_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_grade_items`
--

CREATE TABLE `evaluation_grade_items` (
  `grade_item_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `grade_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`grade_item_id`),
  UNIQUE KEY `uq_eval_grade_item` (`grade_id`,`item_id`),
  KEY `idx_eval_grade_item_grade` (`grade_id`),
  KEY `idx_eval_grade_item_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `course_name` varchar(200) NOT NULL,
  `program` varchar(120) NOT NULL DEFAULT 'Digital Marketing',
  `year_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `semester` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `course_type` enum('R','LAS') NOT NULL DEFAULT 'R',
  `subject_code` varchar(30) NOT NULL,
  `total_hours` decimal(5,2) NOT NULL DEFAULT 10.00,
  `course_Hours` decimal(5,2) NOT NULL DEFAULT 10.00,
  `default_room_code` varchar(50) DEFAULT NULL,
  `coefficient` decimal(6,2) NOT NULL DEFAULT 1.00,
  `doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_name`, `program`, `year_level`, `semester`, `course_type`, `subject_code`, `total_hours`, `course_Hours`, `default_room_code`, `coefficient`, `doctor_id`, `created_at`) VALUES
(1, 'INTEGRATION', 'Digital Marketing', 1, 1, 'R', '1.11', 28.50, 28.50, '127', 1.00, 1, '2025-12-29 19:21:59'),
(2, 'WEB DEVELOPMENT', 'Digital Marketing', 1, 1, 'R', '1.12', 25.50, 25.50, '127', 1.00, 1, '2025-12-29 19:23:29'),
(3, 'INFORMATION REPRESENTATION AND PROCESSING', 'Digital Marketing', 1, 1, 'R', '1.14', 24.00, 24.00, '127', 1.00, 1, '2025-12-29 19:24:54'),
(4, 'PRODUCE A WEBSITE', 'Digital Marketing', 1, 1, 'LAS', '105', 31.50, 31.50, '127', 1.00, 1, '2025-12-29 19:26:14'),
(5, 'STRATEGIC MANAGEMENT', 'Digital Marketing', 1, 1, 'R', '1.20', 0.00, 0.00, NULL, 1.00, 4, '2025-12-29 19:51:52'),
(11, 'French', 'Digital Marketing', 1, 1, 'R', '1.01', 18.00, 18.00, NULL, 1.00, 7, '2025-12-30 21:29:57'),
(12, 'Advanced French (LV2)', 'Digital Marketing', 1, 1, 'R', '1.02', 9.00, 9.00, NULL, 1.00, 7, '2025-12-30 21:31:27'),
(13, 'Ergonomics and Accessibility', 'Digital Marketing', 1, 1, 'R', '1.03', 18.00, 18.00, NULL, 1.00, 5, '2025-12-30 21:32:36'),
(14, 'Digital Culture', 'Digital Marketing', 1, 1, 'R', '1.04', 16.50, 16.50, NULL, 1.00, 9, '2025-12-30 21:33:49'),
(15, 'Marketing and Communication strategies', 'Digital Marketing', 1, 1, 'R', '1.05', 27.00, 27.00, NULL, 1.00, 4, '2025-12-30 21:35:23'),
(16, 'Expression, Communication and rhetoric', 'Digital Marketing', 1, 1, 'R', '1.06', 22.50, 22.50, NULL, 1.00, 9, '2025-12-30 21:36:54'),
(17, 'Multimedia writing and story telling', 'Digital Marketing', 1, 1, 'R', '1.07', 15.00, 15.00, NULL, 1.00, 6, '2025-12-30 21:37:47'),
(18, 'Graphic Production', 'Digital Marketing', 1, 1, 'R', '1.08', 22.50, 22.50, NULL, 1.00, 9, '2025-12-30 21:38:18'),
(19, 'Artistic Culture', 'Digital Marketing', 1, 1, 'R', '1.09', 16.50, 16.50, '127', 1.00, 6, '2025-12-30 21:39:05'),
(20, 'Audio and Video production', 'Digital Marketing', 1, 1, 'R', '1.10', 24.00, 24.00, NULL, 1.00, 9, '2025-12-30 21:39:45'),
(22, 'Hosting', 'Digital Marketing', 1, 1, 'R', '1.13', 18.00, 18.00, NULL, 1.00, 9, '2025-12-30 21:41:57'),
(23, 'Project Management', 'Digital Marketing', 1, 1, 'R', '1.15', 16.50, 16.50, NULL, 1.00, 3, '2025-12-30 21:43:24'),
(24, 'Economics management and digital law', 'Digital Marketing', 1, 1, 'R', '1.16', 15.00, 15.00, NULL, 1.00, 2, '2025-12-30 21:44:09'),
(25, 'Personal and Professional Project', 'Digital Marketing', 1, 1, 'R', '1.17', 19.50, 19.50, NULL, 1.00, 3, '2025-12-30 21:44:49'),
(26, 'Digital Communication Audit', 'Digital Marketing', 1, 1, 'LAS', '101', 13.50, 13.50, NULL, 1.00, 2, '2025-12-30 21:45:38'),
(27, 'Design a digital Communication recommand', 'Digital Marketing', 1, 1, 'LAS', '102', 10.50, 10.50, NULL, 1.00, 2, '2025-12-30 21:46:34'),
(28, 'Produce Elements for a visual Communication', 'Digital Marketing', 1, 1, 'LAS', '103', 15.00, 15.00, NULL, 1.00, 2, '2025-12-30 21:47:12'),
(29, 'Produce Audio And Video Content', 'Digital Marketing', 1, 1, 'LAS', '104', 13.50, 13.50, NULL, 1.00, 6, '2025-12-30 21:47:58'),
(30, 'Managing a Digital Communication Project', 'Digital Marketing', 1, 1, 'LAS', '106', 25.50, 25.50, NULL, 1.00, 2, '2025-12-30 21:53:37'),
(31, 'The Portfolio Approach', 'Digital Marketing', 1, 1, 'LAS', '107', 15.00, 15.00, NULL, 1.00, 2, '2025-12-30 21:54:30'),
(33, 'French', 'Digital Marketing', 1, 2, 'R', '2.01', 18.00, 18.00, NULL, 1.00, 7, '2025-12-31 10:12:14'),
(34, 'Advanced French (LV2)', 'Digital Marketing', 1, 2, 'R', '2.02', 9.00, 9.00, '127', 1.00, 7, '2025-12-31 11:44:46'),
(36, 'Ergonomics and Accessibility', 'Digital Marketing', 1, 2, 'R', '2.03', 16.50, 16.50, NULL, 1.00, 5, '2025-12-31 11:47:39'),
(37, 'Digital Culture', 'Digital Marketing', 1, 2, 'R', '2.04', 15.00, 15.00, NULL, 1.00, 9, '2025-12-31 11:50:55'),
(38, 'Marketing and Communication strategies', 'Digital Marketing', 1, 2, 'R', '2.05', 15.00, 15.00, '127', 1.00, 4, '2025-12-31 11:51:37'),
(39, 'Expression, Communication and rhetoric', 'Digital Marketing', 1, 2, 'R', '2.06', 16.50, 16.50, '127', 1.00, 9, '2025-12-31 11:52:30'),
(40, 'Multimedia writing and story telling', 'Digital Marketing', 1, 2, 'R', '2.07', 15.00, 15.00, '127', 1.00, 6, '2025-12-31 11:53:20'),
(41, 'Graphic Production', 'Digital Marketing', 1, 2, 'R', '2.08', 21.00, 21.00, '127', 1.00, 9, '2025-12-31 11:54:08'),
(42, 'Artistic Culture', 'Digital Marketing', 1, 2, 'R', '2.09', 18.00, 18.00, '127', 1.00, 1, '2025-12-31 11:55:02'),
(43, 'Audio and Video production', 'Digital Marketing', 1, 2, 'R', '2.10', 21.00, 21.00, '127', 1.00, 9, '2025-12-31 11:55:37'),
(44, 'Content Management', 'Digital Marketing', 1, 2, 'R', '2.11', 9.00, 9.00, '127', 1.00, 6, '2025-12-31 11:56:42'),
(45, 'INTEGRATION', 'Digital Marketing', 1, 2, 'R', '2.12', 24.00, 24.00, '127', 1.00, 1, '2025-12-31 11:57:24'),
(46, 'WEB DEVELOPMENT', 'Digital Marketing', 1, 2, 'R', '2.13', 21.00, 21.00, '127', 1.00, 1, '2025-12-31 11:58:17'),
(47, 'Information Systems', 'Digital Marketing', 1, 2, 'R', '2.14', 18.00, 18.00, '127', 1.00, 1, '2025-12-31 11:59:10'),
(48, 'Hosting', 'Digital Marketing', 1, 2, 'R', '2.15', 18.00, 18.00, NULL, 1.00, 9, '2025-12-31 11:59:44'),
(49, 'INFORMATION REPRESENTATION AND PROCESSING', 'Digital Marketing', 1, 2, 'R', '2.16', 19.50, 19.50, '127', 1.00, 1, '2025-12-31 12:00:33'),
(50, 'Project Management', 'Digital Marketing', 1, 2, 'R', '2.17', 15.00, 15.00, '127', 1.00, 6, '2025-12-31 12:01:18'),
(51, 'Economics management and digital law', 'Digital Marketing', 1, 2, 'R', '2.18', 15.00, 15.00, NULL, 1.00, 2, '2025-12-31 12:01:54'),
(52, 'Personal and Professional Project', 'Digital Marketing', 1, 2, 'R', '2.19', 19.50, 19.50, '127', 1.00, 3, '2025-12-31 12:02:25'),
(54, 'Explore Digital Uses', 'Digital Marketing', 1, 2, 'LAS', '2.01', 15.00, 15.00, NULL, 1.00, 5, '2025-12-31 12:30:47'),
(55, 'Design a product or services and its communication', 'Digital Marketing', 1, 2, 'LAS', '2.02', 55.50, 55.50, NULL, 1.00, 1, '2025-12-31 12:32:25'),
(56, 'Design a website with a Data Source', 'Digital Marketing', 1, 2, 'LAS', '.203', 21.00, 21.00, '127', 1.00, 1, '2025-12-31 12:33:58'),
(57, 'Build your online presence', 'Digital Marketing', 1, 2, 'LAS', '2.04', 15.00, 15.00, NULL, 1.00, 8, '2025-12-31 12:36:25'),
(58, 'Portfolio', 'Digital Marketing', 1, 2, 'LAS', '2.05', 30.00, 30.00, '127', 1.00, 2, '2025-12-31 12:37:05'),
(59, 'French', 'Digital Marketing', 2, 1, 'R', '3.01', 15.00, 15.00, '129', 1.00, 7, '2025-12-31 17:27:21'),
(60, 'Advanced French (LV2)', 'Digital Marketing', 2, 1, 'R', '3.02', 10.00, 10.00, '129', 1.00, 7, '2025-12-31 17:28:40'),
(61, 'Experience Design', 'Digital Marketing', 2, 1, 'R', '3.03', 16.50, 16.50, NULL, 1.00, 9, '2025-12-31 17:30:13'),
(62, 'Digital Culture', 'Digital Marketing', 2, 1, 'R', '3.04', 15.00, 15.00, '129', 1.00, 9, '2025-12-31 17:31:19'),
(63, 'Marketing and Communication strategies', 'Digital Marketing', 2, 1, 'R', '3.Dweb-D1.05', 15.00, 15.00, '129', 1.00, 4, '2025-12-31 17:32:36'),
(64, 'Referencing', 'Digital Marketing', 2, 1, 'R', '3.06', 15.00, 15.00, '129', 1.00, 1, '2025-12-31 17:33:37'),
(65, 'Expression, Communication and rhetoric', 'Digital Marketing', 2, 1, 'R', '3.07', 18.00, 18.00, NULL, 1.00, 9, '2025-12-31 17:34:31'),
(66, 'Multimedia writing and story telling', 'Digital Marketing', 2, 1, 'R', '3.08', 12.00, 12.00, NULL, 1.00, 6, '2025-12-31 17:35:27'),
(67, 'Creation and Interactive Design (UI)', 'Digital Marketing', 2, 1, 'R', '3.DWeb-D1.09', 15.00, 15.00, '129', 1.00, 9, '2025-12-31 17:37:15'),
(68, 'Artistic Culture', 'Digital Marketing', 2, 1, 'R', '3.10', 24.00, 24.00, '129', 1.00, 6, '2025-12-31 17:38:13'),
(69, 'Audio visual and motion design', 'Digital Marketing', 2, 1, 'R', '3.11', 19.50, 19.50, '129', 1.00, 2, '2025-12-31 17:39:29'),
(70, 'Front-End development and Integration', 'Digital Marketing', 2, 1, 'R', '3.12', 35.00, 35.00, '129', 1.00, 1, '2025-12-31 17:40:53'),
(71, 'Development Back', 'Digital Marketing', 2, 1, 'R', '3.DWeb-D1.13', 20.00, 20.00, '129', 1.00, 1, '2025-12-31 17:42:02'),
(72, 'Service Deployment', 'Digital Marketing', 2, 1, 'R', '3.14', 20.00, 20.00, NULL, 1.00, 9, '2025-12-31 17:42:40'),
(73, 'INFORMATION REPRESENTATION AND PROCESSING', 'Digital Marketing', 2, 1, 'R', '3.15', 20.00, 20.00, NULL, 1.00, 1, '2025-12-31 17:43:03'),
(74, 'Project Management', 'Digital Marketing', 2, 1, 'R', '3.16', 15.00, 15.00, '129', 1.00, 3, '2025-12-31 17:43:35'),
(75, 'Economics management and digital law', 'Digital Marketing', 2, 1, 'R', '3.17', 15.00, 15.00, '129', 1.00, 2, '2025-12-31 17:44:03'),
(76, 'Personal and Professional Project', 'Digital Marketing', 2, 1, 'R', '3.18', 10.00, 10.00, '129', 1.00, 3, '2025-12-31 17:44:28'),
(77, 'Developing User Paths Within an Information System', 'Digital Marketing', 2, 1, 'LAS', '3.DWeb-D1.01', 25.50, 25.50, NULL, 1.00, 1, '2025-12-31 17:45:28'),
(78, 'Communication', 'Digital Marketing', 2, 1, 'LAS', '3.02', 25.50, 25.50, '129', 1.00, 2, '2025-12-31 17:46:08'),
(79, 'Designing Data Visualization For the Web And an Interactive Application', 'Digital Marketing', 2, 1, 'LAS', '3.DWeb-D1.03', 25.50, 25.50, '129', 1.00, 1, '2025-12-31 17:47:25'),
(80, 'Portfolio', 'Digital Marketing', 2, 1, 'LAS', '3.04', 15.00, 15.00, NULL, 1.00, 2, '2025-12-31 17:47:54'),
(81, 'French', 'Digital Marketing', 2, 2, 'R', '4.DWeb-D1.01', 15.00, 15.00, '129', 1.00, 7, '2025-12-31 17:49:28'),
(82, 'Economics management and digital law', 'Digital Marketing', 2, 2, 'R', '4.02', 15.00, 15.00, '129', 1.00, 3, '2025-12-31 17:50:38'),
(83, 'Experience Design', 'Digital Marketing', 2, 2, 'R', '4.03', 15.00, 15.00, '129', 1.00, 9, '2025-12-31 17:52:12'),
(84, 'Expression, Communication', 'Digital Marketing', 2, 2, 'R', '4.04', 15.00, 15.00, NULL, 1.00, 8, '2025-12-31 17:52:58'),
(85, 'Creation and Interactive Design', 'Digital Marketing', 2, 2, 'R', '4.DWeb-D1.05', 15.00, 15.00, '129', 1.00, 9, '2025-12-31 17:53:45'),
(86, 'Front-End development', 'Digital Marketing', 2, 2, 'R', '4.DWeb-D1.06', 15.00, 15.00, '129', 1.00, 1, '2025-12-31 17:54:35'),
(87, 'Back-End Development', 'Digital Marketing', 2, 2, 'R', '4.DWeb-D1.07', 20.00, 20.00, '129', 1.00, 1, '2025-12-31 17:55:22'),
(88, 'Service Deployment', 'Digital Marketing', 2, 2, 'R', '4.DWeb-D1.08', 10.00, 10.00, '129', 1.00, 9, '2025-12-31 17:57:28'),
(89, 'WEB DEVELOPMENT', 'Digital Marketing', 2, 2, 'LAS', '4.DWeb-D1.01', 30.00, 30.00, '129', 1.00, 1, '2025-12-31 18:00:12'),
(90, 'Designing an Interactive Device', 'Digital Marketing', 2, 2, 'LAS', '4.DWeb-D1.02', 30.00, 30.00, NULL, 1.00, 2, '2025-12-31 18:00:58'),
(91, 'Internship', 'Digital Marketing', 2, 2, 'LAS', 'STAGE.DWeb-D1', 30.00, 30.00, NULL, 1.00, 2, '2025-12-31 18:01:55'),
(92, 'Portfolio', 'Digital Marketing', 2, 2, 'LAS', 'PORTIFOLIO', 30.00, 30.00, NULL, 1.00, 2, '2025-12-31 18:02:22'),
(93, 'French', 'Digital Marketing', 3, 1, 'R', '5.01', 20.00, 20.00, NULL, 1.00, 7, '2026-01-01 16:10:22'),
(94, 'Management and Quality Assurance', 'Digital Marketing', 3, 1, 'R', '5.02', 30.00, 30.00, NULL, 1.00, 2, '2026-01-01 16:11:30'),
(95, 'Entrepreneurship', 'Digital Marketing', 3, 1, 'R', '5.03', 15.00, 15.00, NULL, 1.00, 2, '2026-01-01 16:12:53'),
(96, 'Personal and Professional Project', 'Digital Marketing', 3, 1, 'R', '5.04', 21.00, 21.00, NULL, 1.00, 2, '2026-01-01 16:13:34'),
(97, 'Advanced Front-End Development', 'Digital Marketing', 3, 1, 'R', '5.DWeb-D1.05', 45.50, 45.50, NULL, 1.00, 1, '2026-01-01 16:15:09'),
(98, 'Advanced Back-End Development', 'Digital Marketing', 3, 1, 'R', '5.DWeb-D1.06', 50.00, 50.00, NULL, 1.00, 1, '2026-01-01 16:17:13'),
(99, 'Interactive Devices', 'Digital Marketing', 3, 1, 'R', '5.DWeb-D1.07', 58.50, 58.50, NULL, 1.00, 2, '2026-01-01 16:18:08'),
(100, 'Hosting And Cyber Security', 'Digital Marketing', 3, 1, 'R', '5.DWeb-D1.08', 50.00, 50.00, NULL, 1.00, 9, '2026-01-01 16:19:40'),
(101, 'Developing of the Web or designing an interactive Device', 'Digital Marketing', 3, 1, 'LAS', '5.DWeb-D1.01', 170.00, 170.00, NULL, 1.00, 2, '2026-01-01 16:21:29'),
(102, 'Portfolio', 'Digital Marketing', 3, 1, 'LAS', 'PORTIFOLIO', 30.00, 30.00, NULL, 1.00, 2, '2026-01-01 16:21:57'),
(103, 'Entrepreneurship', 'Digital Marketing', 3, 2, 'R', '6.01', 15.00, 15.00, NULL, 1.00, 2, '2026-01-01 16:24:08'),
(104, 'Web Development and Interactive Devices', 'Digital Marketing', 3, 2, 'R', '6.DWeb-D1.02', 37.00, 37.00, NULL, 1.00, 2, '2026-01-01 16:25:21'),
(105, 'Internship', 'Digital Marketing', 3, 2, 'LAS', '6.01', 50.00, 50.00, NULL, 1.00, 2, '2026-01-01 16:26:27'),
(106, 'Portfolio', 'Digital Marketing', 3, 2, 'LAS', '6.02', 7.00, 7.00, NULL, 1.00, 2, '2026-01-01 16:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `course_doctors`
--

CREATE TABLE `course_doctors` (
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_doctors`
--

INSERT INTO `course_doctors` (`course_id`, `doctor_id`, `created_at`) VALUES
(1, 1, '2025-12-30 22:14:56'),
(2, 1, '2025-12-30 22:17:05'),
(3, 1, '2025-12-30 22:14:47'),
(4, 1, '2025-12-30 22:15:55'),
(5, 4, '2025-12-30 22:16:33'),
(11, 7, '2025-12-30 22:14:27'),
(12, 7, '2025-12-31 10:17:49'),
(13, 5, '2025-12-31 08:55:33'),
(14, 9, '2026-01-01 15:00:41'),
(15, 4, '2026-01-01 15:02:45'),
(16, 9, '2026-01-01 15:04:20'),
(17, 6, '2025-12-30 22:15:35'),
(18, 9, '2026-01-01 15:06:30'),
(19, 6, '2026-01-01 15:15:49'),
(20, 9, '2026-01-01 15:16:35'),
(22, 9, '2026-01-01 15:19:07'),
(23, 3, '2025-12-30 22:16:25'),
(24, 2, '2025-12-30 22:14:03'),
(25, 3, '2026-01-01 15:39:31'),
(26, 2, '2025-12-30 22:13:41'),
(27, 2, '2025-12-30 22:13:07'),
(28, 2, '2025-12-30 22:16:17'),
(29, 6, '2025-12-30 22:16:07'),
(30, 2, '2025-12-30 22:15:13'),
(31, 2, '2025-12-30 22:16:52'),
(33, 7, '2025-12-31 10:12:14'),
(34, 7, '2025-12-31 12:40:10'),
(36, 5, '2025-12-31 11:47:39'),
(37, 9, '2026-01-01 15:00:58'),
(38, 4, '2025-12-31 11:51:37'),
(39, 9, '2026-01-01 15:04:35'),
(40, 6, '2025-12-31 11:53:20'),
(41, 9, '2026-01-01 15:06:48'),
(42, 1, '2026-01-18 14:23:19'),
(43, 9, '2026-01-01 15:16:48'),
(44, 6, '2025-12-31 11:56:42'),
(45, 1, '2025-12-31 11:57:24'),
(46, 1, '2025-12-31 11:58:17'),
(47, 1, '2025-12-31 11:59:10'),
(48, 9, '2026-01-01 15:19:17'),
(49, 1, '2025-12-31 12:00:33'),
(50, 6, '2025-12-31 12:01:18'),
(51, 2, '2025-12-31 12:01:54'),
(52, 3, '2025-12-31 12:02:25'),
(54, 5, '2025-12-31 12:30:47'),
(55, 1, '2026-01-01 15:45:39'),
(55, 3, '2026-01-01 15:45:39'),
(55, 4, '2026-01-01 15:45:39'),
(55, 5, '2026-01-01 15:45:39'),
(55, 6, '2026-01-01 15:45:39'),
(55, 11, '2026-01-01 15:45:39'),
(56, 1, '2025-12-31 12:33:58'),
(57, 8, '2025-12-31 12:36:25'),
(58, 2, '2025-12-31 12:37:05'),
(59, 7, '2025-12-31 17:27:21'),
(60, 7, '2025-12-31 17:28:40'),
(61, 9, '2026-01-01 15:51:10'),
(62, 9, '2026-01-01 15:01:12'),
(63, 4, '2026-01-01 15:03:05'),
(64, 1, '2025-12-31 17:33:37'),
(65, 9, '2026-01-01 15:04:56'),
(66, 6, '2025-12-31 17:35:27'),
(67, 9, '2026-01-01 15:52:41'),
(68, 6, '2026-01-01 15:10:37'),
(69, 2, '2025-12-31 17:39:29'),
(70, 1, '2025-12-31 17:40:53'),
(71, 1, '2025-12-31 17:42:02'),
(72, 9, '2026-01-01 15:53:59'),
(73, 1, '2025-12-31 17:43:03'),
(74, 3, '2025-12-31 17:43:35'),
(75, 2, '2025-12-31 17:44:03'),
(76, 3, '2026-01-01 15:40:15'),
(77, 1, '2025-12-31 17:45:28'),
(78, 2, '2025-12-31 17:46:08'),
(79, 1, '2025-12-31 17:47:25'),
(80, 2, '2025-12-31 17:47:54'),
(81, 7, '2025-12-31 17:49:28'),
(82, 3, '2025-12-31 18:10:04'),
(83, 9, '2026-01-01 15:50:59'),
(84, 8, '2025-12-31 17:52:58'),
(85, 9, '2026-01-01 15:52:33'),
(86, 1, '2025-12-31 17:54:35'),
(87, 1, '2025-12-31 17:55:22'),
(88, 9, '2026-01-01 15:54:13'),
(89, 1, '2025-12-31 18:00:12'),
(90, 2, '2025-12-31 18:00:58'),
(91, 2, '2025-12-31 18:01:55'),
(92, 2, '2025-12-31 18:02:22'),
(93, 7, '2026-01-01 16:10:22'),
(94, 2, '2026-01-01 16:11:30'),
(95, 2, '2026-01-01 16:12:53'),
(96, 2, '2026-01-01 16:13:34'),
(97, 1, '2026-01-01 16:15:09'),
(98, 1, '2026-01-01 16:17:13'),
(99, 2, '2026-01-01 16:18:08'),
(100, 9, '2026-01-01 16:19:40'),
(101, 2, '2026-01-01 16:21:29'),
(102, 2, '2026-01-01 16:21:57'),
(103, 2, '2026-01-01 16:24:08'),
(104, 2, '2026-01-01 16:25:21'),
(105, 2, '2026-01-01 16:26:27'),
(106, 2, '2026-01-01 16:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `course_doctor_hours`
--

CREATE TABLE `course_doctor_hours` (
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `allocated_hours` decimal(6,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_doctor_hours`
--

INSERT INTO `course_doctor_hours` (`course_id`, `doctor_id`, `allocated_hours`, `updated_at`) VALUES
(55, 1, 10.00, '2026-01-01 15:47:11'),
(55, 3, 10.00, '2026-01-01 15:47:11'),
(55, 4, 11.50, '2026-01-01 15:47:11'),
(55, 5, 11.50, '2026-01-01 15:47:11'),
(55, 6, 10.50, '2026-01-01 15:47:11'),
(55, 11, 2.00, '2026-01-01 15:47:11');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `email` varchar(254) NOT NULL,
  `phone_number` varchar(32) DEFAULT NULL,
  `color_code` char(7) NOT NULL DEFAULT '#0055A4' COMMENT 'Hex color like #RRGGBB',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`doctor_id`, `full_name`, `email`, `phone_number`, `color_code`, `created_at`) VALUES
(1, 'Sherif Rostom', 'sherrost@yahoo.com', '+201017776756', '#21A706', '2025-12-29 18:03:37'),
(2, 'Prof. Asmaa El Sherif', 'asmaa_alsharif@yahoo.com', '+201001160631', '#F32537', '2025-12-29 18:05:59'),
(3, 'Dr. Farid', 'haddadism@hotmail.com', '+201022593899', '#79C1D2', '2025-12-29 18:07:03'),
(4, 'Dr. Hanan', 'hananghaly@gmail.com', '+201277116614', '#D772E4', '2025-12-29 18:07:38'),
(5, 'Dr. Norhan El Gebaly', 'nmelgebaly@gmail.com', '+201001160631', '#E2C765', '2025-12-29 18:08:44'),
(6, 'Dr. Asmaa Abd El Magid', 'asmaamagiud@gmail.com', NULL, '#7365E2', '2025-12-29 18:09:44'),
(7, 'Dr.Manal El Shafii', 'mchafei.manalchafei@gmail.com', '+201114666522', '#58748D', '2025-12-30 21:29:14'),
(8, 'Dr.Reem', 'ream@gmail.com', '+201033515666', '#A3009E', '2025-12-31 12:35:28'),
(9, 'Missionnaire', 'Missionnaire@gmail.com', NULL, '#A31000', '2026-01-01 14:56:51'),
(10, 'Aya Younes', 'Aya@gmail.com', NULL, '#00A388', '2026-01-01 15:11:46'),
(11, 'Dr. Khaled', 'Khaled@gmail.com', NULL, '#A34F00', '2026-01-01 15:44:35');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `schedule_id` bigint(20) UNSIGNED NOT NULL,
  `week_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `day_of_week` enum('Sun','Mon','Tue','Wed','Thu') NOT NULL,
  `slot_number` tinyint(3) UNSIGNED NOT NULL COMMENT '1-5 (each slot = 1.5 hours)',
  `room_code` varchar(50) NOT NULL COMMENT 'Required free-text room (e.g., 101 / Lab A / Building 2 - 203)',
  `counts_towards_hours` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Only count/subtract hours when true',
  `extra_minutes` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0,15,30,45 extra minutes deducted from course hours',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`schedule_id`, `week_id`, `doctor_id`, `course_id`, `day_of_week`, `slot_number`, `room_code`, `counts_towards_hours`, `extra_minutes`, `created_at`) VALUES
(11, 1, 7, 33, 'Sun', 1, '127', 1, 0, '2025-12-31 12:53:55'),
(12, 1, 7, 33, 'Sun', 2, '127', 1, 0, '2025-12-31 12:54:08'),
(13, 1, 3, 52, 'Sun', 3, '127', 1, 0, '2025-12-31 12:54:48'),
(14, 1, 8, 57, 'Sun', 4, '127', 1, 0, '2025-12-31 12:55:10'),
(15, 1, 8, 57, 'Sun', 5, '127', 1, 0, '2025-12-31 12:55:20'),
(17, 1, 1, 45, 'Mon', 2, '127', 1, 0, '2025-12-31 12:56:26'),
(18, 1, 1, 49, 'Mon', 3, '127', 1, 0, '2025-12-31 12:56:39'),
(19, 1, 1, 49, 'Mon', 4, '127', 1, 0, '2025-12-31 12:56:54'),
(20, 1, 1, 49, 'Mon', 5, '127', 1, 0, '2025-12-31 12:57:00'),
(21, 1, 6, 50, 'Tue', 1, '127', 1, 0, '2025-12-31 12:57:39'),
(22, 1, 6, 50, 'Tue', 2, '127', 1, 0, '2025-12-31 12:57:45'),
(23, 1, 6, 44, 'Tue', 3, '127', 1, 0, '2025-12-31 12:58:11'),
(24, 1, 1, 45, 'Tue', 5, '127', 1, 0, '2025-12-31 12:58:41'),
(25, 1, 6, 40, 'Thu', 1, '127', 1, 0, '2025-12-31 12:59:24'),
(26, 1, 6, 40, 'Thu', 2, '127', 1, 0, '2025-12-31 12:59:34'),
(27, 1, 6, 44, 'Thu', 3, '127', 1, 0, '2025-12-31 12:59:41'),
(28, 1, 6, 44, 'Thu', 4, '127', 1, 0, '2025-12-31 12:59:49'),
(29, 1, 6, 44, 'Thu', 5, '127', 1, 0, '2025-12-31 12:59:57'),
(30, 1, 7, 81, 'Sun', 3, '129', 1, 0, '2025-12-31 18:06:25'),
(31, 1, 7, 81, 'Sun', 4, '129', 1, 0, '2025-12-31 18:06:40'),
(32, 1, 3, 82, 'Sun', 5, '129', 1, 0, '2025-12-31 18:11:07'),
(33, 1, 1, 87, 'Mon', 1, '129', 1, 0, '2025-12-31 18:12:20'),
(34, 1, 7, 81, 'Mon', 3, '129', 1, 0, '2025-12-31 18:13:04'),
(35, 1, 7, 81, 'Mon', 4, '129', 1, 0, '2025-12-31 18:13:11'),
(36, 1, 1, 87, 'Tue', 1, '129', 1, 0, '2025-12-31 18:13:45'),
(37, 1, 1, 87, 'Tue', 2, '129', 1, 0, '2025-12-31 18:13:52'),
(38, 1, 1, 86, 'Tue', 3, '129', 1, 0, '2025-12-31 18:13:59'),
(39, 1, 1, 86, 'Tue', 4, '129', 1, 0, '2025-12-31 18:14:06'),
(40, 1, 1, 87, 'Thu', 1, '129', 1, 0, '2025-12-31 18:14:29'),
(41, 1, 1, 87, 'Thu', 2, '129', 1, 0, '2025-12-31 18:14:35'),
(42, 1, 1, 86, 'Thu', 3, '129', 1, 0, '2025-12-31 18:14:52'),
(43, 1, 1, 86, 'Thu', 4, '129', 1, 0, '2025-12-31 18:14:59'),
(44, 2, 7, 81, 'Sun', 3, '129', 1, 0, '2026-01-11 08:55:48'),
(45, 2, 7, 81, 'Sun', 4, '129', 1, 0, '2026-01-11 08:55:50'),
(46, 2, 7, 81, 'Mon', 4, '129', 1, 0, '2026-01-11 08:55:54'),
(47, 2, 7, 81, 'Mon', 5, '129', 1, 0, '2026-01-11 08:55:57'),
(48, 2, 7, 81, 'Wed', 4, '129', 1, 0, '2026-01-11 08:56:02'),
(49, 2, 7, 81, 'Wed', 5, '129', 1, 0, '2026-01-11 08:56:04'),
(50, 2, 3, 82, 'Sun', 5, '129', 1, 0, '2026-01-11 08:56:14'),
(51, 2, 3, 82, 'Wed', 1, '129', 1, 0, '2026-01-11 08:56:20'),
(52, 2, 3, 82, 'Wed', 3, '129', 1, 0, '2026-01-11 08:56:23'),
(54, 2, 1, 87, 'Tue', 1, '129', 1, 0, '2026-01-11 08:56:38'),
(55, 2, 1, 87, 'Tue', 2, '129', 1, 0, '2026-01-11 08:56:41'),
(56, 2, 1, 87, 'Tue', 3, '129', 1, 0, '2026-01-11 08:56:44'),
(57, 2, 1, 87, 'Mon', 3, '129', 1, 0, '2026-01-11 08:56:46'),
(58, 2, 1, 87, 'Mon', 1, '129', 1, 0, '2026-01-11 08:56:49'),
(59, 2, 7, 33, 'Sun', 1, '127', 1, 0, '2026-01-11 08:59:44'),
(60, 2, 7, 33, 'Sun', 2, '127', 1, 0, '2026-01-11 08:59:49'),
(61, 2, 3, 52, 'Sun', 3, '127', 1, 0, '2026-01-11 09:00:08'),
(62, 2, 8, 57, 'Sun', 4, '127', 1, 0, '2026-01-11 09:00:26'),
(63, 2, 8, 57, 'Sun', 5, '127', 1, 0, '2026-01-11 09:00:30'),
(64, 2, 5, 54, 'Mon', 3, '127', 1, 0, '2026-01-11 09:00:48'),
(65, 2, 1, 45, 'Mon', 2, '127', 1, 0, '2026-01-11 09:01:00'),
(66, 2, 1, 49, 'Mon', 4, '127', 1, 0, '2026-01-11 09:01:04'),
(67, 2, 1, 49, 'Mon', 5, '127', 1, 0, '2026-01-11 09:01:07'),
(73, 2, 6, 50, 'Tue', 1, '127', 1, 0, '2026-01-11 09:59:02'),
(74, 2, 6, 50, 'Tue', 2, '127', 1, 0, '2026-01-11 09:59:20'),
(75, 2, 6, 44, 'Tue', 3, '127', 1, 0, '2026-01-11 09:59:27'),
(76, 2, 6, 40, 'Thu', 1, '127', 1, 0, '2026-01-11 10:00:21'),
(77, 2, 6, 40, 'Thu', 2, '127', 1, 0, '2026-01-11 10:00:24'),
(78, 2, 6, 44, 'Thu', 3, '127', 1, 0, '2026-01-11 10:00:30'),
(79, 2, 6, 40, 'Thu', 4, '127', 1, 0, '2026-01-11 10:00:55'),
(80, 2, 6, 50, 'Thu', 5, '127', 1, 0, '2026-01-11 10:01:00'),
(81, 3, 7, 33, 'Sun', 1, '127', 1, 0, '2026-01-16 17:27:48'),
(82, 3, 7, 33, 'Sun', 2, '127', 1, 0, '2026-01-16 17:28:04'),
(83, 3, 8, 57, 'Sun', 4, '127', 1, 0, '2026-01-16 17:28:42'),
(84, 3, 8, 57, 'Sun', 5, '127', 1, 0, '2026-01-16 17:29:13'),
(85, 3, 7, 33, 'Mon', 1, '127', 1, 0, '2026-01-16 17:30:01'),
(86, 3, 7, 33, 'Mon', 2, '127', 1, 0, '2026-01-16 17:30:09'),
(87, 3, 5, 54, 'Mon', 3, '127', 1, 0, '2026-01-16 17:30:44'),
(88, 3, 1, 49, 'Mon', 5, '127', 1, 0, '2026-01-16 17:31:09'),
(90, 3, 5, 54, 'Tue', 3, '127', 1, 0, '2026-01-16 17:32:05'),
(91, 3, 1, 45, 'Wed', 1, '127', 1, 0, '2026-01-16 17:32:33'),
(92, 3, 1, 45, 'Wed', 3, '127', 1, 0, '2026-01-16 17:32:47'),
(93, 3, 2, 51, 'Wed', 2, '127', 1, 0, '2026-01-16 17:33:13'),
(94, 3, 8, 57, 'Wed', 4, '127', 1, 0, '2026-01-16 17:33:42'),
(95, 3, 8, 57, 'Wed', 5, '127', 1, 0, '2026-01-16 17:33:51'),
(96, 3, 6, 50, 'Thu', 1, '127', 1, 0, '2026-01-16 17:34:17'),
(97, 3, 6, 50, 'Thu', 2, '127', 1, 0, '2026-01-16 17:34:27'),
(98, 3, 6, 50, 'Thu', 3, '127', 1, 0, '2026-01-16 17:34:34'),
(99, 3, 6, 50, 'Thu', 4, '127', 1, 0, '2026-01-16 17:34:49'),
(100, 3, 6, 50, 'Thu', 5, '127', 1, 0, '2026-01-16 17:34:58'),
(101, 3, 6, 40, 'Tue', 1, '127', 1, 0, '2026-01-16 17:44:03'),
(102, 3, 6, 40, 'Tue', 2, '127', 1, 0, '2026-01-16 17:44:09'),
(103, 3, 6, 40, 'Tue', 4, '127', 1, 0, '2026-01-16 17:45:17'),
(104, 3, 6, 40, 'Tue', 5, '127', 1, 0, '2026-01-16 17:45:23'),
(107, 3, 1, 86, 'Mon', 1, '129', 1, 0, '2026-01-16 17:53:32'),
(108, 3, 1, 86, 'Mon', 2, '129', 1, 0, '2026-01-16 17:53:59'),
(109, 3, 1, 87, 'Mon', 3, '129', 1, 0, '2026-01-16 17:54:11'),
(110, 3, 1, 86, 'Mon', 4, '129', 1, 0, '2026-01-16 17:54:22'),
(111, 3, 1, 87, 'Tue', 1, '129', 1, 0, '2026-01-16 17:54:41'),
(114, 3, 3, 82, 'Wed', 1, '129', 1, 0, '2026-01-16 17:55:43'),
(115, 3, 3, 82, 'Wed', 3, '129', 1, 0, '2026-01-16 17:55:49'),
(116, 3, 1, 87, 'Wed', 2, '129', 1, 0, '2026-01-16 17:56:08'),
(119, 3, 1, 86, 'Sun', 1, '129', 1, 0, '2026-01-16 17:58:57'),
(120, 3, 1, 86, 'Sun', 2, '129', 1, 0, '2026-01-16 17:59:02'),
(121, 3, 1, 89, 'Wed', 4, '129', 1, 0, '2026-01-16 17:59:30'),
(122, 3, 1, 89, 'Wed', 5, '129', 1, 0, '2026-01-16 17:59:37'),
(123, 3, 1, 86, 'Tue', 2, '129', 1, 0, '2026-01-16 18:05:14'),
(124, 3, 1, 89, 'Tue', 3, '129', 1, 0, '2026-01-16 18:05:23'),
(125, 3, 3, 82, 'Tue', 4, '129', 1, 0, '2026-01-18 10:33:49'),
(126, 3, 3, 82, 'Tue', 5, '129', 1, 0, '2026-01-18 10:33:59');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_slot_cancellations`
--

CREATE TABLE `doctor_slot_cancellations` (
  `slot_cancellation_id` bigint(20) UNSIGNED NOT NULL,
  `week_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `day_of_week` enum('Sun','Mon','Tue','Wed','Thu') NOT NULL,
  `slot_number` tinyint(3) UNSIGNED NOT NULL COMMENT '1-5',
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_unavailability`
--

CREATE TABLE `doctor_unavailability` (
  `unavailability_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL COMMENT 'e.g., Sick Leave, Conference, Personal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_week_cancellations`
--

CREATE TABLE `doctor_week_cancellations` (
  `cancellation_id` bigint(20) UNSIGNED NOT NULL,
  `week_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `day_of_week` enum('Sun','Mon','Tue','Wed','Thu') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_year_colors`
--

CREATE TABLE `doctor_year_colors` (
  `doctor_id` bigint(20) UNSIGNED NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL COMMENT '1-3',
  `color_code` char(7) NOT NULL DEFAULT '#0055A4',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_year_colors`
--

INSERT INTO `doctor_year_colors` (`doctor_id`, `year_level`, `color_code`, `updated_at`) VALUES
(1, 1, '#21A706', '2026-01-19 07:28:24'),
(1, 2, '#A76406', '2026-01-19 07:29:01'),
(1, 3, '#7C7BCC', '2026-01-19 07:29:01'),
(2, 1, '#F32537', '2026-01-15 12:17:49'),
(2, 2, '#25F4F0', '2026-01-15 12:17:49'),
(2, 3, '#6D25F4', '2026-01-15 12:17:49'),
(3, 1, '#79C1D2', '2026-01-15 11:09:04'),
(3, 2, '#BF79D2', '2026-01-15 11:09:04'),
(3, 3, '#D2CF79', '2026-01-15 11:09:04'),
(4, 1, '#D772E4', '2026-01-15 11:06:45'),
(4, 2, '#72CDE4', '2026-01-15 11:06:45'),
(4, 3, '#E4BA72', '2026-01-15 11:06:45'),
(5, 1, '#E2C765', '2026-01-15 11:05:07'),
(5, 2, '#F56505', '2026-01-15 12:15:32'),
(5, 3, '#E9A9EA', '2026-01-15 12:15:58'),
(7, 1, '#58748D', '2026-01-15 11:08:00'),
(7, 2, '#8D588B', '2026-01-15 11:08:00'),
(7, 3, '#8C8D58', '2026-01-15 11:08:00');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `week_id` bigint(20) UNSIGNED DEFAULT NULL,
  `doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `room_code` varchar(50) DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portal_users`
--

CREATE TABLE `portal_users` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','management','teacher','student') NOT NULL,
  `doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `student_id` bigint(20) UNSIGNED DEFAULT NULL,
  `allowed_pages_json` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `portal_users`
--

INSERT INTO `portal_users` (`user_id`, `username`, `password_hash`, `role`, `doctor_id`, `student_id`, `allowed_pages_json`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$/aXYqYpoUQvFMcJTSlxwT.00LHR466Ym8ETLD.PDwtFkUup2jrNba', 'admin', NULL, NULL, NULL, 1, '2026-01-11 08:51:03', '2026-01-11 08:51:03'),
(2, 'Assma El Sharif', '$2y$10$oKlXdaAH.lqJniwTQMTJNO0ZkcCa2l9.KWRh641.xZBo3k4bYVzIi', 'teacher', 2, NULL, '[\"index.php\",\"doctor.php\",\"attendance.php\"]', 1, '2026-01-15 10:57:59', '2026-01-15 10:58:25'),
(3, 'Manal El Chafai', '$2y$10$jdILVgCYvRH3jMfj0zAg8.idYpT4g/c9RJuYXatPg87kxmFBI/WRS', 'teacher', 7, NULL, '[\"doctor.php\",\"attendance.php\"]', 1, '2026-01-15 11:01:28', '2026-01-15 11:01:28'),
(4, 'Nourhan', '$2y$10$KwCfTWEDEVY4y/xO2UJFduvi/FICHiGrRye2DnQAab1gOG79f4Qqa', 'teacher', 5, NULL, '[\"doctor.php\",\"attendance.php\",\"evaluation.php\",\"profile.php\"]', 1, '2026-01-26 19:19:01', '2026-01-26 19:19:01');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `floors` (
  `floor_id` bigint(20) UNSIGNED NOT NULL,
  `floor_name` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `floor_id` bigint(20) UNSIGNED NOT NULL,
  `room_code` varchar(50) NOT NULL,
  `label` varchar(80) NOT NULL,
  `room_type` enum('room','lab') NOT NULL DEFAULT 'room',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `email` varchar(254) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `program` varchar(100) NOT NULL DEFAULT 'Digital Marketing',
  `year_level` tinyint(3) UNSIGNED NOT NULL,
  `semester` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `full_name`, `email`, `student_code`, `program`, `year_level`, `semester`, `created_at`) VALUES
(1, 'Abd El Rahman Ahmed Mohamed Taha', 'abdo@gmail.com', 'MG25314', 'Digital Marketing', 1, 0, '2026-01-02 14:32:12'),
(2, 'Al Qasem Khaled Qasem', 'qas@gmail.com', 'MG25557', 'Digital Marketing', 1, 0, '2026-01-02 14:42:20'),
(3, 'Alia Mahmoud Mohamed El Meligy', 'ali@gmail.com', 'MG25141', 'Digital Marketing', 1, 0, '2026-01-02 14:44:02'),
(4, 'Aly Hesham Aly Ramadan', 'Aly@gmail.com', 'MG24764', 'Digital Marketing', 1, 0, '2026-01-02 14:45:01'),
(5, 'Bilal Ahmed El Sayed State', 'bil@gmail.com', 'MG25192', 'Digital Marketing', 1, 0, '2026-01-02 14:45:58'),
(6, 'Carol Wissam Salah Sadik', 'car@gmail.com', 'MG25031', 'Digital Marketing', 1, 0, '2026-01-02 14:46:51'),
(7, 'Faris Shireen Mohamed Hassanin', 'far@gmail.com', 'MG24448', 'Digital Marketing', 1, 0, '2026-01-02 14:48:01'),
(8, 'Hamza Mohamed Reda Abd El Fattah Ahmed Abd El Nasser', 'ham@gmail.com', 'MG25596', 'Digital Marketing', 1, 0, '2026-01-02 14:49:33'),
(9, 'Jamila Ossama Kamal Abd El Latif El Mahdy', 'jam@gmail.com', 'MG25068', 'Digital Marketing', 1, 0, '2026-01-02 14:51:00'),
(10, 'Jana Eslam Mohamed', 'Jana@gmail.com', 'MG25446', 'Digital Marketing', 1, 0, '2026-01-02 14:51:56'),
(11, 'Jean Jeorge Eriane Mansour', 'jean@gmail.com', 'MG25149', 'Digital Marketing', 1, 0, '2026-01-02 15:00:26'),
(12, 'Jessy Osama Raafat Shoukry', 'jes@gmail.com', 'MG25518', 'Digital Marketing', 1, 0, '2026-01-02 15:01:21'),
(13, 'Laila Wael Mohamed Hussien', 'Lai@gmail.com', 'MG24398', 'Digital Marketing', 1, 0, '2026-01-02 15:02:17'),
(14, 'Leen Ibrahim Mohamed Hussien Mahmoud', 'leen@gmail.com', 'MG25539', 'Digital Marketing', 1, 0, '2026-01-02 15:03:27'),
(15, 'Leila Ahmed Maher Mohamed El Guindy', 'gu@gmail.com', 'AL24398', 'Digital Marketing', 1, 0, '2026-01-02 15:04:58'),
(16, 'Malika Hany Ahmed SaadAllah Sorour', 'malika@gmail.com', 'AL25180', 'Digital Marketing', 1, 0, '2026-01-02 15:06:26'),
(17, 'Mohamed Ahmed AbdAllah Eid', 'mo@gmail.com', 'MG25559', 'Digital Marketing', 1, 0, '2026-01-02 15:07:29'),
(18, 'Nada Mostafa Abd El Monem El Kady', 'nada@gmail.com', 'MG25187', 'Digital Marketing', 1, 0, '2026-01-02 15:08:43'),
(19, 'Omar Ahmed Hanafy Kassab', 'omar@gmail.com', 'MG25056', 'Digital Marketing', 1, 0, '2026-01-02 15:09:44'),
(20, 'Omar Khaled AboEl Nasr Abd El monem', 'oo@gmail.com', 'MG24728', 'Digital Marketing', 1, 0, '2026-01-02 15:11:18'),
(21, 'Rita Samuel Raouf El Kommos', 'rit@gmail.com', 'MG25061', 'Digital Marketing', 1, 0, '2026-01-02 15:25:29'),
(22, 'Roba Mahmoud Nasef Shaker', 'rob@gmail.com', 'MG25512', 'Digital Marketing', 1, 0, '2026-01-02 15:26:15'),
(23, 'Salma Khaled Mohamed Yousef', 'salma@gmail.com', 'MG24405', 'Digital Marketing', 1, 0, '2026-01-02 15:27:07'),
(24, 'Sarah Ashraf Mohamed Reda Shehata', 'sar@gmail.com', 'MG25122', 'Digital Marketing', 1, 0, '2026-01-02 15:28:10'),
(25, 'Yasmine Habib Ibn Arabi', 'yae@gmail.com', 'MG25577', 'Digital Marketing', 1, 0, '2026-01-02 15:28:55'),
(26, 'Yassin Ahmed El dardiry Mohamed', 'Yass@gmail.com', 'GM24747-TR', 'Digital Marketing', 1, 0, '2026-01-02 15:30:46'),
(27, 'Youssef Ahmed El Sayed State', 'yous@gmail.com', 'MG25189', 'Digital Marketing', 1, 0, '2026-01-02 15:37:05'),
(28, 'Youssef Raafat Mahrous Fahim', 'y@gmail.com', 'MG24753', 'Digital Marketing', 1, 0, '2026-01-02 15:38:06'),
(29, 'Yousuf Mohamed Aly Amin', 'amin@gmail.com', 'MG25537', 'Digital Marketing', 1, 0, '2026-01-02 15:38:59'),
(30, 'Molly Raymond Abdelmalak', 'molly@gmail.com', 'MG24571', 'Digital Marketing', 2, 0, '2026-01-02 15:40:30'),
(31, 'Mohamed Galal Mohamed', 'Galal@gmail.com', 'MG24735', 'Digital Marketing', 2, 0, '2026-01-02 15:41:15'),
(33, 'Nada Amer Rafat Mabrouk', 'Nada2@gmail.com', 'MG24385', 'Digital Marketing', 2, 0, '2026-01-02 15:43:43'),
(34, 'Lena Magdy Mohamed', 'lena@gmail.com', 'MG24647', 'Digital Marketing', 2, 0, '2026-01-02 15:44:28'),
(35, 'Mazen Mohamed Diab Mohammed', 'maz@gmail.com', 'MG24751', 'Digital Marketing', 2, 0, '2026-01-02 15:58:51'),
(36, 'Adham Ashraf', 'adham@gmail.com', 'M24760', 'Digital Marketing', 2, 0, '2026-01-16 13:59:20'),
(38, 'Aly Amr AbdelRaof Sabaa', 'AlyAmr@gmail.com', 'MG24458', 'Digital Marketing', 2, 0, '2026-01-16 14:00:18'),
(39, 'David Fakhry mahrous yakoub', 'David@gmail.com', 'MG24773', 'Digital Marketing', 2, 0, '2026-01-16 14:00:49'),
(40, 'Habiba Essam Eldin Mohamed Abdelhamid', 'Habiba@gmail.com', 'MG24373', 'Digital Marketing', 2, 0, '2026-01-16 14:01:20'),
(41, 'Mai Tarek Ramadan Mohamed', 'Mai@gmail.com', 'MG24287', 'Digital Marketing', 2, 0, '2026-01-16 14:01:54'),
(42, 'Marawan Gasser Mohamed Fahmy', 'Marawan@gmail.com', 'MG24400', 'Digital Marketing', 2, 0, '2026-01-16 14:02:37');

-- --------------------------------------------------------

--
-- Table structure for table `student_schedules`
--

CREATE TABLE `student_schedules` (
  `student_schedule_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `schedule_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `weeks`
--

CREATE TABLE `weeks` (
  `week_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `weeks`
--

INSERT INTO `weeks` (`week_id`, `label`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 'Week 1', '2026-01-04', '2025-12-31', 'closed', '2025-12-29 13:57:47'),
(2, 'Week 2', '2026-01-11', '2026-01-16', 'closed', '2026-01-11 08:55:34'),
(3, 'Week 3', '2026-01-18', NULL, 'active', '2026-01-16 17:23:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `uq_admins_email` (`email`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `uq_attendance_schedule_student` (`schedule_id`,`student_id`),
  ADD KEY `idx_attendance_student` (`student_id`),
  ADD KEY `idx_attendance_schedule` (`schedule_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_created` (`created_at`),
  ADD KEY `idx_audit_admin` (`admin_id`),
  ADD KEY `idx_audit_doctor` (`doctor_id`),
  ADD KEY `idx_audit_course` (`course_id`),
  ADD KEY `idx_audit_week` (`week_id`),
  ADD KEY `idx_audit_schedule` (`schedule_id`);

--
-- Indexes for table `cancelled_doctor_schedules`
--
ALTER TABLE `cancelled_doctor_schedules`
  ADD PRIMARY KEY (`cancelled_id`),
  ADD UNIQUE KEY `uq_cancelled_day` (`week_id`,`doctor_id`,`day_of_week`,`cancelled_scope`,`slot_number`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD KEY `idx_courses_year_sem_code` (`year_level`,`semester`,`subject_code`),
  ADD KEY `idx_courses_doctor_id` (`doctor_id`),
  ADD KEY `idx_courses_program_year` (`program`,`year_level`),
  ADD KEY `idx_courses_semester` (`semester`);

--
-- Indexes for table `course_doctors`
--
ALTER TABLE `course_doctors`
  ADD PRIMARY KEY (`course_id`,`doctor_id`),
  ADD KEY `idx_course_doctor_doctor` (`doctor_id`);

--
-- Indexes for table `course_doctor_hours`
--
ALTER TABLE `course_doctor_hours`
  ADD PRIMARY KEY (`course_id`,`doctor_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`),
  ADD UNIQUE KEY `uq_doctors_email` (`email`),
  ADD UNIQUE KEY `uq_doctors_color_code` (`color_code`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `uq_week_doctor_day_slot` (`week_id`,`doctor_id`,`day_of_week`,`slot_number`),
  ADD UNIQUE KEY `uq_week_room_day_slot` (`week_id`,`room_code`,`day_of_week`,`slot_number`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_week` (`week_id`),
  ADD KEY `idx_room` (`room_code`),
  ADD KEY `fk_doctor_schedules_doctor` (`doctor_id`);

--
-- Indexes for table `doctor_slot_cancellations`
--
ALTER TABLE `doctor_slot_cancellations`
  ADD PRIMARY KEY (`slot_cancellation_id`),
  ADD UNIQUE KEY `uq_week_doctor_day_slot` (`week_id`,`doctor_id`,`day_of_week`,`slot_number`),
  ADD KEY `idx_slot_cancel_week` (`week_id`),
  ADD KEY `fk_slot_cancel_doctor` (`doctor_id`);

--
-- Indexes for table `doctor_unavailability`
--
ALTER TABLE `doctor_unavailability`
  ADD PRIMARY KEY (`unavailability_id`),
  ADD KEY `idx_unavailability_range` (`doctor_id`,`start_datetime`,`end_datetime`);

--
-- Indexes for table `doctor_week_cancellations`
--
ALTER TABLE `doctor_week_cancellations`
  ADD PRIMARY KEY (`cancellation_id`),
  ADD UNIQUE KEY `uq_week_doctor_day` (`week_id`,`doctor_id`,`day_of_week`),
  ADD KEY `fk_cancel_doctor` (`doctor_id`);

--
-- Indexes for table `doctor_year_colors`
--
ALTER TABLE `doctor_year_colors`
  ADD PRIMARY KEY (`doctor_id`,`year_level`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_events_date` (`event_date`),
  ADD KEY `idx_events_week` (`week_id`),
  ADD KEY `idx_events_doctor` (`doctor_id`),
  ADD KEY `idx_events_room` (`room_code`),
  ADD KEY `idx_events_admin` (`created_by_admin_id`);

--
-- Indexes for table `floors`
--
ALTER TABLE `floors`
  ADD PRIMARY KEY (`floor_id`),
  ADD UNIQUE KEY `uq_floors_name` (`floor_name`);

--
-- Indexes for table `portal_users`
--
ALTER TABLE `portal_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uniq_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_doctor_id` (`doctor_id`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `uq_rooms_code` (`room_code`),
  ADD KEY `idx_rooms_floor` (`floor_id`),
  ADD KEY `idx_rooms_active_floor` (`floor_id`,`is_active`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `uq_students_email` (`email`),
  ADD UNIQUE KEY `uq_students_student_code` (`student_code`),
  ADD KEY `idx_students_program_year` (`program`,`year_level`);

--
-- Indexes for table `student_schedules`
--
ALTER TABLE `student_schedules`
  ADD PRIMARY KEY (`student_schedule_id`),
  ADD UNIQUE KEY `uq_student_schedule` (`student_id`,`schedule_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_schedule` (`schedule_id`),
  ADD KEY `idx_admin` (`assigned_by_admin_id`);

--
-- Indexes for table `weeks`
--
ALTER TABLE `weeks`
  ADD PRIMARY KEY (`week_id`),
  ADD UNIQUE KEY `uq_weeks_label` (`label`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `attendance_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=317;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `audit_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cancelled_doctor_schedules`
--
ALTER TABLE `cancelled_doctor_schedules`
  MODIFY `cancelled_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctor_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `schedule_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `doctor_slot_cancellations`
--
ALTER TABLE `doctor_slot_cancellations`
  MODIFY `slot_cancellation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `doctor_unavailability`
--
ALTER TABLE `doctor_unavailability`
  MODIFY `unavailability_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_week_cancellations`
--
ALTER TABLE `doctor_week_cancellations`
  MODIFY `cancellation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `floors`
--
ALTER TABLE `floors`
  MODIFY `floor_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portal_users`
--
ALTER TABLE `portal_users`
  MODIFY `user_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `student_schedules`
--
ALTER TABLE `student_schedules`
  MODIFY `student_schedule_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weeks`
--
ALTER TABLE `weeks`
  MODIFY `week_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `fk_attendance_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `doctor_schedules` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_audit_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_audit_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_audit_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `doctor_schedules` (`schedule_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_audit_week` FOREIGN KEY (`week_id`) REFERENCES `weeks` (`week_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `course_doctors`
--
ALTER TABLE `course_doctors`
  ADD CONSTRAINT `fk_course_doctors_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_course_doctors_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `fk_doctor_schedules_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_doctor_schedules_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_doctor_schedules_week` FOREIGN KEY (`week_id`) REFERENCES `weeks` (`week_id`) ON UPDATE CASCADE;

--
-- Constraints for table `doctor_slot_cancellations`
--
ALTER TABLE `doctor_slot_cancellations`
  ADD CONSTRAINT `fk_slot_cancel_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_slot_cancel_week` FOREIGN KEY (`week_id`) REFERENCES `weeks` (`week_id`) ON UPDATE CASCADE;

--
-- Constraints for table `doctor_unavailability`
--
ALTER TABLE `doctor_unavailability`
  ADD CONSTRAINT `fk_unavailability_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctor_week_cancellations`
--
ALTER TABLE `doctor_week_cancellations`
  ADD CONSTRAINT `fk_cancel_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cancel_week` FOREIGN KEY (`week_id`) REFERENCES `weeks` (`week_id`) ON UPDATE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_events_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_events_week` FOREIGN KEY (`week_id`) REFERENCES `weeks` (`week_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `floors`
--

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_rooms_floor` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`floor_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_schedules`
--
ALTER TABLE `student_schedules`
  ADD CONSTRAINT `fk_student_schedule_admin` FOREIGN KEY (`assigned_by_admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_schedule_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `doctor_schedules` (`schedule_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_schedule_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
