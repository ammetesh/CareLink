-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 16, 2026 at 10:02 AM
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
-- Database: `carelink_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `alert_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dose_logs`
--

CREATE TABLE `dose_logs` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `status` enum('Taken','Skipped','Snoozed') NOT NULL,
  `taken_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dose_logs`
--

INSERT INTO `dose_logs` (`id`, `schedule_id`, `log_date`, `status`, `taken_time`) VALUES
(1, 1, '2026-07-16', 'Taken', '2026-07-16 05:13:33'),
(2, 3, '2026-07-16', 'Taken', '2026-07-16 07:30:52');

-- --------------------------------------------------------

--
-- Table structure for table `family_links`
--

CREATE TABLE `family_links` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `family_links`
--

INSERT INTO `family_links` (`id`, `patient_id`, `family_id`, `relationship`) VALUES
(2, 1, 2, 'Father');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `medicine_name` varchar(100) NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `take_time` varchar(100) DEFAULT NULL,
  `frequency` enum('Once Daily','Twice Daily','Three Times Daily','Weekly','As Needed') NOT NULL,
  `meal_timing` enum('Before Food','After Food','With Food','Anytime') DEFAULT 'Anytime',
  `is_active` tinyint(1) DEFAULT 1,
  `instructions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `patient_id`, `medicine_name`, `dosage`, `take_time`, `frequency`, `meal_timing`, `is_active`, `instructions`, `notes`, `start_date`, `end_date`) VALUES
(1, 1, 'Paracetamol', '450', NULL, 'Twice Daily', 'After Food', 1, 'Break into half and eat', 'May avoid if no pain', '2026-07-15', '2026-07-22'),
(2, 3, 'Paracetamol', '450', NULL, 'Once Daily', 'Before Food', 1, 'Eat half only', 'nil', '2026-07-15', '2026-07-22'),
(3, 4, 'citrizen', '250', NULL, 'Once Daily', 'After Food', 1, 'night time', '', '2026-07-16', '2026-07-23');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `dose_time` time NOT NULL,
  `reminder_enabled` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `medicine_id`, `dose_time`, `reminder_enabled`) VALUES
(1, 1, '23:13:00', 1),
(2, 2, '11:22:00', 1),
(3, 3, '11:01:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `role` enum('patient','family','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `age`, `role`, `created_at`) VALUES
(1, 'Sabarish', 'sabarish@gmail.com', '$2y$10$1AI.fCEcTmM7aXB9KvrR3eljBa6nGRgromn6uJn2EvADozH5ut/Je', '9994534546', 18, 'patient', '2026-07-15 14:59:32'),
(2, 'Heloo', 'hellosan@gmail.com', '$2y$10$QXaYeDNtChNPocCRyD.zkuTj9/dHaupM8czfm.Mfm2T0H3kzQbJOi', '9994534547', 48, 'family', '2026-07-15 16:21:14'),
(3, 'Dinesh', 'dinesh123@gmail.com', '$2y$10$f4W/VSuTlLx4BUjKcTF6uuiSFCANkGOwG8Lk4/V82zGGJzrEhRtyK', '9626848923', 18, 'patient', '2026-07-16 04:50:46'),
(4, 'SRI SABARISH N', 'srisabarish191107@gmail.com', '$2y$10$559NjuFwp6AdNqqHEdM2n.odWFWlylwg4iccjELvD3xKND37M/zli', '9345663878', 18, 'patient', '2026-07-16 05:27:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `dose_logs`
--
ALTER TABLE `dose_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `family_links`
--
ALTER TABLE `family_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `family_id` (`family_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dose_logs`
--
ALTER TABLE `dose_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `family_links`
--
ALTER TABLE `family_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `dose_logs`
--
ALTER TABLE `dose_logs`
  ADD CONSTRAINT `dose_logs_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `family_links`
--
ALTER TABLE `family_links`
  ADD CONSTRAINT `family_links_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `family_links_ibfk_2` FOREIGN KEY (`family_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `medicines`
--
ALTER TABLE `medicines`
  ADD CONSTRAINT `medicines_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
