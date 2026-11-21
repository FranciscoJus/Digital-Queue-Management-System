-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2026 at 08:12 AM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `digital_queue_db`
--
CREATE DATABASE IF NOT EXISTS `digital_queue_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `digital_queue_db`;

-- --------------------------------------------------------

--
-- Table structure for table `queue_records`
--

CREATE TABLE IF NOT EXISTS `queue_records` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `transaction_type_id` int(11) NOT NULL,
  `ticket_token` char(64) NOT NULL,
  `status` enum('Waiting','Serving','Completed','Skipped','Cancelled') NOT NULL DEFAULT 'Waiting',
  `created_at` timestamp(6) NOT NULL DEFAULT current_timestamp(6),
  `called_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `skipped_at` datetime DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `queue_number` int(11) DEFAULT NULL,
  `student_name` varchar(50) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_token` (`ticket_token`),
  UNIQUE KEY `uq_queue_number_session_number` (`session_id`,`queue_number`),
  KEY `fk_queue_transaction` (`transaction_type_id`),
  KEY `idx_queue_records_session_id` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue_sessions`
--

CREATE TABLE IF NOT EXISTS `queue_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `opened_at` int(11) NOT NULL,
  `closed_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_session_opened_at` (`opened_at`),
  KEY `fk_session_closed_at` (`closed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_accounts`
--

CREATE TABLE IF NOT EXISTS `staff_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_types`
--

CREATE TABLE IF NOT EXISTS `transaction_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaction_types`
--

INSERT INTO `transaction_types` (`id`, `type_name`, `is_active`) VALUES
(1, 'Enrollment', 1),
(2, 'Clearance', 1),
(3, 'Evaluation', 1),
(4, 'Department Fee', 1),
(5, 'Attendance', 1),
(6, 'Insurance', 1);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `queue_records`
--
ALTER TABLE `queue_records`
  ADD CONSTRAINT `fk_queue_records_session` FOREIGN KEY (`session_id`) REFERENCES `queue_sessions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_transaction` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types` (`id`);

--
-- Constraints for table `queue_sessions`
--
ALTER TABLE `queue_sessions`
  ADD CONSTRAINT `fk_session_closed_at` FOREIGN KEY (`closed_at`) REFERENCES `staff_accounts` (`id`),
  ADD CONSTRAINT `fk_session_opened_at` FOREIGN KEY (`opened_at`) REFERENCES `staff_accounts` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
