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
-- This table caches movie data from the API to prevent repeated API calls
--

CREATE TABLE `tbl_movie_list` (
  `movie_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `overview` text DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `last_updated` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_movie_list`
-- Includes all movies that have reviews
--

INSERT INTO `tbl_movie_list` (`movie_id`, `title`, `overview`, `poster_path`, `release_date`) VALUES
(83533, 'Avatar: Fire and Ash', NULL, '/5bxrxnRaxZooBAxgUVBZ13dpzC7.jpg', NULL),
(1168190, 'The Wrecking Crew', NULL, '/gbVwHl4YPSq6BcC92TQpe7qUTh6.jpg', NULL),
(1234731, 'Anaconda', 'A group of friends facing mid-life crises head to the rainforest with the intention of remaking their favorite movie from their youth, only to find themselves in a fight for their lives against natural disasters, giant snakes and violent criminals.', '/qxMv3HwAB3XPuwNLMhVRg795Ktp.jpg', '2025-12-24');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_movie_review`
--

CREATE TABLE `tbl_movie_review` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `movie_title` varchar(255) DEFAULT NULL,
  `review` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rating` tinyint(4) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_movie_review`
--

INSERT INTO `tbl_movie_review` (`id`, `user_id`, `movie_id`, `movie_title`, `review`, `created_at`, `rating`) VALUES
(1, 1, 83533, 'Avatar: Fire and Ash', 'Avatar: Fire and Ash delivers a visually stunning return to Pandora, blending breathtaking effects with a darker, more intense emotional tone. The film expands the world and its conflicts, exploring themes of anger, loss, and survival while maintaining James Cameron’s signature spectacle. Though the story feels familiar at times, the powerful visuals and immersive atmosphere make it a compelling and memorable cinematic experience.', '2026-01-22 12:44:57', 5),
(2, 2, 1168190, 'The Wrecking Crew', 'yey', '2026-02-04 16:40:47', 5),
(3, 2, 83533, 'Avatar: Fire and Ash', 'hell yeah', '2026-02-04 16:41:37', 10),
(4, 8, 1168190, 'The Wrecking Crew', 'Gunning evry morning', '2026-02-05 03:26:13', 8),
(5, 8, 83533, 'Avatar: Fire and Ash', 'cool movie1', '2026-02-05 01:07:06', 10),
(6, 8, 1234731, 'Anaconda', 'cool movie!', '2026-02-04 23:04:51', 10),
(7, 9, 1234731, 'Anaconda', 'meme movie but awesome', '2026-02-04 23:06:34', 10),
(9, 13, 1168190, 'The Wrecking Crew', 'test 2', '2026-02-05 03:16:31', 5);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_watchlist`
--

CREATE TABLE `tbl_watchlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `movie_title` varchar(255) DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_watchlist`
--

INSERT INTO `tbl_watchlist` (`id`, `user_id`, `movie_id`, `movie_title`, `poster_path`, `added_at`) VALUES
(1, 1, 840464, 'Greenland 2: Migration', '/1mF4othta76CEXcL1YFInYudQ7K.jpg', '2026-02-04 20:23:21'),
(3, 8, 1168190, 'The Wrecking Crew', '/gbVwHl4YPSq6BcC92TQpe7qUTh6.jpg', '2026-02-04 22:39:29'),
(5, 13, 1168190, 'The Wrecking Crew', '/gbVwHl4YPSq6BcC92TQpe7qUTh6.jpg', '2026-02-05 03:08:06');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sign_in`
-- This table stores authentication records (separate from profile `users` table)
--

CREATE TABLE `tbl_sign_in` (
  `sign_in_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_sent_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`sign_in_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `email`, `password`, `created_at`) VALUES
(1, 'mrnmtchell', 'Maureene Mitchell', 'mtmitchell@gmail.com', '$2y$10$ZbNepJeaDIt5K2XYKM2CxOiyTMwsuu3mz.p6dnDyDvkwhGpD1xMta', '2026-01-22 10:53:53'),
(2, 'ace_grnd', 'Mary Althea Grande', 'maryaltheagrande@gmail.com', '$2y$10$6QJwC5dtIIjzwxFKQqJI9ut18Aa8GwZCvLPZnA6UpyRRAUzJiISeq', '2026-02-04 16:38:42'),
(8, 'snowlax', 'Samira Papa', 'samirasnoe7@gmail.com', '$2y$10$lQ8R7vuD9rShf3rV9CL2GuWF.o/xQJEWQFUH1F0.i.8k4chP6wl86', '2026-02-04 22:36:01'),
(9, 'testuser', 'test', 'test@gmail.com', '$2y$10$J2be1GxmytUGdEvI2rXk0e4YY5nrdM7JdTHHJMM4OQXHDqq/1V2TG', '2026-02-04 23:05:58'),
(11, 'eyjieee', 'Althea Grande', 'grandealthea2@gmail.com', '$2y$10$nLvXlaq/zvRmdF25tTu5ye0oM8XAweAvwjiol94KFB/fjH0emFgeK', '2026-02-05 02:33:48'),
(12, 'usert', 'user t', 'usert@yahoo.com', '$2y$10$O6jocToIPeSyCWM6VQ8svuUe6SWkikEDIfuc1M/j.Z8LyiLoHk60O', '2026-02-05 02:38:44'),
(13, 'julia_b', 'Julia Bautista', 'juliabautista@gmail.com', '$2y$10$nhP2D3hPPkNgkBhRAd5iauRrQBQrQwA8cCGnl.hrYgvUH7R4JdaFC', '2026-02-05 02:44:51');

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tbl_watchlist`
--
ALTER TABLE `tbl_watchlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `tbl_movie_review`
--
ALTER TABLE `tbl_movie_review`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_watchlist`
--
ALTER TABLE `tbl_watchlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_movie_review`
--
ALTER TABLE `tbl_movie_review`
  ADD CONSTRAINT `tbl_movie_review_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_movie_connection` FOREIGN KEY (`movie_id`) REFERENCES `tbl_movie_list` (`movie_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_watchlist`
--
ALTER TABLE `tbl_watchlist`
  ADD CONSTRAINT `tbl_watchlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------
-- Migrate existing auth data into `tbl_sign_in` and link to `users`
-- Insert sign-in records from existing `users` rows (preserve passwords)
-- Note: this keeps the existing `email` and `password` columns in `users` for compatibility,
-- but also creates `tbl_sign_in` entries and links them via `sign_in_id` on `users`.

INSERT INTO `tbl_sign_in` (`email`, `password`, `is_verified`, `is_admin`, `created_at`)
SELECT `email`, `password`, 0, 0, `created_at` FROM `users`;

-- Add sign_in_id to users (nullable first), populate from tbl_sign_in, then make NOT NULL and add FK
ALTER TABLE `users` ADD COLUMN `sign_in_id` INT NULL DEFAULT NULL;

UPDATE `users` u
JOIN `tbl_sign_in` s ON s.email = u.email
SET u.sign_in_id = s.sign_in_id;

ALTER TABLE `users` MODIFY `sign_in_id` INT NOT NULL;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_signin` FOREIGN KEY (`sign_in_id`) REFERENCES `tbl_sign_in` (`sign_in_id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL,
  ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;

ALTER TABLE `tbl_movie_review`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL,
  ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
--
-- Database: `test`
--
CREATE DATABASE IF NOT EXISTS `test` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `test`;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
