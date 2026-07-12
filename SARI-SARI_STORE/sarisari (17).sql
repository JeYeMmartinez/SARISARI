-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 12, 2026 at 01:10 PM
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
-- Database: `sarisari`
--

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `applicant_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `stage` enum('Initial Screening','First Interview','Final Interview','Approved','Rejected') DEFAULT 'Initial Screening',
  `notes` text DEFAULT NULL,
  `schedule` varchar(100) DEFAULT NULL,
  `rest_day` varchar(50) DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`applicant_id`, `position_id`, `full_name`, `email`, `phone`, `address`, `resume`, `stage`, `notes`, `schedule`, `rest_day`, `applied_at`, `updated_at`) VALUES
(10, 6, 'jushiramen', 'sushiramenthis1@gmail.com', '09123123123', 'BLK 1 LOT 28', '1783853220_LCS_Reviewer_20260711_175344_0000.pdf', 'Approved', 'qfqwfq', NULL, NULL, '2026-07-12 10:47:00', '2026-07-12 18:47:13');

-- --------------------------------------------------------

--
-- Table structure for table `applicants_archive`
--

CREATE TABLE `applicants_archive` (
  `archive_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `position_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `stage` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `applied_at` timestamp NULL DEFAULT NULL,
  `archive_reason` varchar(50) DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants_archive`
--

INSERT INTO `applicants_archive` (`archive_id`, `applicant_id`, `position_id`, `full_name`, `email`, `phone`, `address`, `resume`, `stage`, `notes`, `applied_at`, `archive_reason`, `archived_by`, `archived_at`) VALUES
(1, 8, 4, 'John Mark P Martinez', 'Johnmmartinez814@gmail.com', '09946710564', 'BLK 1 LOT 28', '', 'Approved', 'qqfwqfq', '2026-07-11 14:12:35', 'Hired', 1, '2026-07-11 14:47:38'),
(2, 9, 4, 'Sushi', 'sushiramenthis1@gmail.com', '09467105643', 'BLK 1 LOT 28', '1783850790_LCS_Reviewer_20260711_175344_0000.pdf', 'Approved', 'fqqwfqwfqwf', '2026-07-12 10:06:30', 'Hired', 1, '2026-07-12 10:29:48');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('Present','Absent','Late','Half Day','On Leave') DEFAULT 'Present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `employee_id`, `date`, `time_in`, `time_out`, `hours_worked`, `overtime_hours`, `status`, `notes`, `created_at`, `updated_at`, `photo`) VALUES
(1, 7, '2026-07-11', NULL, NULL, 0.00, 0.00, 'Present', '', '2026-07-11 15:33:46', '2026-07-11 23:33:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('Create','Update','Delete','Login','Logout','Void','Status Change','Approve','Reject','Archive','Restore') NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `table_name`, `record_id`, `description`, `created_at`) VALUES
(1, 1, 'Create', 'sales', 1, 'Processed sale #1 — Total: ₱43', '2026-06-26 18:09:57'),
(2, 1, 'Create', 'products', 9, 'Added product: Panda', '2026-06-26 18:11:37'),
(3, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 02:45:33'),
(4, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 02:46:03'),
(5, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 02:46:11'),
(6, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 07:28:23'),
(7, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 07:28:23'),
(8, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 07:28:31'),
(9, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 07:28:39'),
(10, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 07:29:09'),
(11, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 07:34:59'),
(12, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 07:35:30'),
(13, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 07:41:33'),
(14, 1, 'Create', 'products', 10, 'Added product: wow', '2026-06-27 07:45:35'),
(15, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 07:46:08'),
(16, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 07:51:28'),
(17, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 07:51:34'),
(18, 1, 'Create', 'sales', 2, 'Processed sale #2 — Total: ₱12', '2026-06-27 10:47:32'),
(19, 1, 'Create', 'sales', 3, 'Processed sale #3 — Total: ₱2706', '2026-06-27 10:48:04'),
(20, 1, 'Create', 'products', 11, 'Added product: dasasdasdas', '2026-06-27 11:04:50'),
(21, 1, 'Delete', 'products', 11, 'Deleted product \'dasasdasdas\' — Reason: wqeqeqewqe', '2026-06-27 11:07:41'),
(22, 1, 'Create', 'products', 12, 'Added product: DING DONG (initial stock: 1)', '2026-06-27 12:04:14'),
(23, 1, 'Delete', 'products', 12, 'Deleted product \'DING DONG\' — Reason: sdasdsa', '2026-06-27 12:36:41'),
(24, 1, 'Delete', 'products', 10, 'Deleted product \'wow\' — Reason: qweqwe', '2026-06-27 12:44:13'),
(25, 1, '', 'products', 9, 'Moved to trash: \'Panda\' — Reason: asdas', '2026-06-27 13:01:52'),
(26, 1, '', 'products', 7, 'Moved to trash: \'Rexona Small\' — Reason: asdsad', '2026-06-27 13:02:33'),
(27, 1, '', 'products', 6, 'Moved to trash: \'Century Tuna Spicy\' — Reason: asdasd', '2026-06-27 13:10:28'),
(28, 1, '', 'products', 6, 'Restored product \'Century Tuna Spicy\' — Reason: asdasdasd', '2026-06-27 13:59:12'),
(29, 1, '', 'products', 9, 'Permanently deleted \'Panda\' — Reason: asdasd', '2026-06-27 13:59:43'),
(30, 1, '', 'products', 7, 'Restored product \'Rexona Small\' — Reason: asdada', '2026-06-27 14:04:49'),
(31, 1, '', 'products', 7, 'Moved to trash: \'Rexona Small\' — Reason: asdasd', '2026-06-27 14:05:11'),
(32, 1, '', 'products', 7, 'Restored product \'Rexona Small\' — Reason: asdasdasd', '2026-06-27 15:25:41'),
(33, 1, '', 'products', 14, 'Moved to trash: \'Liquid Detergent\' — Reason: asdasd', '2026-06-27 15:26:13'),
(34, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 15:27:29'),
(35, 2, 'Create', 'users', 2, 'Registered & Verified new customer: John Mark Martinez', '2026-06-27 15:38:25'),
(36, 2, 'Logout', 'users', 2, 'John Mark Martinez logged out', '2026-06-27 15:38:39'),
(37, 2, 'Login', 'users', 2, 'John Mark Martinez logged in', '2026-06-27 15:40:00'),
(38, 2, 'Logout', 'users', 2, 'John Mark Martinez logged out', '2026-06-27 15:54:42'),
(39, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 15:54:52'),
(40, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 15:57:49'),
(41, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 15:58:05'),
(42, 1, '', 'products', 7, 'Moved to trash: \'Rexona Small\' — Reason: qweqwe', '2026-06-27 16:29:53'),
(43, 1, 'Create', 'users', 3, 'Created user account: Jeyem (Cashier)', '2026-06-27 16:33:09'),
(44, 1, 'Update', 'users', 1, 'Updated user account: Admin User (Admin)', '2026-06-27 16:33:26'),
(45, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 16:33:30'),
(46, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 16:33:40'),
(47, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 16:33:55'),
(48, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-27 16:34:11'),
(49, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-27 16:35:00'),
(50, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 16:35:07'),
(51, 1, '', 'products', 6, 'Moved to trash: \'Century Tuna Spicy\' — Reason: asdasd', '2026-06-27 16:39:58'),
(52, 1, '', 'products', 6, 'Restored product \'Century Tuna Spicy\' — Reason: sadasd', '2026-06-27 16:43:12'),
(53, 1, '', 'products', 7, 'Restored product \'Rexona Small\' — Reason: asdasd', '2026-06-27 16:46:02'),
(54, 1, '', 'products', 14, 'Restored product \'Liquid Detergent\' — Reason: asdasd', '2026-06-27 16:48:53'),
(55, 1, '', 'products', 14, 'Moved to trash: \'Liquid Detergent\' — Reason: sadasd', '2026-06-27 16:49:16'),
(56, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-27 23:54:24'),
(57, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-27 23:55:05'),
(58, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-27 23:55:13'),
(59, 3, 'Create', 'sales', 4, 'Processed sale #4 — Total: ₱172', '2026-06-28 02:12:28'),
(60, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-28 02:12:54'),
(61, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-28 02:13:43'),
(62, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-28 02:14:39'),
(63, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 02:14:48'),
(64, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-28 07:45:35'),
(65, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-28 07:45:41'),
(66, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-28 08:07:52'),
(67, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 08:08:05'),
(68, 1, 'Create', 'sales', 5, 'Processed sale #5 — Total: ₱108.25', '2026-06-28 08:08:32'),
(69, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-28 10:14:05'),
(70, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 10:14:28'),
(71, 2, 'Login', 'users', 2, 'John Mark Martinez logged in', '2026-06-28 10:15:20'),
(72, 2, 'Create', 'orders', 1, 'Order #1 placed — Subtotal: ₱280.25 | Tax: ₱33.63 | Total: ₱313.88', '2026-06-28 10:16:06'),
(73, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 10:17:46'),
(74, 2, 'Create', 'orders', 2, 'Order #2 placed — Subtotal: ₱433.00 | Tax: ₱51.96 | Total: ₱484.96', '2026-06-28 10:53:27'),
(75, 1, 'Create', 'sales', 6, 'Approved order #1 for John Mark Martinez → Sale #6 created — Note: asdad', '2026-06-28 11:15:48'),
(76, 1, 'Create', 'sales', 7, 'Approved order #2 for John Mark Martinez → Sale #7 created — Note: sadsa', '2026-06-28 11:16:29'),
(77, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-28 11:24:14'),
(78, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-28 11:24:24'),
(79, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-28 11:30:11'),
(80, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 11:30:57'),
(81, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-28 11:31:38'),
(82, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-28 11:31:46'),
(83, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-28 11:32:00'),
(84, 2, 'Logout', 'users', 2, 'John Mark Martinez logged out', '2026-06-28 11:32:25'),
(85, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 11:32:57'),
(86, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-28 12:56:56'),
(87, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-28 12:57:06'),
(88, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-28 12:58:11'),
(89, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 12:58:20'),
(90, 1, 'Create', 'products', 15, 'Added product: C2 (initial stock: 10)', '2026-06-28 14:31:58'),
(91, 2, 'Login', 'users', 2, 'John Mark Martinez logged in', '2026-06-28 14:39:01'),
(92, 2, 'Create', 'orders', 3, 'Order #3 placed — Subtotal: ₱395.75 | Tax: ₱47.49 | Total: ₱443.24', '2026-06-28 14:39:55'),
(93, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-28 14:40:02'),
(94, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-28 14:40:16'),
(95, 3, 'Create', 'sales', 8, 'Approved order #3 for John Mark Martinez → Sale #8 created — Note: asdasd', '2026-06-28 14:42:47'),
(96, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-28 14:43:59'),
(97, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 14:44:05'),
(98, 2, 'Logout', 'users', 2, 'John Mark Martinez logged out', '2026-06-28 14:54:20'),
(99, 1, '', 'products', 7, 'Moved to trash: \'Rexona Small\' — Reason: asdsad', '2026-06-28 16:56:20'),
(100, 1, 'Create', 'products', 16, 'Added product: ding dong (initial stock: 40)', '2026-06-28 17:10:10'),
(101, 1, '', 'products', 7, 'Restored product \'Rexona Small\' — Reason: asdas', '2026-06-28 17:11:57'),
(102, 1, '', 'products', 5, 'Moved to trash: \'Argentina Corn Beef Large\' — Reason: asdas', '2026-06-28 17:15:45'),
(103, 1, '', 'products', 5, 'Moved to trash: \'Argentina Corn Beef Large\' — Reason: asdasd', '2026-06-28 17:16:00'),
(104, 1, '', 'products', 6, 'Moved to trash: \'Century Tuna Spicy\' — Reason: asdasd', '2026-06-28 17:16:10'),
(105, 1, '', 'products', 7, 'Moved to trash: \'Rexona Small\' — Reason: asdasd', '2026-06-28 17:16:27'),
(106, 1, '', 'products', 13, 'Moved to trash: \'Coke 1.5L\' — Reason: asdasd', '2026-06-28 17:16:49'),
(107, 1, '', 'products', 16, 'Moved to trash: \'ding dong\' — Reason: asdasdasd', '2026-06-28 17:17:03'),
(108, 1, '', 'products', 15, 'Moved to trash: \'C2\' — Reason: asdasd', '2026-06-28 17:17:29'),
(109, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-28 18:13:01'),
(110, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-28 18:14:41'),
(111, 1, 'Create', 'products', 17, 'Added product: PRODUCT1 (initial stock: 5)', '2026-06-28 18:16:16'),
(112, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-28 18:22:24'),
(113, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-29 01:23:23'),
(114, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-06-29 01:24:59'),
(115, 3, 'Login', 'users', 3, 'Jeyem logged in', '2026-06-29 01:25:09'),
(116, 3, 'Logout', 'users', 3, 'Jeyem logged out', '2026-06-29 01:25:37'),
(117, 2, 'Login', 'users', 2, 'John Mark Martinez logged in', '2026-06-29 01:28:22'),
(118, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-06-29 01:28:35'),
(119, 2, '', 'orders', 4, 'Order #4 placed — Total: ₱138.60 (inventory reserved)', '2026-06-29 01:28:53'),
(125, 1, 'Create', 'sales', 9, 'Processed sale #9 — Total: ₱82.5', '2026-06-29 03:05:16'),
(126, 2, 'Login', 'users', 2, 'John Mark Martinez logged in', '2026-06-29 03:18:32'),
(127, 2, 'Logout', 'users', 2, 'John Mark Martinez logged out', '2026-06-29 03:26:54'),
(134, 1, 'Create', 'products', 18, 'Added product: asdasdasd11 (initial stock: 10)', '2026-06-29 03:43:43'),
(135, 2, 'Login', 'users', 2, 'John Mark Martinez logged in', '2026-06-29 03:43:59'),
(136, 2, '', 'orders', 5, 'Order #5 placed — Total: ₱30.80 (inventory reserved)', '2026-06-29 03:44:09'),
(137, 1, 'Create', 'sales', 10, 'Approved order #5 for John Mark Martinez → Sale #10 created — Note: asdasd', '2026-06-29 03:44:20'),
(149, 2, 'Logout', 'users', 2, 'John Mark Martinez logged out', '2026-06-29 03:59:00'),
(150, 1, 'Void', 'orders', 4, 'Cancelled order #4 for John Mark Martinez — Reason: sadsa', '2026-06-29 04:00:39'),
(151, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-07-04 03:22:32'),
(152, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-07-06 17:27:28'),
(153, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-07-07 13:03:56'),
(157, 1, '', 'positions', 2, 'Changed status of \'Store Clerk\' from Open to On Hold.', '2026-07-07 16:57:09'),
(158, 1, '', 'positions', 1, 'Changed status of \'Store Clerk\' from Closed to Open.', '2026-07-07 16:57:27'),
(159, 1, '', 'positions', 2, 'Changed status of \'Store Clerk\' from On Hold to Open.', '2026-07-07 16:57:32'),
(160, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-07-08 01:49:51'),
(161, 1, 'Create', 'applicants', 1, 'Added applicant: wqewqewqeqwe', '2026-07-08 02:02:50'),
(162, 1, 'Update', 'applicants', 1, 'Advanced wqewqewqeqwe to stage: First Interview', '2026-07-08 02:04:42'),
(163, 1, 'Update', 'applicants', 1, 'Advanced wqewqewqeqwe to stage: Final Interview', '2026-07-08 02:04:47'),
(164, 1, 'Update', 'applicants', 1, 'Advanced wqewqewqeqwe to stage: Approved', '2026-07-08 02:04:51'),
(165, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-07-08 11:33:45'),
(166, 1, 'Create', 'employees', 1, 'Converted applicant EDon to Employee #EMP-0001', '2026-07-08 12:09:00'),
(167, 1, 'Update', 'employees', 1, 'Updated details of employee: EDon', '2026-07-08 13:58:11'),
(168, 1, 'Create', 'positions', 3, 'Created job posting: efwqefwef (Full-time, 34 slots, ₱213123–₱123123123, Open)', '2026-07-08 14:20:30'),
(169, 1, 'Create', 'applicants', 2, 'Added applicant: Lucky Me Noodles', '2026-07-08 14:22:18'),
(170, 1, 'Update', 'applicants', 2, 'Advanced Lucky Me Noodles to stage: First Interview', '2026-07-08 14:22:34'),
(171, 1, 'Update', 'applicants', 2, 'Advanced Lucky Me Noodles to stage: Final Interview', '2026-07-08 14:26:18'),
(172, 1, 'Update', 'applicants', 2, 'Advanced Lucky Me Noodles to stage: Approved', '2026-07-08 14:26:23'),
(173, 1, 'Create', 'employees', 2, 'Converted applicant Lucky Me Noodles to Employee #EMP-0002', '2026-07-08 14:29:54'),
(174, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-07-09 12:11:01'),
(175, 1, 'Create', 'applicants', 3, 'Added applicant: John Mark P Martinez', '2026-07-10 09:37:38'),
(176, 1, 'Update', 'applicants', 3, 'Advanced John Mark P Martinez to stage: First Interview', '2026-07-10 09:37:43'),
(177, 1, 'Update', 'applicants', 3, 'Advanced John Mark P Martinez to stage: Final Interview', '2026-07-10 09:37:46'),
(178, 1, 'Update', 'applicants', 3, 'Advanced John Mark P Martinez to stage: Approved', '2026-07-10 09:37:54'),
(179, 1, 'Delete', 'applicants', 1, 'Deleted applicant: wqewqewqeqwe', '2026-07-10 09:56:47'),
(180, 1, 'Delete', 'applicants', 2, 'Deleted applicant: Lucky Me Noodles', '2026-07-10 09:56:50'),
(181, 1, 'Delete', 'employees', 1, 'Deleted employee: EDon (#EMP-0001)', '2026-07-10 10:18:02'),
(182, 1, 'Delete', 'employees', 2, 'Deleted employee: Lucky Me Noodles (#EMP-0002)', '2026-07-10 10:18:06'),
(183, 1, 'Delete', 'positions', 2, 'Deleted job posting: Store Clerk', '2026-07-10 10:18:17'),
(184, 1, 'Delete', 'applicants', 3, 'Deleted applicant: John Mark P Martinez', '2026-07-10 10:18:30'),
(185, 1, 'Delete', 'positions', 1, 'Deleted job posting: Store Clerk', '2026-07-10 10:18:34'),
(186, 1, 'Delete', 'positions', 3, 'Deleted job posting: efwqefwef', '2026-07-10 10:18:36'),
(187, 1, 'Create', 'positions', 4, 'Created job posting: Sales Supervisor (Full-time, 1 slots, ₱100–₱500, Open)', '2026-07-10 16:18:48'),
(188, 1, 'Create', 'applicants', 4, 'Added applicant: John Mark P Martinez', '2026-07-10 16:19:50'),
(189, 1, 'Update', 'applicants', 4, 'Advanced John Mark P Martinez to stage: First Interview', '2026-07-10 16:21:16'),
(190, 1, 'Update', 'applicants', 4, 'Advanced John Mark P Martinez to stage: Final Interview', '2026-07-10 16:21:20'),
(191, 1, 'Update', 'applicants', 4, 'Advanced John Mark P Martinez to stage: Approved', '2026-07-10 16:21:24'),
(192, 1, 'Create', 'employees', 3, 'Converted applicant John Mark P Martinez to Employee #EMP-0001', '2026-07-10 16:25:43'),
(193, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-07-10 16:27:12'),
(194, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-07-10 16:27:34'),
(195, 1, 'Create', 'applicants', 5, 'Added applicant: Sushi', '2026-07-11 11:36:13'),
(196, 1, 'Update', 'applicants', 5, 'Advanced Sushi to stage: First Interview', '2026-07-11 11:36:18'),
(197, 1, 'Update', 'applicants', 5, 'Advanced Sushi to stage: Final Interview', '2026-07-11 11:36:21'),
(198, 1, 'Update', 'applicants', 5, 'Advanced Sushi to stage: Approved', '2026-07-11 11:36:26'),
(199, 1, 'Create', 'employees', 4, 'Converted applicant Sushi to Employee #EMP-0002', '2026-07-11 11:36:58'),
(200, 1, 'Update', 'employees', 3, 'Updated details of employee: John Mark P Martinez', '2026-07-11 12:39:15'),
(201, 1, 'Create', 'applicants', 6, 'Added applicant: martinez', '2026-07-11 12:41:03'),
(202, 1, 'Update', 'applicants', 6, 'Advanced martinez to stage: First Interview', '2026-07-11 12:41:07'),
(203, 1, 'Update', 'applicants', 6, 'Advanced martinez to stage: Final Interview', '2026-07-11 12:41:11'),
(204, 1, 'Update', 'applicants', 6, 'Advanced martinez to stage: Approved', '2026-07-11 12:41:18'),
(205, 1, 'Create', 'employees', 5, 'Converted applicant martinez to Employee #EMP-0003', '2026-07-11 12:41:41'),
(206, 1, 'Delete', 'applicants', 6, 'Deleted applicant: martinez', '2026-07-11 13:05:29'),
(207, 1, 'Create', 'applicants', 7, 'Added applicant: martinez', '2026-07-11 13:05:49'),
(208, 1, 'Update', 'applicants', 7, 'Advanced martinez to stage: First Interview', '2026-07-11 13:05:53'),
(209, 1, 'Update', 'applicants', 7, 'Advanced martinez to stage: Final Interview', '2026-07-11 13:05:58'),
(210, 1, 'Update', 'applicants', 7, 'Advanced martinez to stage: Approved', '2026-07-11 13:06:02'),
(211, 1, 'Create', 'employees', 6, 'Converted applicant martinez to Employee #EMP-0004', '2026-07-11 13:10:53'),
(212, 1, 'Logout', 'users', 1, 'Admin User logged out', '2026-07-11 13:31:46'),
(213, 1, 'Login', 'users', 1, 'Admin User logged in', '2026-07-11 13:35:56'),
(214, 1, 'Create', 'positions', 6, 'Created new position: Cashier (Full-time)', '2026-07-11 13:36:42'),
(215, 1, 'Delete', 'applicants', 7, 'Deleted applicant: martinez', '2026-07-11 13:47:58'),
(216, 1, 'Delete', 'applicants', 5, 'Deleted applicant: Sushi', '2026-07-11 13:48:02'),
(217, 1, 'Delete', 'applicants', 4, 'Deleted applicant: John Mark P Martinez', '2026-07-11 13:48:04'),
(218, 1, 'Delete', 'employees', 6, 'Deleted employee: martinez (#EMP-0004)', '2026-07-11 13:48:15'),
(219, 1, 'Delete', 'employees', 4, 'Deleted employee: Sushi (#EMP-0002)', '2026-07-11 13:48:26'),
(220, 1, 'Delete', 'employees', 5, 'Deleted employee: martinez (#EMP-0003)', '2026-07-11 13:48:37'),
(221, 1, 'Delete', 'employees', 3, 'Archived & deleted employee: John Mark P Martinez (#EMP-0001) — Reason: qwdqwdqwd', '2026-07-11 14:11:54'),
(222, 1, 'Create', 'applicants', 8, 'Added applicant: John Mark P Martinez', '2026-07-11 14:12:35'),
(223, 1, 'Update', 'applicants', 8, 'Advanced John Mark P Martinez to stage: First Interview', '2026-07-11 14:29:29'),
(224, 1, 'Update', 'applicants', 8, 'Advanced John Mark P Martinez to stage: Final Interview', '2026-07-11 14:29:41'),
(225, 1, 'Update', 'applicants', 8, 'Advanced John Mark P Martinez to stage: Approved', '2026-07-11 14:29:44'),
(226, 1, 'Create', 'employees', 7, 'Converted applicant John Mark P Martinez to Employee #EMP-0001', '2026-07-11 14:47:38'),
(227, 1, 'Create', 'attendance', 1, 'Manual attendance entry for employee ID 7 on 2026-07-11', '2026-07-11 15:33:46'),
(228, 1, 'Create', 'applicants', 9, 'Added applicant: Sushi', '2026-07-12 10:06:30'),
(229, 1, 'Update', 'applicants', 9, 'Advanced Sushi to stage: First Interview', '2026-07-12 10:07:05'),
(230, 1, 'Update', 'applicants', 9, 'Advanced Sushi to stage: Final Interview', '2026-07-12 10:07:09'),
(231, 1, 'Update', 'applicants', 9, 'Advanced Sushi to stage: Approved', '2026-07-12 10:07:12'),
(232, 1, 'Delete', 'employees', 8, 'Archived & deleted employee: Sushi (#EMP-0002) — Reason: fr2q3f', '2026-07-12 10:37:43'),
(233, 1, 'Delete', 'employees', 9, 'Archived & deleted employee: Sushi (#EMP-0003) — Reason: 3r23r23r', '2026-07-12 10:37:54'),
(234, 1, 'Create', 'applicants', 10, 'Added applicant: jushiramen', '2026-07-12 10:47:00'),
(235, 1, 'Update', 'applicants', 10, 'Advanced jushiramen to stage: First Interview', '2026-07-12 10:47:08'),
(236, 1, 'Update', 'applicants', 10, 'Advanced jushiramen to stage: Final Interview', '2026-07-12 10:47:11'),
(237, 1, 'Update', 'applicants', 10, 'Advanced jushiramen to stage: Approved', '2026-07-12 10:47:13');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_registrations`
--

CREATE TABLE `blocked_registrations` (
  `gmail` varchar(255) NOT NULL,
  `blocked_until` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Completed','Pending','Voided') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `cashier_id`, `total_amount`, `payment`, `change_amount`, `status`, `created_at`) VALUES
(1, 2, 484.96, 0.00, 0.00, 'Pending', '2026-06-28 10:53:27'),
(2, 2, 443.24, 0.00, 0.00, 'Pending', '2026-06-28 14:39:55');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_item_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Food & Groceries', '(Canned goods, instant noodles, rice, condiments, oil)', '2026-06-25 15:16:43'),
(2, 'Beverages ', '(Soft drinks, water, coffee sachets, powdered juice, liquor)', '2026-06-25 15:16:43'),
(3, 'Snacks & Sweets', '(Chips, biscuits, candies, chocolates, bakery items)', '2026-06-25 15:16:43'),
(4, 'Personal Care', '(Shampoo, soap, toothpaste, sanitary napkins)', '2026-06-25 15:16:43'),
(5, 'Household Supplies', '(Detergents, dishwashing liquid, bleach, matches, candles)', '2026-06-25 15:16:43'),
(6, 'Digital Services & Loading ', '(Prepaid load, GCash/Maya cash-in, gaming pins)', '2026-06-25 15:16:43'),
(7, 'Tobacco & Alcohol', '(Cigarettes, lighters, beer, hard drinks)', '2026-06-25 15:16:43'),
(8, 'Miscellaneous / Others', '(School supplies, rags, pet food)', '2026-06-25 15:16:43');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `description`, `created_at`) VALUES
(1, 'Store Operations', 'Handles day-to-day store operations', '2026-07-06 17:24:12'),
(2, 'Cashiering', 'Handles all cashier and payment transactions', '2026-07-06 17:24:12'),
(3, 'Inventory', 'Manages product stocks and deliveries', '2026-07-06 17:24:12'),
(4, 'Administration', 'Handles HR, payroll, and admin tasks', '2026-07-06 17:24:12');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `position_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `applicant_id` int(11) DEFAULT NULL,
  `employee_no` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contractual','Probationary') DEFAULT 'Full-time',
  `basic_salary` decimal(10,2) DEFAULT 0.00,
  `status` enum('Active','Inactive','Resigned','Terminated') DEFAULT 'Active',
  `photo` varchar(255) DEFAULT NULL,
  `sss_no` varchar(30) DEFAULT NULL,
  `philhealth_no` varchar(30) DEFAULT NULL,
  `pagibig_no` varchar(30) DEFAULT NULL,
  `tin_no` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `position_id`, `department_id`, `applicant_id`, `employee_no`, `full_name`, `email`, `phone`, `address`, `birthdate`, `gender`, `civil_status`, `date_hired`, `employment_type`, `basic_salary`, `status`, `photo`, `sss_no`, `philhealth_no`, `pagibig_no`, `tin_no`, `created_at`, `updated_at`) VALUES
(7, 4, NULL, 8, 'EMP-0001', 'John Mark P Martinez', 'Johnmmartinez814@gmail.com', '09946710564', 'BLK 1 LOT 28', '2005-08-14', 'Male', 'Single', '2026-07-11', 'Full-time', 300000.00, 'Active', NULL, '123213123123', '123123123123', '123123123', '123123123', '2026-07-11 14:47:38', '2026-07-11 22:47:38');

-- --------------------------------------------------------

--
-- Table structure for table `employees_archive`
--

CREATE TABLE `employees_archive` (
  `archive_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `employee_no` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contractual','Probationary') DEFAULT NULL,
  `basic_salary` decimal(10,2) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `sss_no` varchar(30) DEFAULT NULL,
  `philhealth_no` varchar(30) DEFAULT NULL,
  `pagibig_no` varchar(30) DEFAULT NULL,
  `tin_no` varchar(30) DEFAULT NULL,
  `position_name` varchar(100) DEFAULT NULL,
  `department_name` varchar(100) DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees_archive`
--

INSERT INTO `employees_archive` (`archive_id`, `employee_id`, `employee_no`, `full_name`, `email`, `phone`, `address`, `birthdate`, `gender`, `civil_status`, `date_hired`, `employment_type`, `basic_salary`, `status`, `photo`, `sss_no`, `philhealth_no`, `pagibig_no`, `tin_no`, `position_name`, `department_name`, `deleted_by`, `deleted_reason`, `deleted_at`) VALUES
(1, 3, 'EMP-0001', 'John Mark P Martinez', 'johnmmartinez814@gmail.com', '09946710564', 'BLK 1 LOT 28', '2005-08-14', 'Male', 'Single', '2026-07-10', 'Full-time', 300000.00, 'Active', '', '213123123', '123123123', '123123123', '12321321', 'Sales Supervisor', 'Administration', 1, 'qwdqwdqwd', '2026-07-11 14:11:54'),
(2, 8, 'EMP-0002', 'Sushi', 'sushiramenthis1@gmail.com', '09467105643', 'BLK 1 LOT 28', '0000-00-00', 'Male', 'Single', '2026-07-12', '', 100.07, 'Active', '', '', '', '', '', 'Cashier', '', 1, 'fr2q3f', '2026-07-12 10:37:43'),
(3, 9, 'EMP-0003', 'Sushi', 'sushiramenthis1@gmail.com', '09467105643', 'BLK 1 LOT 28', '0000-00-00', 'Male', 'Single', '2026-07-12', '', 100.07, 'Active', '', '21221', '2121212', '1212121212', '121212121', 'Cashier', '', 1, '3r23r23r', '2026-07-12 10:37:54');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `minimum_stock` int(11) NOT NULL DEFAULT 5,
  `maximum_Stock` int(11) DEFAULT NULL,
  `aisle` varchar(20) DEFAULT NULL,
  `last_restock` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `quantity`, `minimum_stock`, `maximum_Stock`, `aisle`, `last_restock`, `updated_at`) VALUES
(33, 1, 50, 5, 100, 'Aisle 1', NULL, '2026-06-29 11:58:41'),
(34, 2, 30, 5, 100, 'Aisle 2', NULL, '2026-06-29 11:58:41');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('Sick Leave','Vacation Leave','Emergency Leave','Maternity','Paternity') NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `days` int(11) DEFAULT 1,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('Low Stock','Approval','System','Sales') NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 'Product Restored', 'Product restored: Rexona Small', '', 1, '2026-06-27 16:46:02'),
(2, 'Product Restored', 'Product restored: Liquid Detergent', '', 1, '2026-06-27 16:48:53'),
(3, 'Product Deleted', 'Product moved to trash: Liquid Detergent', '', 1, '2026-06-27 16:49:16'),
(4, 'Sale Completed', 'Sale #4 processed successfully — Total: ₱172.00', 'Sales', 1, '2026-06-28 02:12:28'),
(5, 'Order Approved', 'Order #1 for John Mark Martinez approved → Sale #6 created', 'Approval', 1, '2026-06-28 11:15:48'),
(6, 'Order Approved', 'Order #2 for John Mark Martinez approved → Sale #7 created', 'Approval', 1, '2026-06-28 11:16:29'),
(7, 'Product Added', 'New product added: C2', '', 1, '2026-06-28 14:31:58'),
(8, 'Order Approved', 'Order #3 for John Mark Martinez approved → Sale #8 created', 'Approval', 1, '2026-06-28 14:42:47'),
(9, 'Product Deleted', 'Product moved to trash: Rexona Small', '', 1, '2026-06-28 16:56:20'),
(10, 'Product Added', 'New product added: ding dong', '', 1, '2026-06-28 17:10:11'),
(11, 'Product Restored', 'Product restored: Rexona Small', '', 1, '2026-06-28 17:11:57'),
(12, 'Product Deleted', 'Product moved to trash: Argentina Corn Beef Large', '', 1, '2026-06-28 17:15:45'),
(13, 'Product Deleted', 'Product moved to trash: Argentina Corn Beef Large', '', 1, '2026-06-28 17:16:00'),
(14, 'Product Deleted', 'Product moved to trash: Century Tuna Spicy', '', 1, '2026-06-28 17:16:10'),
(15, 'Product Deleted', 'Product moved to trash: Rexona Small', '', 1, '2026-06-28 17:16:27'),
(16, 'Product Deleted', 'Product moved to trash: Coke 1.5L', '', 1, '2026-06-28 17:16:49'),
(17, 'Product Deleted', 'Product moved to trash: ding dong', '', 1, '2026-06-28 17:17:03'),
(18, 'Product Deleted', 'Product moved to trash: C2', '', 1, '2026-06-28 17:17:29'),
(19, 'Product Added', 'New product added: PRODUCT1', '', 1, '2026-06-28 18:16:16'),
(20, 'New Order', 'Order #4 from John Mark Martinez — ₱138.60 (inventory reserved)', 'Approval', 1, '2026-06-29 01:28:53'),
(21, 'Product Added', 'New product added: asdasdasd11', '', 1, '2026-06-29 03:43:43'),
(22, 'New Order', 'Order #5 from John Mark Martinez — ₱30.80 (inventory reserved)', 'Approval', 1, '2026-06-29 03:44:09'),
(23, 'Order Approved', 'Order #5 for John Mark Martinez approved → Sale #10 created', 'Approval', 1, '2026-06-29 03:44:20'),
(47, 'Order Cancelled', 'Order #4 for John Mark Martinez was cancelled', 'Approval', 1, '2026-06-29 04:00:39'),
(48, 'Job Status Changed', 'Changed status of \'Store Clerk\' from Open to On Hold.', '', 1, '2026-07-07 16:52:01'),
(49, 'Job Status Changed', 'Changed status of \'Store Clerk\' from On Hold to Closed.', '', 1, '2026-07-07 16:52:05'),
(50, 'New Job Posting', 'Position \'Store Clerk\' has been created.', '', 1, '2026-07-07 16:52:46'),
(51, 'Job Status Changed', 'Changed status of \'Store Clerk\' from Open to On Hold.', '', 1, '2026-07-07 16:57:09'),
(52, 'Job Status Changed', 'Changed status of \'Store Clerk\' from Closed to Open.', '', 1, '2026-07-07 16:57:27'),
(53, 'Job Status Changed', 'Changed status of \'Store Clerk\' from On Hold to Open.', '', 1, '2026-07-07 16:57:32'),
(54, 'New Job Posting', 'Position \'efwqefwef\' has been created.', '', 1, '2026-07-08 14:20:30'),
(55, 'Job Posting Deleted', 'Position \'Store Clerk\' has been removed from the system.', '', 0, '2026-07-10 10:18:17'),
(56, 'Job Posting Deleted', 'Position \'Store Clerk\' has been removed from the system.', '', 0, '2026-07-10 10:18:34'),
(57, 'Job Posting Deleted', 'Position \'efwqefwef\' has been removed from the system.', '', 0, '2026-07-10 10:18:36'),
(58, 'New Job Posting', 'Position \'Sales Supervisor\' has been created.', '', 0, '2026-07-10 16:18:48');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Completed','Pending','Voided') DEFAULT 'Completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `cashier_id`, `subtotal`, `tax`, `total`, `status`, `created_at`) VALUES
(1, 2, 280.25, 33.63, 313.88, 'Completed', '2026-06-28 10:16:06'),
(2, 2, 433.00, 51.96, 484.96, 'Completed', '2026-06-28 10:53:27'),
(3, 2, 395.75, 47.49, 443.24, 'Completed', '2026-06-28 14:39:55'),
(4, 2, 123.75, 14.85, 138.60, 'Voided', '2026-06-29 01:28:53'),
(5, 2, 27.50, 3.30, 30.80, 'Completed', '2026-06-29 03:44:09');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `selling_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `basic_salary` decimal(10,2) DEFAULT 0.00,
  `days_worked` decimal(5,2) DEFAULT 0.00,
  `overtime_pay` decimal(10,2) DEFAULT 0.00,
  `gross_pay` decimal(10,2) DEFAULT 0.00,
  `sss` decimal(10,2) DEFAULT 0.00,
  `philhealth` decimal(10,2) DEFAULT 0.00,
  `pagibig` decimal(10,2) DEFAULT 0.00,
  `withholding_tax` decimal(10,2) DEFAULT 0.00,
  `other_deductions` decimal(10,2) DEFAULT 0.00,
  `deduction_notes` text DEFAULT NULL,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) DEFAULT 0.00,
  `status` enum('Draft','Approved','Paid') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`payroll_id`, `period_id`, `employee_id`, `basic_salary`, `days_worked`, `overtime_pay`, `gross_pay`, `sss`, `philhealth`, `pagibig`, `withholding_tax`, `other_deductions`, `deduction_notes`, `total_deductions`, `net_pay`, `status`, `created_at`) VALUES
(3, 3, 7, 300000.00, 13.00, 3605.77, 153605.77, 900.00, 1250.00, 100.00, 72866.67, 0.00, 'cqccqc', 75116.67, 78489.10, 'Draft', '2026-07-11 15:36:54');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `period_id` int(11) NOT NULL,
  `period_name` varchar(100) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `pay_date` date DEFAULT NULL,
  `status` enum('Draft','For Approval','Approved','Paid') DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_periods`
--

INSERT INTO `payroll_periods` (`period_id`, `period_name`, `date_from`, `date_to`, `pay_date`, `status`, `created_by`, `created_at`) VALUES
(3, 'July 11- 20', '2026-07-11', '2026-07-19', '2026-07-30', 'Approved', 1, '2026-07-11 15:36:17');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `position_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_name` varchar(100) NOT NULL,
  `employment_type` enum('Full-time','Part-time','Contractual','Probationary') NOT NULL,
  `slots` int(11) DEFAULT 1,
  `salary_min` decimal(10,2) DEFAULT 0.00,
  `salary_max` decimal(10,2) DEFAULT 0.00,
  `requirements` text DEFAULT NULL,
  `status` enum('Open','Closed','On Hold') DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`position_id`, `department_id`, `position_name`, `employment_type`, `slots`, `salary_min`, `salary_max`, `requirements`, `status`, `created_at`) VALUES
(4, 2, 'Sales Supervisor', 'Full-time', 1, 100.00, 500.00, 'wqdqwd', 'Open', '2026-07-10 16:18:48'),
(6, NULL, 'Cashier', 'Full-time', 10, 15000.00, 20000.00, '', 'Open', '2026-07-11 13:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('Available','Unavailable') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `added_by` int(11) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category_id`, `product_name`, `barcode`, `description`, `selling_price`, `cost_price`, `image`, `status`, `created_at`, `updated_at`, `added_by`, `deleted_at`, `deleted_reason`) VALUES
(1, 2, 'Coke 1.5L', 'coke15l', '1.5 Liters of Coca-Cola', 65.25, 50.00, NULL, 'Available', '2026-06-29 03:58:41', '2026-06-29 11:58:41', 1, NULL, NULL),
(2, 5, 'Liquid Detergent', 'liqdet', 'Premium liquid detergent', 35.00, 25.00, NULL, 'Available', '2026-06-29 03:58:41', '2026-06-29 11:58:41', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `resignations`
--

CREATE TABLE `resignations` (
  `resignation_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `resignation_type` enum('Voluntary','Constructive','Mutual Agreement') NOT NULL DEFAULT 'Voluntary',
  `date_filed` date NOT NULL,
  `last_day` date NOT NULL,
  `reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('Pending','Acknowledged','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Completed','Pending','Voided') DEFAULT 'Completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `cashier_id`, `total_amount`, `payment`, `change_amount`, `status`, `created_at`) VALUES
(6, 1, 313.88, 313.88, 0.00, 'Completed', '2026-06-28 11:15:48'),
(7, 1, 484.96, 484.96, 0.00, 'Completed', '2026-06-28 11:16:29'),
(8, 3, 443.24, 443.24, 0.00, 'Completed', '2026-06-28 14:42:47'),
(9, 1, 82.50, 150.00, 67.50, 'Completed', '2026-06-29 03:05:16'),
(10, 1, 30.80, 30.80, 0.00, 'Completed', '2026-06-29 03:44:20');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `gmail` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('Admin','Cashier','Customer') DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `gmail`, `password`, `full_name`, `role`, `status`, `created_at`, `last_login`) VALUES
(1, 'admin1234@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'Admin', 'Active', '2026-06-25 16:46:12', '2026-07-11 21:35:56'),
(2, 'johnmmartinez814@gmail.com', '$2y$10$RluONkR79g6khwsHWvcFUO650fqbIL.TV8Qa34ArDoYD.A3AM2wmW', 'John Mark P Martinez', '', 'Active', '2026-06-27 15:38:25', '2026-06-29 11:43:59'),
(3, 'Jmark14@gmail.com', '$2y$10$LqRhmirUz6m33Uz6c5eYe.Q4iqpTOxK8UWBeZwN.TY86cijAM2S9e', 'Jeyem', 'Cashier', 'Active', '2026-06-27 16:33:09', '2026-06-29 09:25:09'),
(9, 'nenelviray@gmail.com', '$2y$10$cqjG.liYUctcAbNOO3IWoOY0Bb9/cm9e8OlqM.VZ3ovcCqwQwQ0w.', 'shannel', 'Customer', 'Active', '2026-06-29 03:28:17', NULL),
(16, 'sushiramenthis1@gmail.com', '$2y$10$h90nTiUTayILjwptv612T.zXUDbPuaDmbOqZIT/kItSPNL//rnXpm', 'Sushi', '', 'Active', '2026-07-12 10:29:48', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`applicant_id`),
  ADD KEY `fk_applicant_position` (`position_id`);

--
-- Indexes for table `applicants_archive`
--
ALTER TABLE `applicants_archive`
  ADD PRIMARY KEY (`archive_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `fk_att_employee` (`employee_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_audit_user` (`user_id`);

--
-- Indexes for table `blocked_registrations`
--
ALTER TABLE `blocked_registrations`
  ADD PRIMARY KEY (`gmail`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_item_id`),
  ADD KEY `idx_cart_id` (`cart_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_no` (`employee_no`),
  ADD KEY `fk_emp_position` (`position_id`),
  ADD KEY `fk_emp_department` (`department_id`);

--
-- Indexes for table `employees_archive`
--
ALTER TABLE `employees_archive`
  ADD PRIMARY KEY (`archive_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `product_id` (`product_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `fk_leave_employee` (`employee_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_orders_cashier` (`cashier_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `fk_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_product` (`product_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`payroll_id`),
  ADD KEY `fk_payroll_period` (`period_id`),
  ADD KEY `fk_payroll_employee` (`employee_id`);

--
-- Indexes for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`period_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`position_id`),
  ADD KEY `fk_position_dept` (`department_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD UNIQUE KEY `barcode_2` (`barcode`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `resignations`
--
ALTER TABLE `resignations`
  ADD PRIMARY KEY (`resignation_id`),
  ADD KEY `fk_resign_employee` (`employee_id`),
  ADD KEY `fk_resign_processed_by` (`processed_by`),
  ADD KEY `fk_resign_created_by` (`created_by`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `fk_sales_cashier` (`cashier_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`sale_item_id`),
  ADD KEY `fk_items_sale` (`sale_id`),
  ADD KEY `fk_items_product` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `applicant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `applicants_archive`
--
ALTER TABLE `applicants_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=238;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employees_archive`
--
ALTER TABLE `employees_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `period_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `position_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `resignations`
--
ALTER TABLE `resignations`
  MODIFY `resignation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `sale_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `fk_applicant_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`position_id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_att_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`cart_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_emp_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`position_id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `fk_payroll_period` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`period_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `resignations`
--
ALTER TABLE `resignations`
  ADD CONSTRAINT `fk_resign_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_resign_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_resign_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
