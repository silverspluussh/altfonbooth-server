-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 22, 2026 at 07:17 PM
-- Server version: 11.4.10-MariaDB-cll-lve-log
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `altfinwl_alfonweb`
--

-- --------------------------------------------------------

--
-- Table structure for table `subscribers`
--

CREATE TABLE `subscribers` (
  `recid` int(11) NOT NULL,
  `subscriberid` varchar(20) NOT NULL,
  `fullname` varchar(60) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `phonenumber` varchar(20) NOT NULL,
  `emailaddress` varchar(180) NOT NULL,
  `country` varchar(30) NOT NULL,
  `authusername` varchar(20) DEFAULT NULL,
  `switch_status` varchar(15) DEFAULT NULL,
  `billing_acc_status` varchar(15) DEFAULT NULL,
  `password_reset_token` varchar(50) DEFAULT NULL,
  `password_reset_expiration` datetime DEFAULT NULL,
  `regdatetime` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscribers`
--

INSERT INTO `subscribers` (`recid`, `subscriberid`, `fullname`, `username`, `password`, `phonenumber`, `emailaddress`, `country`, `authusername`, `switch_status`, `billing_acc_status`, `password_reset_token`, `password_reset_expiration`, `regdatetime`) VALUES
(14, 'SUB_c8ccb5fdaa00', 'Philip Appiah', 'pappiah', '$2y$10$u/2P1z.IEuyQql3rHowDVOh7z1f.3DGFOGcd8xxc.PyFCBaiEcxi.', '0249335549', 'appiah212@gmail.com', 'Ghana', NULL, 'active', 'active', NULL, NULL, '2026-01-16 18:30:05'),
(15, 'SUB_9ebb08e724bc', 'Francis Doe', 'nsia', '$2y$10$hYHpTEpZv4HLNBv2UX0FR.gptA0TdoDqDu92oujOc6MEn325BeolW', '0557466718', 'pantagoros@gmail.com', 'Ghana', NULL, 'active', 'active', NULL, NULL, '2026-01-16 18:39:05');

-- --------------------------------------------------------

--
-- Table structure for table `subscribers_temp`
--

CREATE TABLE `subscribers_temp` (
  `recid` int(11) NOT NULL,
  `subscriberid` varchar(20) NOT NULL,
  `fullname` varchar(60) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `phonenumber` varchar(20) NOT NULL,
  `emailaddress` varchar(50) NOT NULL,
  `country` varchar(30) NOT NULL,
  `otp` varchar(12) NOT NULL,
  `otp_expiration` datetime DEFAULT NULL,
  `verify_code` varchar(30) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `regdatetime` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriber_auth`
--

CREATE TABLE `subscriber_auth` (
  `id` int(11) NOT NULL,
  `subscriberid` varchar(20) NOT NULL,
  `authusername` varchar(50) NOT NULL,
  `authpassword` varchar(255) NOT NULL,
  `status` varchar(15) DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriber_auth`
--

INSERT INTO `subscriber_auth` (`id`, `subscriberid`, `authusername`, `authpassword`, `status`, `created_at`, `updated_at`) VALUES
(5, 'SUB_c8ccb5fdaa00', '7022875', 'f9d4368a', 'active', '2026-01-16 18:30:05', '2026-01-16 18:30:05'),
(6, 'SUB_9ebb08e724bc', '3891326', '72b682bb', 'active', '2026-01-16 18:39:05', '2026-01-23 08:23:32');

-- --------------------------------------------------------

--
-- Table structure for table `subscriber_dest`
--

CREATE TABLE `subscriber_dest` (
  `id` int(11) NOT NULL,
  `subscriberid` varchar(20) NOT NULL,
  `authusername` varchar(50) NOT NULL,
  `destination` varchar(20) NOT NULL,
  `status` varchar(15) DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `subscribers`
--
ALTER TABLE `subscribers`
  ADD PRIMARY KEY (`recid`),
  ADD UNIQUE KEY `unique_subscriberid` (`subscriberid`);

--
-- Indexes for table `subscribers_temp`
--
ALTER TABLE `subscribers_temp`
  ADD PRIMARY KEY (`recid`);

--
-- Indexes for table `subscriber_auth`
--
ALTER TABLE `subscriber_auth`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `authusername` (`authusername`),
  ADD KEY `fk_subscriber` (`subscriberid`);

--
-- Indexes for table `subscriber_dest`
--
ALTER TABLE `subscriber_dest`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subscriber_dest` (`subscriberid`);


--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `recid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `subscribers_temp`
--
ALTER TABLE `subscribers_temp`
  MODIFY `recid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `subscriber_auth`
--
ALTER TABLE `subscriber_auth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `subscriber_dest`
--
ALTER TABLE `subscriber_dest`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


--
-- Constraints for dumped tables
--

--
-- Constraints for table `subscriber_auth`
--
ALTER TABLE `subscriber_auth`
  ADD CONSTRAINT `fk_subscriber` FOREIGN KEY (`subscriberid`) REFERENCES `subscribers` (`subscriberid`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
