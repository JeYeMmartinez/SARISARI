-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 29, 2026 at 05:49 AM
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
(17, 2, 'C2', '4225689745555', 'Green Tea \r\n230ml', 18.75, 15.00, 'prod_6a41da146fd2b.jpg', 'Available', '2026-06-29 02:36:04', '2026-06-29 10:36:04', 1, NULL, NULL),
(18, 2, 'Choc-O', '7878945454121', '250 ml\r\nchocolate milk drink', 25.00, 20.00, 'prod_6a41dc67e778d.jpg', 'Available', '2026-06-29 02:45:59', '2026-06-29 10:45:59', 1, NULL, NULL),
(19, 2, 'Royal', '8964731659844', 'Orange Flavored\r\n320ml', 25.00, 20.00, 'prod_6a41e40af3ef1.jpg', 'Unavailable', '2026-06-29 03:18:34', '2026-06-29 11:19:27', 1, '2026-06-29 11:19:27', '...'),
(20, 2, 'Royal', '1235442141111', 'Orange Flavored\r\n320ml', 25.00, 20.00, 'prod_6a41e485305b3.jpg', 'Available', '2026-06-29 03:20:37', '2026-06-29 11:20:37', 1, NULL, NULL),
(21, 2, 'Zesto', '1235485285554', 'Orange Drink\r\n250ml', 18.75, 15.00, 'prod_6a41e4dd0e0fb.jpg', 'Available', '2026-06-29 03:22:05', '2026-06-29 11:22:05', 1, NULL, NULL),
(22, 2, 'Coca-Cola', '4599756312512', '500ml', 27.50, 22.00, 'prod_6a41e52b882dd.jpg', 'Available', '2026-06-29 03:23:23', '2026-06-29 11:23:23', 1, NULL, NULL),
(23, 5, 'Joy', '7896541235755', 'Kalamansi', 37.50, 30.00, 'prod_6a41e55fe65f9.jpg', 'Available', '2026-06-29 03:24:15', '2026-06-29 11:24:15', 1, NULL, NULL),
(24, 5, 'Zonrox', '3579541222224', 'Floral Scent\r\n500ml', 37.50, 30.00, 'prod_6a41e58d7481c.jpg', 'Available', '2026-06-29 03:25:01', '2026-06-29 11:25:01', 1, NULL, NULL),
(25, 5, 'Bathroom Tissue', '5468754313121', '4 Rolls\r\n2 Ply', 108.75, 87.00, 'prod_6a41e5d048a0d.jpg', 'Available', '2026-06-29 03:26:08', '2026-06-29 11:26:08', 1, NULL, NULL),
(26, 5, 'Ethyl Alcohol', '7845857236922', '70% Solution', 52.50, 42.00, 'prod_6a41e6195f350.jpg', 'Available', '2026-06-29 03:27:21', '2026-06-29 11:27:21', 1, NULL, NULL),
(27, 5, 'Dishwashing Sponge', '5236547987987', '1 Piece', 18.75, 15.00, 'prod_6a41e65070cac.jpg', 'Available', '2026-06-29 03:28:16', '2026-06-29 11:28:16', 1, NULL, NULL),
(28, 4, 'Charmee Pads', '4587413695552', 'With Wings\r\n8+11 Free Pad', 31.25, 25.00, 'prod_6a41e690535c7.jpg', 'Available', '2026-06-29 03:29:20', '2026-06-29 11:29:20', 1, NULL, NULL),
(29, 4, 'Sanicare Cotton Buds', '7514896325444', '108 Pieces', 45.00, 36.00, 'prod_6a41e6caf1140.jpg', 'Available', '2026-06-29 03:30:18', '2026-06-29 11:30:18', 1, NULL, NULL),
(30, 4, 'Head & Shoulders', '3265741894444', '250ml', 81.25, 65.00, 'prod_6a41e6f9317ff.jpg', 'Available', '2026-06-29 03:31:05', '2026-06-29 11:31:05', 1, NULL, NULL),
(31, 4, 'Dove Bar Soap', '6521489652314', '90g', 62.50, 50.00, 'prod_6a41e726e65c6.jpg', 'Available', '2026-06-29 03:31:50', '2026-06-29 11:31:50', 1, NULL, NULL),
(32, 4, 'Sunscreen', '4556546548798', '50+ SPF\r\n90ml', 100.00, 80.00, 'prod_6a41e75f61b70.jpg', 'Available', '2026-06-29 03:32:47', '2026-06-29 11:32:47', 1, NULL, NULL),
(33, 3, 'Cadbury', '1321564876546', '160g\r\nmilk chocolate', 62.50, 50.00, 'prod_6a41e828af3d7.jpg', 'Available', '2026-06-29 03:36:08', '2026-06-29 11:36:08', 1, NULL, NULL),
(34, 3, 'Trolli', '1425757532456', '142g', 75.00, 60.00, 'prod_6a41e85688742.jpg', 'Available', '2026-06-29 03:36:54', '2026-06-29 11:36:54', 1, NULL, NULL),
(35, 3, 'Pringles', '5687535423542', 'Sour & Cream Flavor', 87.50, 70.00, 'prod_6a41e88678e58.jpg', 'Available', '2026-06-29 03:37:42', '2026-06-29 11:37:42', 1, NULL, NULL),
(36, 3, 'Selecta Ice Cream', '3257418979879', 'Cookies and cream + Double Dutch', 111.25, 89.00, 'prod_6a41e8baa942c.jpg', 'Available', '2026-06-29 03:38:34', '2026-06-29 11:38:34', 1, NULL, NULL),
(37, 3, 'Piattos', '2135468654687', 'Cheese Flavor', 18.75, 15.00, 'prod_6a41e8dc3cee5.jpg', 'Available', '2026-06-29 03:39:08', '2026-06-29 11:39:08', 1, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `fk_product_category` (`category_id`),
  ADD KEY `fk_product_user` (`added_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_product_user` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
