-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 18, 2025 at 04:48 PM
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
-- Database: `notes_app`
--
--DROP DATABASE IF EXISTS `notes_app`;
--CREATE DATABASE IF NOT EXISTS `notes_app` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
--USE `notes_app`;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `global_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT '#ffffff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pinned` tinyint(1) NOT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`global_id`, `user_id`, `item_id`, `name`, `content`, `image`, `color`, `created_at`, `updated_at`, `pinned`, `password`) VALUES
(1, 1, 1, 'Note 1', 'Content of note 1', 'uploads/images/img_6829f2067bb041.54380139.jpg', '#ffffff', '2025-05-18 14:42:58', '2025-05-18 14:43:57', 1, NULL),
(2, 1, 2, 'New Untitled Note (Click to change title)', 'My 2nd Note', 'uploads/images/img_6829f227ecabf4.03304342.png', '#fed7d7', '2025-05-18 14:43:37', '2025-05-18 14:43:55', 0, NULL),
(3, 2, 1, 'My Private note', 'Hehe', 'uploads/images/img_6829f2983b3341.55275019.jpg', '#f4bdff', '2025-05-18 14:45:14', '2025-05-18 14:46:09', 0, '$2y$10$cYXTKfwk3SfQKsV2qgnyXehKM4sooByTwHP6z1syKPdVOjIIWDTdW'),
(4, 2, 2, 'New Untitled Note (Click to change title)', '', NULL, '#ffffff', '2025-05-18 14:46:27', '2025-05-18 14:46:27', 0, NULL);

--
-- Triggers `items`
--
DELIMITER $$
CREATE TRIGGER `before_insert_notes` BEFORE INSERT ON `items` FOR EACH ROW BEGIN
    DECLARE max_item_id INT;

    SELECT IFNULL(MAX(item_id), 0) + 1 INTO max_item_id
    FROM items
    WHERE user_id = NEW.user_id;

    SET NEW.item_id = max_item_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `labels`
--

CREATE TABLE `labels` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `labels`
--

INSERT INTO `labels` (`id`, `user_id`, `name`, `created_at`) VALUES
(1, 1, 'Label 1', '2025-05-18 14:43:25'),
(2, 1, 'Second Label', '2025-05-18 14:43:30'),
(3, 2, 'Secret', '2025-05-18 14:45:51');

-- --------------------------------------------------------

--
-- Table structure for table `note_labels`
--

CREATE TABLE `note_labels` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `label_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `note_labels`
--

INSERT INTO `note_labels` (`id`, `note_id`, `label_id`) VALUES
(2, 1, 1),
(4, 2, 2),
(7, 3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `shared_notes`
--

CREATE TABLE `shared_notes` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `item_global_id` int(11) DEFAULT NULL,
  `shared_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shared_notes`
--

INSERT INTO `shared_notes` (`id`, `sender_id`, `receiver_id`, `item_global_id`, `shared_at`) VALUES
(1, 2, 1, 3, '2025-05-18 14:46:24'),
(2, 2, 1, 4, '2025-05-18 14:46:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `token_auth` varchar(255) DEFAULT NULL,
  `authenticated` tinyint(1) NOT NULL DEFAULT 0,
  `token_reset` varchar(255) DEFAULT NULL,
  `expiration_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `avatar`, `password`, `created_at`, `token_auth`, `authenticated`, `token_reset`, `expiration_time`) VALUES
(1, 'admin', 'admin@gmail.com', NULL, '$2y$10$j15wtfFvmd2Bso23b0QuheGOSQ9l0AYct8SY8ue6tBo1/WDacBrpy', '2025-05-18 14:42:08', '', 1, NULL, NULL),
(2, 'AvgUser', 'averageuser@gmail.com', NULL, '$2y$10$XyPSXJ36Y0sUH.6eaavRQuNyWmIfMiH5eiTbe/WU8JCQxjdWLOk.K', '2025-05-18 14:45:04', '2a295afdc6f9446bc0afab07ec39fafa', 0, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`global_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `labels`
--
ALTER TABLE `labels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `note_labels`
--
ALTER TABLE `note_labels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_id` (`note_id`),
  ADD KEY `label_id` (`label_id`);

--
-- Indexes for table `shared_notes`
--
ALTER TABLE `shared_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `item_global_id` (`item_global_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `global_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `labels`
--
ALTER TABLE `labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `note_labels`
--
ALTER TABLE `note_labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `shared_notes`
--
ALTER TABLE `shared_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `labels`
--
ALTER TABLE `labels`
  ADD CONSTRAINT `labels_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `note_labels`
--
ALTER TABLE `note_labels`
  ADD CONSTRAINT `note_labels_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `items` (`global_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `note_labels_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_notes`
--
ALTER TABLE `shared_notes`
  ADD CONSTRAINT `shared_notes_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `items` (`user_id`),
  ADD CONSTRAINT `shared_notes_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `shared_notes_ibfk_3` FOREIGN KEY (`item_global_id`) REFERENCES `items` (`global_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
