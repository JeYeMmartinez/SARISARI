-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 28, 2026 at 12:54 PM
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
(74, 2, 'Create', 'orders', 2, 'Order #2 placed — Subtotal: ₱433.00 | Tax: ₱51.96 | Total: ₱484.96', '2026-06-28 10:53:27');

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
(1, 2, 484.96, 0.00, 0.00, 'Pending', '2026-06-28 10:53:27');

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
(2, 1, 13, 4, 65.25, 261.00);

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
(1, 5, 9, 5, 99, '2', '2026-06-26 19:34:46', '2026-06-28 18:16:06'),
(2, 6, 0, 5, 100, NULL, NULL, '2026-06-26 19:20:22'),
(4, 7, 1, 5, 100, '23', '2026-06-26 23:30:34', '2026-06-26 23:30:34'),
(8, 13, 48, 5, 100, 'Aisle 1', NULL, '2026-06-28 18:16:06'),
(9, 14, 30, 5, 100, 'Aisle 2', NULL, '2026-06-27 23:14:28');

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
(4, 'Sale Completed', 'Sale #4 processed successfully — Total: ₱172.00', 'Sales', 1, '2026-06-28 02:12:28');

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
(1, 2, 280.25, 33.63, 313.88, 'Pending', '2026-06-28 10:16:06'),
(2, 2, 433.00, 51.96, 484.96, 'Pending', '2026-06-28 10:53:27');

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
(4, 2, 13, 'Coke 1.5L', 4, 65.25, 261.00);

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
(5, 1, 'Argentina Corn Beef Large', '2222', 'Large ', 43.00, 33.00, NULL, 'Available', '2026-06-25 16:52:09', '2026-06-26 00:52:44', 1, NULL, NULL),
(6, 1, 'Century Tuna Spicy', '4313', 'qfdqfqf', 44.00, 22.00, NULL, 'Unavailable', '2026-06-25 16:53:45', '2026-06-28 00:43:12', 1, NULL, NULL),
(7, 4, 'Rexona Small', '2223', '', 165.00, 120.00, NULL, 'Unavailable', '2026-06-26 12:24:52', '2026-06-28 00:46:02', 1, NULL, NULL),
(13, 2, 'Coke 1.5L', 'coke15l', '1.5 Liters of Coca-Cola', 65.25, 50.00, NULL, 'Available', '2026-06-27 15:14:28', '2026-06-27 23:14:28', 1, NULL, NULL),
(14, 5, 'Liquid Detergent', 'liqdet', 'Premium liquid detergent', 35.00, 25.00, NULL, 'Unavailable', '2026-06-27 15:14:28', '2026-06-28 00:49:16', 1, '2026-06-28 00:49:16', 'sadasd');

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
(1, 'admin1234@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'Admin', 'Active', '2026-06-25 16:46:12', '2026-06-28 18:17:45'),
(2, 'Johnmmartinez814@Gmail.com', '$2y$10$1Ue0aT97dYpEq0ybiv1Rk.Ksejrr1xqHBMiCrRWaIAgRAy/dia5oK', 'John Mark Martinez', 'Customer', 'Active', '2026-06-27 15:38:25', '2026-06-28 18:15:20'),
(3, 'Jmark14@gmail.com', '$2y$10$LqRhmirUz6m33Uz6c5eYe.Q4iqpTOxK8UWBeZwN.TY86cijAM2S9e', 'Jeyem', 'Cashier', 'Active', '2026-06-27 16:33:09', '2026-06-28 15:45:41');

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `sale_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
