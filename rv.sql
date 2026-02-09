-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 04, 2026 at 10:40 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rv`
--
CREATE DATABASE IF NOT EXISTS `rv` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `rv`;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_movie_list`
--

CREATE TABLE `tbl_movie_list` (
  `movie_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `overview` text DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_movie_list`
--

INSERT INTO `tbl_movie_list` (`movie_id`, `title`, `overview`, `poster_path`, `release_date`, `last_updated`, `created_at`) VALUES
(83533, 'Avatar: Fire and Ash', NULL, '/5bxrxnRaxZooBAxgUVBZ13dpzC7.jpg', NULL, '2026-02-07 09:55:18', '2026-02-07 09:55:18'),
(527969, 'Camp', NULL, '/5tmffZhi559MC3FzPi48I7UMJJR.jpg', NULL, '2026-02-09 14:25:24', '2026-02-09 14:25:24'),
(1168190, 'The Wrecking Crew', NULL, '/gbVwHl4YPSq6BcC92TQpe7qUTh6.jpg', NULL, '2026-02-07 09:55:18', '2026-02-07 09:55:18'),
(1234731, 'Anaconda', 'A group of friends facing mid-life crises head to the rainforest with the intention of remaking their favorite movie from their youth, only to find themselves in a fight for their lives against natural disasters, giant snakes and violent criminals.', '/qxMv3HwAB3XPuwNLMhVRg795Ktp.jpg', '2025-12-24', '2026-02-07 09:55:18', '2026-02-07 09:55:18'),
(1368166, 'The Housemaid', NULL, '/jHyir7bVDhZm90qxUhXHTO7BvyA.jpg', NULL, '2026-02-09 12:47:09', '2026-02-09 12:47:09');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_movie_review`
--

CREATE TABLE `tbl_movie_review` (
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `movie_title` varchar(255) DEFAULT NULL,
  `review` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_movie_review`
--

INSERT INTO `tbl_movie_review` (`review_id`, `user_id`, `movie_id`, `movie_title`, `review`, `created_at`, `rating`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 1, 83533, 'Avatar: Fire and Ash', 'Avatar: Fire and Ash delivers a visually stunning return to Pandora, blending breathtaking effects with a darker, more intense emotional tone. The film expands the world and its conflicts, exploring themes of anger, loss, and survival while maintaining James Cameron’s signature spectacle. Though the story feels familiar at times, the powerful visuals and immersive atmosphere make it a compelling and memorable cinematic experience.', '2026-01-22 12:44:57', 5, 0, NULL, NULL),
(2, 2, 1168190, 'The Wrecking Crew', 'yey', '2026-02-04 16:40:47', 5, 0, NULL, NULL),
(3, 2, 83533, 'Avatar: Fire and Ash', 'hell yeah', '2026-02-04 16:41:37', 10, 0, NULL, NULL),
(4, 8, 1168190, 'The Wrecking Crew', 'Gunning evry morning', '2026-02-05 03:26:13', 8, 0, NULL, NULL),
(5, 8, 83533, 'Avatar: Fire and Ash', 'cool movie1', '2026-02-05 01:07:06', 10, 0, NULL, NULL),
(6, 8, 1234731, 'Anaconda', 'cool movie!', '2026-02-04 23:04:51', 10, 0, NULL, NULL),
(7, 9, 1234731, 'Anaconda', 'meme movie but awesome', '2026-02-04 23:06:34', 10, 0, NULL, NULL),
(9, 13, 1168190, 'The Wrecking Crew', 'test 2', '2026-02-05 03:16:31', 5, 0, NULL, NULL),
(10, 14, 1168190, 'The Wrecking Crew', 'boop', '2026-02-07 10:06:29', 5, 1, '2026-02-07 17:58:21', 9),
(11, 8, 1368166, 'The Housemaid', 'boobs', '2026-02-09 12:47:09', 5, 0, NULL, NULL),
(12, 28, 527969, 'Camp', 'nice try guys', '2026-02-09 14:25:44', 5, 1, '2026-02-09 22:48:42', 9);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sign_in`
--

CREATE TABLE `tbl_sign_in` (
  `sign_in_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_attempts_sent` int(11) NOT NULL DEFAULT 0,
  `verification_sent_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_sign_in`
--

INSERT INTO `tbl_sign_in` (`sign_in_id`, `email`, `password`, `is_verified`, `verification_token`, `verification_attempts_sent`, `verification_sent_at`, `verified_at`, `is_admin`, `created_at`, `last_login`) VALUES
(1, 'mtmitchell@gmail.com', '$2y$10$ZbNepJeaDIt5K2XYKM2CxOiyTMwsuu3mz.p6dnDyDvkwhGpD1xMta', 1, NULL, 0, NULL, '2026-02-09 17:00:29', 0, '2026-01-22 10:53:53', NULL),
(2, 'maryaltheagrande@gmail.com', '$2y$10$6QJwC5dtIIjzwxFKQqJI9ut18Aa8GwZCvLPZnA6UpyRRAUzJiISeq', 1, NULL, 0, NULL, '2026-02-09 17:22:20', 0, '2026-02-04 16:38:42', NULL),
(3, 'samirasnoe7@gmail.com', '$2y$10$h9LEPatXm6EfygaoHfuDH.O/O26LLB/sJGEYckFpGU71e4Mdbkfu2', 1, NULL, 0, '2026-02-09 18:05:16', '2026-02-09 18:05:25', 0, '2026-02-04 22:36:01', '2026-02-09 15:39:41'),
(4, 'test@gmail.com', '$2y$10$J2be1GxmytUGdEvI2rXk0e4YY5nrdM7JdTHHJMM4OQXHDqq/1V2TG', 1, NULL, 0, NULL, '2026-02-09 17:14:05', 1, '2026-02-04 23:05:58', '2026-02-09 15:34:32'),
(5, 'grandealthea2@gmail.com', '$2y$10$QeeTR0k5malnhG8gRHilSOumMC566L7NKV4NygmZ9Clb9PvKHkRwW', 1, NULL, 0, '2026-02-09 18:25:55', '2026-02-09 18:26:03', 0, '2026-02-05 02:33:48', NULL),
(7, 'juliabautista@gmail.com', '$2y$10$FqTx5xgsKX4i95rhUDpoZe7KMcnw0nanNcF/enIKhicqhW.2r97MK', 1, NULL, 0, '2026-02-09 18:18:34', '2026-02-09 18:19:11', 0, '2026-02-05 02:44:51', NULL),
(8, 'testuser2@gmail.com', '$2y$10$xsy.gpDFYPuEllrg0BnDReZK5.wsFqsBh5vT/yKMvhCXK3SfD6me.', 1, NULL, 0, NULL, NULL, 1, '2026-02-07 09:56:47', '2026-02-09 15:32:47'),
(13, 'irapapa7@gmail.com', '$2y$10$USVwnOQnOWqeQyxvXsWqVOgxjSDmbQAZUsCtycLqIMS38Mvwh6gm.', 1, 'cc5dc680c95ae88ec8ef9749e9e16cc6', 0, '2026-02-08 13:10:36', '2026-02-09 17:13:58', 0, '2026-02-08 12:04:40', '2026-02-09 15:08:17'),
(18, 'testuser3@gmail.com', '$2y$10$2aC2hly5mOx/xb.H2Hvz6eP7kMwkz9vog8jWY7D6WelWW.m5HWGci', 1, NULL, 1, '2026-02-09 19:44:22', '2026-02-09 19:46:31', 0, '2026-02-09 11:43:56', NULL),
(19, 'testfour@gmail.com', '$2y$10$.ZsDqmcX4Bt1JhETG7e8teAA4f1ptqu38RHIeY17m8elJxkbo7aQm', 1, NULL, 2, '2026-02-09 19:47:22', '2026-02-09 19:47:28', 0, '2026-02-09 11:47:01', NULL),
(21, 'testfive@gmail.com', '$2y$10$qWIILhuckfUPDUHvagNR0uGqI/BW.BYt/m1M8JbPb/MBT3pQ4OsgS', 1, '21:28', 2, '2026-02-09 20:24:50', '2026-02-09 20:24:55', 0, '2026-02-09 11:49:47', NULL),
(22, 'test_six@gmail.com', '$2y$10$iN4Cs8QGN8A4Khvs5Oni../PYQQJ.TVPwyhLA1u.4E9X0z2/YHi1u', 1, '22:16', 2, '2026-02-09 20:54:58', '2026-02-09 20:55:05', 0, '2026-02-09 12:53:42', NULL),
(23, 'test_seven@gmail.com', '$2y$10$0QNWN9FlXHwcvHy8OXKN7ePniVNlX3SkhHCGYZbo4xhwUz..B0J3i', 1, '23:9', 1, '2026-02-09 22:01:50', '2026-02-09 22:01:53', 0, '2026-02-09 14:01:12', NULL),
(24, 'testnine@gmail.com', '$2y$10$bhgGgGg8oUQ1eVEN93FbJ.9ijJ79iZiqD2Fdh7M62BLB5VoRcGsRe', 1, '24:26', 1, '2026-02-09 22:40:44', '2026-02-09 22:40:48', 0, '2026-02-09 14:40:28', NULL),
(25, 'test_ten@gmail.com', '$2y$10$UjbNuWUTDTXGSVwJNaqHa.Otf4NeTg6SNIeWc..A4EJ.DCGAyEDv6', 1, '25:26', 2, '2026-02-10 01:49:34', '2026-02-10 01:49:40', 0, '2026-02-09 17:49:08', '2026-02-09 17:50:08');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sign_in_id` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`user_id`, `username`, `first_name`, `middle_name`, `surname`, `suffix`, `created_at`, `sign_in_id`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 'mrnmtchell', 'Maureene Mitchell', NULL, NULL, NULL, '2026-01-22 10:53:53', 1, 0, NULL, NULL),
(2, 'ace_grnd', 'Mary Althea Grande', NULL, NULL, NULL, '2026-02-04 16:38:42', 2, 0, NULL, NULL),
(8, 'snowlax', 'Samira Papa', NULL, NULL, NULL, '2026-02-04 22:36:01', 3, 0, NULL, NULL),
(9, 'testuser', 'test', NULL, NULL, NULL, '2026-02-04 23:05:58', 4, 0, NULL, NULL),
(11, 'eyjieee', 'Althea Grande', NULL, NULL, NULL, '2026-02-05 02:33:48', 5, 0, NULL, NULL),
(13, 'julia_b', 'Julia Bautista', NULL, NULL, NULL, '2026-02-05 02:44:51', 7, 0, NULL, NULL),
(14, 'test_user2', 'test user', NULL, NULL, NULL, '2026-02-07 09:56:47', 8, 0, NULL, NULL),
(19, 'test_email_2', 'test email two', NULL, NULL, NULL, '2026-02-08 12:04:40', 13, 0, NULL, NULL),
(24, 'test_user_three', 'test user three', NULL, NULL, NULL, '2026-02-09 11:43:56', 18, 0, NULL, NULL),
(25, 'test_user_four', 'test user four', NULL, NULL, NULL, '2026-02-09 11:47:01', 19, 0, NULL, NULL),
(26, 'test_five', 'test five', NULL, NULL, NULL, '2026-02-09 11:49:47', 21, 0, NULL, NULL),
(27, 'test_six', 'test six', NULL, NULL, NULL, '2026-02-09 12:53:42', 22, 0, NULL, NULL),
(28, 'test_seven', 'test seven', 'test middle name', '', 'test suffix', '2026-02-09 14:01:12', 23, 0, NULL, NULL),
(29, 'test_user9', 'test nine', NULL, NULL, NULL, '2026-02-09 14:40:28', 24, 0, NULL, NULL),
(30, 'test_10', 'test ten', NULL, NULL, NULL, '2026-02-09 17:49:08', 25, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_watchlist`
--

CREATE TABLE `tbl_watchlist` (
  `watchlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `movie_title` varchar(255) DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_watchlist`
--

INSERT INTO `tbl_watchlist` (`watchlist_id`, `user_id`, `movie_id`, `movie_title`, `poster_path`, `added_at`) VALUES
(1, 1, 840464, 'Greenland 2: Migration', '/1mF4othta76CEXcL1YFInYudQ7K.jpg', '2026-02-04 20:23:21'),
(3, 8, 1168190, 'The Wrecking Crew', '/gbVwHl4YPSq6BcC92TQpe7qUTh6.jpg', '2026-02-04 22:39:29'),
(5, 13, 1168190, 'The Wrecking Crew', '/gbVwHl4YPSq6BcC92TQpe7qUTh6.jpg', '2026-02-05 03:08:06'),
(6, 8, 1306368, 'The Rip', '/eZo31Dhl5BQ6GfbMNf3oU0tUvPZ.jpg', '2026-02-09 12:50:45'),
(7, 28, 527969, 'Camp', '/5tmffZhi559MC3FzPi48I7UMJJR.jpg', '2026-02-09 14:25:04'),
(8, 28, 1419406, 'The Shadow\`s Edge', '/e0RU6KpdnrqFxDKlI3NOqN8nHL6.jpg', '2026-02-09 14:26:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_movie_list`
--
ALTER TABLE `tbl_movie_list`
  ADD PRIMARY KEY (`movie_id`);

--
-- Indexes for table `tbl_movie_review`
--
ALTER TABLE `tbl_movie_review`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_movie_connection` (`movie_id`);

--
-- Indexes for table `tbl_sign_in`
--
ALTER TABLE `tbl_sign_in`
  ADD PRIMARY KEY (`sign_in_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_signin` (`sign_in_id`);

--
-- Indexes for table `tbl_watchlist`
--
ALTER TABLE `tbl_watchlist`
  ADD PRIMARY KEY (`watchlist_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_movie_review`
--
ALTER TABLE `tbl_movie_review`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_sign_in`
--
ALTER TABLE `tbl_sign_in`
  MODIFY `sign_in_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `tbl_watchlist`
--
ALTER TABLE `tbl_watchlist`
  MODIFY `watchlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_movie_review`
--
ALTER TABLE `tbl_movie_review`
  ADD CONSTRAINT `fk_movie_connection` FOREIGN KEY (`movie_id`) REFERENCES `tbl_movie_list` (`movie_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_movie_review_fk_tbl_users` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD CONSTRAINT `fk_users_signin` FOREIGN KEY (`sign_in_id`) REFERENCES `tbl_sign_in` (`sign_in_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_watchlist`
--
ALTER TABLE `tbl_watchlist`
  ADD CONSTRAINT `tbl_watchlist_fk_tbl_users` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
--
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;