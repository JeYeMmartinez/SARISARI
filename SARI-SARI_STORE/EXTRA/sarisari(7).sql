-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 29, 2026 at 05:48 AM
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
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('Create','Update','Delete','Login','Logout','Void') NOT NULL,
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
(132, 0, 'Create', 'users', 0, 'Registered & Verified new customer: jeyem', '2026-06-29 03:33:22'),
(134, 1, 'Create', 'products', 18, 'Added product: asdasdasd11 (initial stock: 10)', '2026-06-29 03:43:43'),
(135, 2, 'Login', 'users', 2, 'John Mark Martinez logged in', '2026-06-29 03:43:59'),
(136, 2, '', 'orders', 5, 'Order #5 placed — Total: ₱30.80 (inventory reserved)', '2026-06-29 03:44:09'),
(137, 1, 'Create', 'sales', 10, 'Approved order #5 for John Mark Martinez → Sale #10 created — Note: asdasd', '2026-06-29 03:44:20');

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

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`cart_item_id`, `cart_id`, `product_id`, `quantity`, `selling_price`, `subtotal`) VALUES
(1, 1, 5, 4, 43.00, 172.00),
(2, 1, 13, 4, 65.25, 261.00),
(3, 2, 15, 10, 20.00, 200.00),
(4, 2, 13, 3, 65.25, 195.75);

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
(1, 5, 0, 5, 99, '2', '2026-06-26 19:34:46', '2026-06-28 19:16:29'),
(2, 6, 0, 5, 100, NULL, NULL, '2026-06-26 19:20:22'),
(4, 7, 1, 5, 100, '23', '2026-06-26 23:30:34', '2026-06-26 23:30:34'),
(8, 13, 40, 5, 100, 'Aisle 1', NULL, '2026-06-28 22:42:47'),
(9, 14, 30, 5, 100, 'Aisle 2', NULL, '2026-06-27 23:14:28'),
(10, 15, 0, 5, NULL, NULL, '2026-06-28 22:31:58', '2026-06-28 22:42:47'),
(11, 16, 40, 5, NULL, NULL, '2026-06-29 01:10:10', '2026-06-29 01:10:10'),
(12, 17, 0, 5, NULL, NULL, '2026-06-29 02:16:16', '2026-06-29 11:05:16'),
(13, 18, 8, 5, NULL, NULL, '2026-06-29 11:43:43', '2026-06-29 11:44:20');

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
(19, 'Product Added', 'New product added: PRODUCT1', '', 0, '2026-06-28 18:16:16'),
(20, 'New Order', 'Order #4 from John Mark Martinez — ₱138.60 (inventory reserved)', 'Approval', 0, '2026-06-29 01:28:53'),
(21, 'Product Added', 'New product added: asdasdasd11', '', 0, '2026-06-29 03:43:43'),
(22, 'New Order', 'Order #5 from John Mark Martinez — ₱30.80 (inventory reserved)', 'Approval', 0, '2026-06-29 03:44:09'),
(23, 'Order Approved', 'Order #5 for John Mark Martinez approved → Sale #10 created', 'Approval', 0, '2026-06-29 03:44:20');

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
(4, 2, 123.75, 14.85, 138.60, 'Pending', '2026-06-29 01:28:53'),
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

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `product_name`, `quantity`, `selling_price`, `subtotal`) VALUES
(1, 1, 5, 'Argentina Corn Beef Large', 5, 43.00, 215.00),
(2, 1, 13, 'Coke 1.5L', 1, 65.25, 65.25),
(3, 2, 5, 'Argentina Corn Beef Large', 4, 43.00, 172.00),
(4, 2, 13, 'Coke 1.5L', 4, 65.25, 261.00),
(5, 3, 15, 'C2', 10, 20.00, 200.00),
(6, 3, 13, 'Coke 1.5L', 3, 65.25, 195.75),
(7, 4, 17, 'PRODUCT1', 3, 41.25, 123.75),
(8, 5, 18, 'asdasdasd11', 1, 27.50, 27.50);

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
(5, 1, 'Argentina Corn Beef Large', '2222', 'Large ', 43.00, 33.00, NULL, 'Unavailable', '2026-06-25 16:52:09', '2026-06-29 01:16:00', 1, '2026-06-29 01:16:00', 'asdasd'),
(6, 1, 'Century Tuna Spicy', '4313', 'qfdqfqf', 44.00, 22.00, NULL, 'Unavailable', '2026-06-25 16:53:45', '2026-06-29 01:16:10', 1, '2026-06-29 01:16:10', 'asdasd'),
(7, 4, 'Rexona Small', '2223', '', 165.00, 120.00, NULL, 'Unavailable', '2026-06-26 12:24:52', '2026-06-29 01:16:27', 1, '2026-06-29 01:16:27', 'asdasd'),
(13, 2, 'Coke 1.5L', 'coke15l', '1.5 Liters of Coca-Cola', 65.25, 50.00, NULL, 'Unavailable', '2026-06-27 15:14:28', '2026-06-29 01:16:49', 1, '2026-06-29 01:16:49', 'asdasd'),
(14, 5, 'Liquid Detergent', 'liqdet', 'Premium liquid detergent', 35.00, 25.00, NULL, 'Unavailable', '2026-06-27 15:14:28', '2026-06-28 00:49:16', 1, '2026-06-28 00:49:16', 'sadasd'),
(15, 2, 'C2', '', '500ml', 20.00, 16.00, 'prod_6a41305eb8348.jpg', 'Unavailable', '2026-06-28 14:31:58', '2026-06-29 01:17:29', 1, '2026-06-29 01:17:29', 'asdasd'),
(16, 3, 'ding dong', '1121321331311', 'medium', 18.75, 15.00, NULL, 'Unavailable', '2026-06-28 17:10:10', '2026-06-29 01:17:03', 1, '2026-06-29 01:17:03', 'asdasdasd'),
(17, 1, 'PRODUCT1', '1111111111111', 'adasdsad', 41.25, 33.00, 'prod_6a4164f0a811f.jpg', 'Unavailable', '2026-06-28 18:16:16', '2026-06-29 11:05:16', 1, NULL, NULL),
(18, 1, 'asdasdasd11', '2222222222222', 'sadasdsa', 27.50, 22.00, 'prod_6a41e9ef9c41b.jpg', 'Available', '2026-06-29 03:43:43', '2026-06-29 11:43:43', 1, NULL, NULL);

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

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`sale_item_id`, `sale_id`, `product_id`, `quantity`, `selling_price`, `subtotal`) VALUES
(7, 6, 5, 5, 43.00, 215.00),
(8, 6, 13, 1, 65.25, 65.25),
(9, 7, 5, 4, 43.00, 172.00),
(10, 7, 13, 4, 65.25, 261.00),
(11, 8, 15, 10, 20.00, 200.00),
(12, 8, 13, 3, 65.25, 195.75),
(13, 9, 17, 2, 41.25, 82.50),
(14, 10, 18, 1, 27.50, 27.50);

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
(1, 'admin1234@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'Admin', 'Active', '2026-06-25 16:46:12', '2026-06-29 09:28:35'),
(2, 'Johnmmartinez814@Gmail.com', '$2y$10$5sAtXBkx1.KUKhrkuGnA..rrZ94l8MkKg75eTzmSPll1ycDhcECiS', 'John Mark Martinez', 'Customer', 'Active', '2026-06-27 15:38:25', '2026-06-29 11:43:59'),
(3, 'Jmark14@gmail.com', '$2y$10$LqRhmirUz6m33Uz6c5eYe.Q4iqpTOxK8UWBeZwN.TY86cijAM2S9e', 'Jeyem', 'Cashier', 'Active', '2026-06-27 16:33:09', '2026-06-29 09:25:09'),
(9, 'nenelviray@gmail.com', '$2y$10$cqjG.liYUctcAbNOO3IWoOY0Bb9/cm9e8OlqM.VZ3ovcCqwQwQ0w.', 'shannel', 'Customer', 'Active', '2026-06-29 03:28:17', NULL),
(12, 'jmarkmartinez14@gmail.com', '$2y$10$dSE30GYToLjYybMQWERgkuVYoggkUxsUhzHkHFGbBZ8sSqPSLuc3i', 'jeyem', 'Customer', 'Active', '2026-06-29 03:31:53', NULL),
(13, 'jmarkmartinez14@gmail.com', '$2y$10$dSE30GYToLjYybMQWERgkuVYoggkUxsUhzHkHFGbBZ8sSqPSLuc3i', 'jeyem', 'Customer', 'Active', '2026-06-29 03:33:22', NULL),
(15, 'sushiramenthis1@gmail.com', '$2y$10$OE77qBt0uT4hohunUXxyBe2lcnODQ/PnI0JP8eXuzKYR2mtOEkU/O', 'jonathan', 'Customer', 'Active', '2026-06-29 03:40:25', NULL);

--
-- Indexes for dumped tables
--

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
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `product_id` (`product_id`);

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
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `fk_product_category` (`category_id`),
  ADD KEY `fk_product_user` (`added_by`);

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
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

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
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

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
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

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
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_product_user` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`);

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
