-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2026 at 12:45 AM
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
-- Database: `shop_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'registered', 'Role: buyer', '2026-05-08 02:00:53'),
(2, 2, 'registered', 'Role: seller', '2026-05-08 02:01:00'),
(3, 1, 'login', 'Logged in', '2026-05-08 02:01:04'),
(4, 1, 'logout', 'Logged out', '2026-05-08 02:01:06'),
(5, 2, 'login', 'Logged in', '2026-05-08 02:01:11'),
(6, 2, 'added_product', 'Wireless Mouse (ID 4)', '2026-05-08 02:01:28'),
(7, 2, 'logout', 'Logged out', '2026-05-08 02:01:32'),
(8, 1, 'login', 'Logged in', '2026-05-08 02:01:37'),
(9, 1, 'reviewed_product', 'Product #4 — 5 stars', '2026-05-08 02:01:47'),
(10, 1, 'placed_order', 'Order #1 for Wireless Mouse x1', '2026-05-08 02:02:15'),
(11, 1, 'logout', 'Logged out', '2026-05-08 02:02:20'),
(12, 2, 'login', 'Logged in', '2026-05-08 02:02:24'),
(13, 2, 'updated_order_status', 'Order #1 → processing', '2026-05-08 02:02:43'),
(14, 2, 'updated_order_status', 'Order #1 → processing', '2026-05-08 02:03:32'),
(15, 2, 'logout', 'Logged out', '2026-05-08 02:03:39'),
(16, 1, 'login', 'Logged in', '2026-05-08 02:03:43'),
(17, 1, 'placed_order', 'Order #2 for Wireless Mouse x1', '2026-05-08 02:03:48'),
(18, 1, 'login', 'Logged in', '2026-05-08 10:38:43'),
(19, 1, 'placed_order', 'Order #3 for Wireless Mouse x1', '2026-05-08 10:38:51'),
(20, 1, 'logout', 'Logged out', '2026-05-08 10:39:04'),
(21, 2, 'login', 'Logged in', '2026-05-08 10:39:09'),
(22, 2, 'updated_order_status', 'Order #1 → processing', '2026-05-08 10:39:21'),
(23, 2, 'logout', 'Logged out', '2026-05-08 10:39:49'),
(24, 1, 'login', 'Logged in', '2026-05-08 10:39:52'),
(25, 2, 'login', 'Logged in', '2026-05-11 12:04:46'),
(26, 2, 'updated_order_status', 'Order #2 → shipped', '2026-05-11 12:05:14'),
(27, 2, 'updated_order_status', 'Order #3 → pending', '2026-05-11 12:07:01'),
(28, 2, 'logout', 'Logged out', '2026-05-11 12:07:04'),
(29, 1, 'login', 'Logged in', '2026-05-11 12:07:06'),
(30, 1, 'logout', 'Logged out', '2026-05-11 12:07:18'),
(31, 3, 'registered', 'Role: buyer', '2026-05-11 12:07:24'),
(32, 3, 'login', 'Logged in', '2026-05-11 12:07:28'),
(33, 3, 'logout', 'Logged out', '2026-05-11 12:16:31'),
(34, 1, 'login', 'Logged in', '2026-05-11 12:26:22'),
(35, 1, 'logout', 'Logged out', '2026-05-11 12:29:07'),
(36, 2, 'login', 'Logged in', '2026-05-11 12:29:10'),
(37, 2, 'updated_order_status', 'Order #2 → shipped', '2026-05-11 12:43:36'),
(38, 2, 'updated_order_status', 'Order #1 → delivered', '2026-05-11 12:43:40'),
(39, 2, 'updated_order_status', 'Order #1 → delivered', '2026-05-11 12:43:44'),
(40, 2, 'updated_order_status', 'Order #3 → pending', '2026-05-11 12:43:52'),
(41, 2, 'updated_order_status', 'Order #3 → processing', '2026-05-11 12:43:56'),
(42, 2, 'logout', 'Logged out', '2026-05-11 22:26:44'),
(43, 1, 'login', 'Logged in', '2026-05-11 22:26:47'),
(44, 1, 'requested_cancellation', 'Order #3 — Reason: better price', '2026-05-11 22:28:10'),
(45, 1, 'logout', 'Logged out', '2026-05-11 22:28:14'),
(46, 2, 'login', 'Logged in', '2026-05-11 22:28:17'),
(47, 2, 'rejected_cancellation', 'Order #3', '2026-05-11 22:31:14'),
(48, 2, 'logout', 'Logged out', '2026-05-11 22:31:23'),
(49, 1, 'login', 'Logged in', '2026-05-11 22:31:26'),
(50, 1, 'logout', 'Logged out', '2026-05-11 22:31:50'),
(51, 2, 'login', 'Logged in', '2026-05-11 22:31:53'),
(52, 2, 'added_product', 'Keyboard (ID 5)', '2026-05-11 22:32:15'),
(53, 2, 'logout', 'Logged out', '2026-05-11 22:32:18'),
(54, 1, 'login', 'Logged in', '2026-05-11 22:32:21'),
(55, 1, 'reviewed_product', 'Product #5 — 5 stars', '2026-05-11 22:32:28'),
(56, 1, 'placed_order', 'Order #4 for Keyboard x1', '2026-05-11 22:32:33'),
(57, 1, 'logout', 'Logged out', '2026-05-11 22:33:27'),
(58, 2, 'login', 'Logged in', '2026-05-11 22:33:30'),
(59, 2, 'updated_order_status', 'Order #2 → shipped', '2026-05-11 22:33:36'),
(60, 2, 'updated_order_status', 'Order #3 → processing', '2026-05-11 22:33:38'),
(61, 2, 'logout', 'Logged out', '2026-05-11 22:36:20'),
(62, 1, 'login', 'Logged in', '2026-05-11 22:36:22'),
(63, 1, 'logout', 'Logged out', '2026-05-11 22:36:38'),
(64, 2, 'login', 'Logged in', '2026-05-11 22:36:41'),
(65, 2, 'edited_product', 'Keyboard (ID 5)', '2026-05-11 22:36:51'),
(66, 2, 'logout', 'Logged out', '2026-05-11 22:37:28'),
(67, 1, 'login', 'Logged in', '2026-05-11 22:37:30'),
(68, 1, 'logout', 'Logged out', '2026-05-11 22:37:39'),
(69, 2, 'login', 'Logged in', '2026-05-11 22:37:41'),
(70, 2, 'added_product', 'R75 75% Wireless Gaming Keyboard (QMK/VIA) (ID 6)', '2026-05-11 22:39:26'),
(71, 2, 'logout', 'Logged out', '2026-05-11 22:39:34'),
(72, 1, 'login', 'Logged in', '2026-05-11 22:39:37'),
(73, 1, 'placed_order', 'Order #5 — R75 75% Wireless Gaming Keyboard (QMK/VIA) x1', '2026-05-11 22:39:59'),
(74, 1, 'requested_cancellation', 'Order #4 — Reason: hello', '2026-05-11 22:40:33'),
(75, 1, 'logout', 'Logged out', '2026-05-11 22:40:38'),
(76, 2, 'login', 'Logged in', '2026-05-11 22:40:41'),
(77, 2, 'rejected_cancellation', 'Order #4', '2026-05-11 22:41:05'),
(78, 2, 'updated_order_status', 'Order #4 → cancelled', '2026-05-11 22:41:27'),
(79, 2, 'logout', 'Logged out', '2026-05-11 22:41:51'),
(80, 1, 'login', 'Logged in', '2026-05-11 22:41:53'),
(81, 1, 'placed_order', 'Order #6 — Wireless Mouse x1', '2026-05-11 22:42:03'),
(82, 1, 'logout', 'Logged out', '2026-05-11 22:42:10'),
(83, 2, 'login', 'Logged in', '2026-05-11 22:42:13'),
(84, 2, 'updated_order_status', 'Order #6 → cancelled', '2026-05-11 22:42:39'),
(85, 2, 'cancelled_order', 'Order #5 — stock restored', '2026-05-11 22:44:32'),
(86, 2, 'cancelled_order', 'Order #2 — stock restored', '2026-05-11 22:44:53');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `cancel_requested` tinyint(1) NOT NULL DEFAULT 0,
  `cancellation_reason` text DEFAULT NULL,
  `cancel_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `buyer_id`, `phone`, `address`, `total_amount`, `status`, `cancel_requested`, `cancellation_reason`, `cancel_status`, `created_at`) VALUES
(1, 1, '095426523541', 'phase 2 vts bugo, cagayan de oro city', 100.00, 'delivered', 0, NULL, 'none', '2026-05-08 02:02:15'),
(2, 1, '095426523541', 'phase 2 vts bugo, cagayan de oro city', 100.00, 'cancelled', 0, NULL, 'none', '2026-05-08 02:03:48'),
(3, 1, '095426523541', 'phase 2 vts bugo, cagayan de oro city', 100.00, 'processing', 0, 'better price', 'rejected', '2026-05-08 10:38:51'),
(4, 1, '095426523541', 'phase 2 vts bugo, cagayan de oro city', 250.00, 'cancelled', 0, 'hello', 'rejected', '2026-05-11 22:32:33'),
(5, 1, '095426523541', 'phase 2 vts bugo, cagayan de oro city', 6560.50, 'cancelled', 0, NULL, 'none', '2026-05-11 22:39:59'),
(6, 1, '095426523541', 'phase 2 vts bugo, cagayan de oro city', 100.00, 'cancelled', 0, NULL, 'none', '2026-05-11 22:42:03');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `seller_id`, `quantity`, `price`) VALUES
(1, 1, 4, 2, 1, 100.00),
(2, 2, 4, 2, 1, 100.00),
(3, 3, 4, 2, 1, 100.00),
(4, 4, 5, 2, 1, 250.00),
(5, 5, 6, 2, 1, 6560.50),
(6, 6, 4, 2, 1, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `name`, `description`, `price`, `stock`, `created_at`) VALUES
(4, 2, 'Wireless Mouse', 'blablabla', 100.00, 7, '2026-05-08 02:01:28'),
(5, 2, 'Keyboard', '', 250.00, 500, '2026-05-11 22:32:15'),
(6, 2, 'R75 75% Wireless Gaming Keyboard (QMK/VIA)', 'Gasket Mount 75% Wireless Keyboard\r\n4000mAh built-in battery\r\nTri-Mode connectivity\r\nCherry Profile PBT Keycaps\r\n5-Layer Noise Dampening\r\nRemovable Metal Volume Knob\r\nHot Swappable Linear Cream Switch\r\nSupports QMK/VIA Programming\r\nCompatible with Windows/MacOS/Linux', 6560.50, 10, '2026-05-11 22:39:26');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `buyer_id`, `rating`, `comment`, `created_at`) VALUES
(1, 4, 1, 5, 'dfgd', '2026-05-08 02:01:47'),
(2, 5, 1, 5, '', '2026-05-11 22:32:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('buyer','seller') NOT NULL DEFAULT 'buyer',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `phone`, `address`, `created_at`) VALUES
(1, 'EuniceDael', '$2y$10$wOOWMPfuXB7jebuFHoEuy.FyOXYsVtYTuAxLb08Ux.uFsQE0tFcP.', 'buyer', '095426523541', 'phase 2 vts bugo, cagayan de oro city', '2026-05-08 02:00:52'),
(2, 'Eunice', '$2y$10$Livp3UxC11xGOfkGnjK1x.8u/ECh1.7LhCwb9lLQ2UoGd.UE.kjGW', 'seller', NULL, NULL, '2026-05-08 02:01:00'),
(3, 'EuniceDael2', '$2y$10$iGBcKvwJF37/agIBh1srxe2NXh4DGzLH5Mn1YAQ1eDwVjacKLeyy6', 'buyer', NULL, NULL, '2026-05-11 12:07:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_review` (`product_id`,`buyer_id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
