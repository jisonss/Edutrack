-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 23, 2026 at 01:55 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `datas`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_submission_inbox`
--

CREATE TABLE `admin_submission_inbox` (
  `id` int(11) NOT NULL,
  `lrn` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deadlines`
--

CREATE TABLE `deadlines` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `deadline_date` date NOT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deadlines`
--

INSERT INTO `deadlines` (`id`, `title`, `deadline_date`, `created_by`, `created_at`, `section_id`, `teacher_id`) VALUES
(70, '123', '2026-11-13', 'ssss@gmail.com', '2026-11-14 06:48:46', 1, 30);

-- --------------------------------------------------------

--
-- Table structure for table `final_grades`
--

CREATE TABLE `final_grades` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_email` varchar(100) NOT NULL,
  `lrn` varchar(50) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `final_grade` decimal(5,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `final_grades`
--

INSERT INTO `final_grades` (`id`, `student_email`, `lrn`, `teacher_id`, `section_id`, `final_grade`, `remarks`) VALUES
(1, 'sons43914@gmail.com', '136544130604', 30, 1, 90.00, 'buti nalang mabait ako sayo hayop ka');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `lrn` varchar(50) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `grade` decimal(5,2) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inbox`
--

CREATE TABLE `inbox` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lrn` varchar(50) NOT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `type` enum('lesson','deadline','submission') DEFAULT 'submission',
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inbox`
--

INSERT INTO `inbox` (`id`, `email`, `message`, `created_at`, `lrn`, `archived`, `type`, `section_id`, `teacher_id`, `related_id`) VALUES
(225, 'sons43914@gmail.com', 'New lesson uploaded for BSIT 1-1: COMP-20023_Computer-Programming-1.pdf', '2026-11-14 06:48:03', '136544130604', 1, 'lesson', 1, 30, 30),
(226, 'sons43914@gmail.com', 'New deadline for BSIT 1-1: 123', '2026-11-14 06:48:46', '136544130604', 1, 'deadline', 1, 30, 70),
(227, 'sons43914@gmail.com', 'New deadline for BSIT 1-1: now', '2026-11-14 06:49:09', '136544130604', 1, 'deadline', 1, 30, 71),
(228, 'sons43914@gmail.com', 'Your file \'123\' has been reviewed. Feedback: awit sayo boss', '2026-11-14 06:51:00', '136544130604', 1, '', 1, 30, 76),
(229, 'sons43914@gmail.com', 'Your FINAL grade has been posted: 90', '2026-11-14 06:52:34', '136544130604', 1, '', 1, 30, NULL),
(231, 'sons43914@gmail.com', 'Your FINAL grade has been posted: 90', '2026-11-14 06:52:39', '136544130604', 1, '', 1, 30, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inbox_history`
--

CREATE TABLE `inbox_history` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `seen` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(10) UNSIGNED NOT NULL,
  `section_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`) VALUES
(1, 'BSIT 1-1'),
(2, 'BSIT 1-2'),
(3, 'BSIT 1-3'),
(4, 'BSIT 1-4'),
(5, 'BSIT 1-5');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `student_email` varchar(100) DEFAULT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `lrn` varchar(20) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `filepath` varchar(255) DEFAULT NULL,
  `submission_title` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  `feedback` text DEFAULT NULL,
  `feedback_noted` tinyint(1) DEFAULT 0,
  `status` varchar(20) DEFAULT 'pending',
  `visible_to_admin` tinyint(1) DEFAULT 1,
  `submission_file` varchar(255) NOT NULL,
  `deadline_id` int(11) NOT NULL,
  `late` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`id`, `student_email`, `student_name`, `lrn`, `filename`, `filepath`, `submission_title`, `submitted_at`, `feedback`, `feedback_noted`, `status`, `visible_to_admin`, `submission_file`, `deadline_id`, `late`) VALUES
(76, 'sons43914@gmail.com', 'JAYSON GAMUTAN PRADO', '136544130604', '123', 'uploads/submissions/1794639051_123.pdf', NULL, '2026-11-14 14:50:51', 'awit sayo boss', 0, 'pending', 1, '123.pdf', 70, 1);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_sections`
--

CREATE TABLE `teacher_sections` (
  `id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_sections`
--

INSERT INTO `teacher_sections` (`id`, `teacher_id`, `section_id`) VALUES
(1, 30, 1);

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_lessons`
--

CREATE TABLE `uploaded_lessons` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `filepath` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `filepath` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `teacher_id` int(11) DEFAULT NULL,
  `section_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uploads`
--

INSERT INTO `uploads` (`id`, `filename`, `filepath`, `uploaded_by`, `uploaded_at`, `teacher_id`, `section_id`) VALUES
(30, 'COMP-20023_Computer-Programming-1.pdf', 'uploads/1794638883_COMP-20023_Computer-Programming-1.pdf', 'ssss@gmail.com', '2026-11-14 14:48:03', 30, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `lrn` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `section_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `lrn`, `email`, `password`, `role`, `created_at`, `section_id`) VALUES
(28, 'JAYSON GAMUTAN PRADO', '136544130604', 'sons43914@gmail.com', '$2y$10$ynFNHVZSOXG.pgrj/rgYie/GmaTLjOHgabzJ7UHbCJJzEX.3MwmQi', 'user', '2026-11-14 06:29:47', 1),
(30, 'Sir batuta', NULL, 'ssss@gmail.com', '$2y$10$4DgcXshu1wAUM5WwnjXtPe2KONtfX.76./o66/o5pv7gJ2O6aVUXi', 'admin', '2026-11-14 06:35:02', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_submission_inbox`
--
ALTER TABLE `admin_submission_inbox`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deadlines`
--
ALTER TABLE `deadlines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `final_grades`
--
ALTER TABLE `final_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_teacher_section` (`student_email`,`teacher_id`,`section_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inbox`
--
ALTER TABLE `inbox`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inbox_history`
--
ALTER TABLE `inbox_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section_name` (`section_name`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_section` (`teacher_id`,`section_id`);

--
-- Indexes for table `uploaded_lessons`
--
ALTER TABLE `uploaded_lessons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `lrn` (`lrn`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_submission_inbox`
--
ALTER TABLE `admin_submission_inbox`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deadlines`
--
ALTER TABLE `deadlines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `final_grades`
--
ALTER TABLE `final_grades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inbox`
--
ALTER TABLE `inbox`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232;

--
-- AUTO_INCREMENT for table `inbox_history`
--
ALTER TABLE `inbox_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uploaded_lessons`
--
ALTER TABLE `uploaded_lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
