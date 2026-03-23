-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 23, 2026 at 05:33 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.1.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smartschoolbus_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `record_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `rfid_uid` varchar(20) NOT NULL,
  `action` enum('boarded','dropped_off') NOT NULL,
  `timestamp` datetime NOT NULL,
  `device_id` varchar(50) NOT NULL,
  `verification_status` enum('verified','pending','flagged') DEFAULT 'verified',
  `recorded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`record_id`, `student_id`, `bus_id`, `rfid_uid`, `action`, `timestamp`, `device_id`, `verification_status`, `recorded_at`) VALUES
(1, 1, 1, '4A3B2C1D', 'boarded', '2026-03-18 20:55:53', 'ESP32_BUS01', 'verified', '2026-03-18 22:55:53'),
(2, 2, 1, 'E8F94A23', 'boarded', '2026-03-18 20:55:53', 'ESP32_BUS01', 'verified', '2026-03-18 22:55:53'),
(3, 3, 1, '3F8A9B4C', 'boarded', '2026-03-18 20:55:53', 'ESP32_BUS01', 'verified', '2026-03-18 22:55:53'),
(4, 4, 2, '7B2D4E6F', 'boarded', '2026-03-18 21:55:53', 'ESP32_BUS02', 'verified', '2026-03-18 22:55:53'),
(5, 5, 2, '9C3E5F1A', 'boarded', '2026-03-18 21:55:53', 'ESP32_BUS02', 'verified', '2026-03-18 22:55:53'),
(6, 1, 1, '4A3B2C1D', 'dropped_off', '2026-03-18 22:25:53', 'ESP32_BUS01', 'verified', '2026-03-18 22:55:53'),
(7, 2, 1, 'E8F94A23', 'dropped_off', '2026-03-18 22:25:53', 'ESP32_BUS01', 'verified', '2026-03-18 22:55:53'),
(8, 1, 1, '4A3B2C1D', 'boarded', '2026-03-18 23:04:39', 'ESP32_BUS01', 'verified', '2026-03-18 23:04:39'),
(9, 2, 1, 'E8F94A23', 'boarded', '2026-03-18 23:04:34', 'ESP32_BUS01', 'verified', '2026-03-18 23:04:39'),
(10, 1, 1, '4A3B2C1D', 'dropped_off', '2026-03-18 23:04:46', 'ESP32_BUS01', 'verified', '2026-03-18 23:04:46'),
(11, 2, 1, 'E8F94A23', 'dropped_off', '2026-03-18 23:04:41', 'ESP32_BUS01', 'verified', '2026-03-18 23:04:46'),
(12, 1, 1, '4A3B2C1D', 'boarded', '2026-03-20 00:54:13', 'ESP32_BUS01', 'verified', '2026-03-20 00:54:13'),
(13, 2, 1, 'E8F94A23', 'boarded', '2026-03-20 00:54:08', 'ESP32_BUS01', 'verified', '2026-03-20 00:54:13');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `timestamp`) VALUES
(1, 1, 'created_user', 'user', 4, '{\"username\": \"driver1\", \"role\": \"driver\"}', '127.0.0.1', '2026-03-18 22:55:53'),
(2, 1, 'created_bus', 'bus', 1, '{\"bus_number\": \"Bus #01\", \"capacity\": 30}', '127.0.0.1', '2026-03-18 22:55:53'),
(3, 2, 'assigned_student', 'student', 1, '{\"student_id\": 1, \"bus_id\": 1}', '127.0.0.1', '2026-03-18 22:55:53'),
(4, 1, 'demo_attendance_seed', 'attendance_records', NULL, '{\"count\":2,\"action\":\"boarded\",\"students\":[\"Ahmad bin Ali\",\"Nur Aisyah binti Hassan\"]}', '::1', '2026-03-18 23:04:39'),
(5, 1, 'demo_attendance_seed', 'attendance_records', NULL, '{\"count\":2,\"action\":\"dropped_off\",\"students\":[\"Ahmad bin Ali\",\"Nur Aisyah binti Hassan\"]}', '::1', '2026-03-18 23:04:46'),
(6, 1, 'update', 'user', 4, '{\"username\":\"driver1\",\"role_id\":3}', '::1', '2026-03-18 23:05:38'),
(7, 1, 'demo_attendance_seed', 'attendance_records', NULL, '{\"count\":2,\"action\":\"boarded\",\"students\":[\"Ahmad bin Ali\",\"Nur Aisyah binti Hassan\"]}', '::1', '2026-03-20 00:54:13'),
(8, 1, 'update', 'user', 2, '{\"username\":\"Yasin\",\"role_id\":2}', '::1', '2026-03-20 01:20:50'),
(9, 1, 'update', 'user', 4, '{\"username\":\"izdihar\",\"role_id\":3}', '::1', '2026-03-20 01:21:57'),
(10, 1, 'update', 'user', 2, '{\"username\":\"yasin\",\"role_id\":2}', '::1', '2026-03-20 01:22:13'),
(11, 2, 'unauthorized_role_access', 'security', NULL, '{\"user_id\":2,\"user_role\":\"staff\",\"required_roles\":\"admin\",\"url\":\"\\/SmartSchoolBus\\/admin\\/users.php?success=updated\"}', '::1', '2026-03-20 01:25:53'),
(12, NULL, 'unauthorized_access_attempt', 'security', NULL, '{\"url\":\"\\/SmartSchoolBus\\/staff\\/notifications.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 01:55:42'),
(13, NULL, 'unauthorized_access_attempt', 'security', NULL, '{\"url\":\"\\/SmartSchoolBus\\/staff\\/notifications.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 01:55:46'),
(14, NULL, 'unauthorized_access_attempt', 'security', NULL, '{\"url\":\"\\/SmartSchoolBus\\/staff\\/notifications.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 01:56:58'),
(15, 2, 'unauthorized_role_access', 'security', NULL, '{\"user_id\":2,\"user_role\":\"staff\",\"required_roles\":\"admin\",\"url\":\"\\/SmartSchoolBus\\/admin\\/notifications.php\"}', '::1', '2026-03-20 01:58:14'),
(16, 1, 'unauthorized_role_access', 'security', NULL, '{\"user_id\":1,\"user_role\":\"admin\",\"required_roles\":\"staff\",\"url\":\"\\/SmartSchoolBus\\/staff\\/payments.php\"}', '::1', '2026-03-20 02:00:51'),
(17, NULL, 'unauthorized_access_attempt', 'security', NULL, '{\"url\":\"\\/SmartSchoolBus\\/admin\\/users.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 02:01:34'),
(18, NULL, 'unauthorized_access_attempt', 'security', NULL, '{\"url\":\"\\/SmartSchoolBus\\/staff\\/notifications.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 02:16:29'),
(19, 2, 'unauthorized_role_access', 'security', NULL, '{\"user_id\":2,\"user_role\":\"staff\",\"required_roles\":\"admin\",\"url\":\"\\/SmartSchoolBus\\/admin\\/payments.php?month=1&year=2026&bus=0&student=&parent=\"}', '::1', '2026-03-20 02:18:28'),
(20, NULL, 'unauthorized_access_attempt', 'security', NULL, '{\"url\":\"\\/SmartSchoolBus\\/staff\\/payments.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 02:19:03'),
(21, 2, 'unauthorized_role_access', 'security', NULL, '{\"user_id\":2,\"user_role\":\"staff\",\"required_roles\":\"admin\",\"url\":\"\\/SmartSchoolBus\\/admin\\/dashboard.php\"}', '::1', '2026-03-20 12:00:13'),
(22, 1, 'unauthorized_role_access', 'security', NULL, '{\"user_id\":1,\"user_role\":\"admin\",\"required_roles\":\"staff\",\"url\":\"\\/SmartSchoolBus\\/staff\\/reports.php\"}', '::1', '2026-03-20 12:05:12'),
(23, NULL, 'no_role_session_found', 'security', NULL, '{\"requested_section\":\"admin\",\"url\":\"\\/SmartSchoolBus\\/admin\\/dashboard.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 12:42:17'),
(24, NULL, 'no_role_session_found', 'security', NULL, '{\"requested_section\":\"admin\",\"url\":\"\\/SmartSchoolBus\\/admin\\/dashboard.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 12:42:44'),
(25, NULL, 'no_role_session_found', 'security', NULL, '{\"requested_section\":\"staff\",\"url\":\"\\/SmartSchoolBus\\/staff\\/dashboard.php\",\"ip\":\"::1\"}', '::1', '2026-03-20 12:43:00'),
(26, 1, 'create', 'user', 6, '{\"username\":\"kamil\",\"role_id\":3}', '::1', '2026-03-20 12:51:13'),
(27, 1, 'delete', 'user', 6, '{\"username\":\"kamil\",\"full_name\":\"kamil shah\"}', '::1', '2026-03-20 12:51:19'),
(28, 1, 'update', 'user', 3, '{\"username\":\"daus\",\"role_id\":2}', '::1', '2026-03-20 16:14:12'),
(29, 1, 'update', 'user', 5, '{\"username\":\"kumar\",\"role_id\":3}', '::1', '2026-03-20 16:19:35'),
(30, 1, 'update', 'user', 4, '{\"username\":\"izdihar\",\"role_id\":3}', '::1', '2026-03-20 16:21:21');

-- --------------------------------------------------------

--
-- Table structure for table `buses`
--

CREATE TABLE `buses` (
  `bus_id` int(11) NOT NULL,
  `bus_number` varchar(20) NOT NULL,
  `license_plate` varchar(20) DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `assigned_driver_id` int(11) DEFAULT NULL,
  `route_description` text DEFAULT NULL,
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `device_id` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buses`
--

INSERT INTO `buses` (`bus_id`, `bus_number`, `license_plate`, `capacity`, `assigned_driver_id`, `route_description`, `status`, `device_id`, `created_at`, `updated_at`) VALUES
(1, 'Bus #01', 'WA1234B', 30, 4, 'Sepang - Putrajaya Route', 'active', 'ESP32_BUS01', '2026-03-18 22:55:53', '2026-03-18 22:55:53'),
(2, 'Bus #02', 'WA5678C', 30, 5, 'Sepang - Cyberjaya Route', 'active', 'ESP32_BUS02', '2026-03-18 22:55:53', '2026-03-18 22:55:53'),
(3, 'Bus #03', 'WB1234D', 25, NULL, 'Sepang - KLIA Route', 'active', 'ESP32_BUS03', '2026-03-18 22:55:53', '2026-03-18 22:55:53');

-- --------------------------------------------------------

--
-- Table structure for table `daily_checklists`
--

CREATE TABLE `daily_checklists` (
  `checklist_id` int(11) NOT NULL,
  `checklist_date` date NOT NULL,
  `shift_type` enum('morning','evening') NOT NULL,
  `staff_id` int(11) NOT NULL,
  `buses_inspected` tinyint(1) DEFAULT 0,
  `drivers_present` tinyint(1) DEFAULT 0,
  `rfid_readers_online` tinyint(1) DEFAULT 0,
  `emergency_kits_checked` tinyint(1) DEFAULT 0,
  `all_students_accounted` tinyint(1) DEFAULT 0,
  `buses_returned` tinyint(1) DEFAULT 0,
  `incidents_reported` tinyint(1) DEFAULT 0,
  `handover_completed` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_checklists`
--

INSERT INTO `daily_checklists` (`checklist_id`, `checklist_date`, `shift_type`, `staff_id`, `buses_inspected`, `drivers_present`, `rfid_readers_online`, `emergency_kits_checked`, `all_students_accounted`, `buses_returned`, `incidents_reported`, `handover_completed`, `notes`, `completed_at`, `created_at`) VALUES
(1, '2026-03-20', 'morning', 2, 1, 1, 1, 1, 0, 0, 0, 0, 'no issue', '2026-03-20 01:25:10', '2026-03-20 01:25:10'),
(2, '2026-03-20', 'evening', 2, 0, 0, 0, 0, 1, 1, 1, 1, 'same, no issue', '2026-03-20 01:25:36', '2026-03-20 01:25:36');

-- --------------------------------------------------------

--
-- Table structure for table `iot_devices`
--

CREATE TABLE `iot_devices` (
  `device_id` varchar(50) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `bus_id` int(11) DEFAULT NULL,
  `api_key` varchar(255) NOT NULL,
  `status` enum('online','offline','error') DEFAULT 'offline',
  `firmware_version` varchar(20) DEFAULT NULL,
  `last_heartbeat` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `iot_devices`
--

INSERT INTO `iot_devices` (`device_id`, `device_name`, `bus_id`, `api_key`, `status`, `firmware_version`, `last_heartbeat`, `ip_address`, `created_at`, `updated_at`) VALUES
('ESP32_BUS01', 'Bus #01 RFID Reader', 1, 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'online', 'v1.0.2', '2026-03-18 22:55:53', '192.168.1.101', '2026-03-18 22:55:53', '2026-03-18 22:55:53'),
('ESP32_BUS02', 'Bus #02 RFID Reader', 2, 'fcde2b2edba56bf408601fb721fe9b5c338d10ee429ea04fae5511b68fbf8fb9', 'online', 'v1.0.2', '2026-03-18 22:55:53', '192.168.1.102', '2026-03-18 22:55:53', '2026-03-18 22:55:53'),
('ESP32_BUS03', 'Bus #03 RFID Reader', 3, 'bef57ec7f53a6d40beb640a780a639c83bc29ac8a9816f1fc6c5c6dcd93c4721', 'offline', 'v1.0.1', '2026-03-18 20:55:53', '192.168.1.103', '2026-03-18 22:55:53', '2026-03-18 22:55:53');

-- --------------------------------------------------------

--
-- Table structure for table `logout_feedback`
--

CREATE TABLE `logout_feedback` (
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_role` varchar(50) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `message` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_duration` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `logout_feedback`
--

INSERT INTO `logout_feedback` (`feedback_id`, `user_id`, `user_role`, `rating`, `message`, `ip_address`, `user_agent`, `session_duration`, `created_at`) VALUES
(1, NULL, 'staff', 10, 'i like it', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 2003, '2026-03-20 16:12:41'),
(2, NULL, 'driver', 9, 'i need to know who student already paid or not', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 106, '2026-03-20 16:23:25');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `recipient_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','alert','request') DEFAULT 'info',
  `related_entity` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `sender_id`, `recipient_id`, `title`, `message`, `type`, `related_entity`, `related_id`, `is_read`, `created_at`, `read_at`) VALUES
(2, 3, 1, 'Payment Reminder', 'Ahmad Firdaus noted that 2 students have unpaid fees for January 2026', 'alert', 'payment', NULL, 1, '2026-03-18 22:55:53', '2026-03-20 14:58:19'),
(4, 2, 1, 'rfid card not detect', 'ascsacczxcscokcsc[dsc', 'alert', 'RFID', NULL, 0, '2026-03-20 16:01:48', NULL),
(5, 2, 1, 'i accident', 'no student effect on this case', 'warning', 'Bus', NULL, 0, '2026-03-20 16:03:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `parent_id` int(11) NOT NULL,
  `parent_name` varchar(100) NOT NULL,
  `phone_primary` varchar(20) NOT NULL,
  `phone_secondary` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `notification_preference` enum('sms','email','both','none') DEFAULT 'sms',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`parent_id`, `parent_name`, `phone_primary`, `phone_secondary`, `email`, `address`, `relationship`, `notification_preference`, `created_at`) VALUES
(1, 'Ali bin Ahmad', '+60123456801', NULL, 'ali.ahmad@email.com', 'Lot 123, Taman Sepang, 43900 Sepang', 'father', 'sms', '2026-03-18 22:55:53'),
(2, 'Siti binti Hassan', '+60123456802', '+60123456803', 'siti.hassan@email.com', 'No 45, Jalan Persiaran, 43900 Sepang', 'mother', 'both', '2026-03-18 22:55:53'),
(3, 'Wong Mei Ling', '+60123456804', NULL, 'wong.mei@email.com', 'Blok C-3-5, Taman Cyberjaya, 43900 Sepang', 'mother', 'sms', '2026-03-18 22:55:53'),
(4, 'Rajesh Kumar', '+60123456805', '+60123456806', 'rajesh.k@email.com', '78 Jalan Bukit, 43900 Sepang', 'father', 'email', '2026-03-18 22:55:53'),
(5, 'Fatimah binti Omar', '+60123456807', NULL, 'fatimah.omar@email.com', 'No 12, Taman Indah, 43900 Sepang', 'mother', 'sms', '2026-03-18 22:55:53');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','online') DEFAULT 'cash',
  `month_paid_for` varchar(20) NOT NULL,
  `status` enum('completed','pending','failed') DEFAULT 'completed',
  `reference_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `student_id`, `amount`, `payment_date`, `payment_method`, `month_paid_for`, `status`, `reference_number`, `notes`, `recorded_by`, `created_at`) VALUES
(1, 1, '150.00', '2026-01-05', 'bank_transfer', 'January 2026', 'completed', 'PAY20260105001', NULL, 1, '2026-03-18 22:55:53'),
(2, 2, '150.00', '2026-01-07', 'cash', 'January 2026', 'completed', 'PAY20260107001', NULL, 2, '2026-03-18 22:55:53'),
(3, 4, '150.00', '2026-01-10', 'online', 'January 2026', 'completed', 'PAY20260110001', NULL, 1, '2026-03-18 22:55:53'),
(4, 5, '150.00', '2026-03-20', 'cash', 'January 2026', 'completed', NULL, '[proof:uploads/payment_proofs/payment_5_1773988351.png]', 1, '2026-03-20 14:32:31'),
(5, 1, '150.00', '2026-03-20', 'cash', 'March 2026', 'completed', NULL, '[proof:uploads/payment_proofs/payment_1_1773988560.png]', 1, '2026-03-20 14:36:00');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_description`, `permissions`, `created_at`) VALUES
(1, 'admin', 'Full system access and management', '{\"user_management\": true, \"delete_records\": true, \"view_all\": true, \"export_data\": true}', '2026-03-18 22:55:53'),
(2, 'staff', 'View and manage students and attendance', '{\"view_students\": true, \"assign_students\": true, \"view_reports\": true, \"delete_records\": false}', '2026-03-18 22:55:53'),
(3, 'driver', 'View own bus and students only', '{\"view_own_bus\": true, \"view_students\": false, \"delete_records\": false}', '2026-03-18 22:55:53');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `rfid_uid` varchar(20) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `bus_id` int(11) DEFAULT NULL,
  `payment_status` enum('paid','unpaid') DEFAULT 'unpaid',
  `status` enum('active','inactive') DEFAULT 'active',
  `photo_url` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `enrollment_date` date DEFAULT curdate(),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_name`, `rfid_uid`, `parent_id`, `bus_id`, `payment_status`, `status`, `photo_url`, `address`, `emergency_contact`, `date_of_birth`, `enrollment_date`, `created_at`, `updated_at`) VALUES
(1, 'Ahmad bin Ali', '4A3B2C1D', 1, 1, 'paid', 'active', NULL, 'Blok T-19', '+60123456801', '2012-05-15', '2024-01-10', '2026-03-18 22:55:53', '2026-03-20 15:04:00'),
(2, 'Nur Aisyah binti Hassan', 'E8F94A23', 2, 1, 'paid', 'active', NULL, NULL, '+60123456802', '2013-08-22', '2024-01-10', '2026-03-18 22:55:53', '2026-03-18 22:55:53'),
(3, 'Wong Li Ming', '3F8A9B4C', 3, 1, 'unpaid', 'active', NULL, NULL, '+60123456804', '2014-03-10', '2024-01-10', '2026-03-18 22:55:53', '2026-03-18 22:55:53'),
(4, 'Priya Rajesh', '7B2D4E6F', 4, 2, 'paid', 'active', NULL, NULL, '+60123456805', '2012-11-30', '2024-01-10', '2026-03-18 22:55:53', '2026-03-18 22:55:53'),
(5, 'Muhammad Amin bin Omar', '9C3E5F1A', 5, 2, 'unpaid', 'active', NULL, NULL, '+60123456807', '2013-07-18', '2024-01-10', '2026-03-18 22:55:53', '2026-03-18 22:55:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `settings` longtext DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `mfa_secret` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `full_name`, `role_id`, `email`, `phone`, `avatar_url`, `settings`, `status`, `last_login`, `created_at`, `updated_at`, `mfa_enabled`, `mfa_secret`) VALUES
(1, 'admin', '$2y$10$Ao0UdoG.RXIOQhT83V/V6eBPY0MZ0uJn8DeLCH0ZxXOJfbqgdAvYO', 'Admin SelamatRide', 1, 'admin@selamatride.com', '+60129485240', NULL, NULL, 'active', '2026-03-20 17:22:35', '2026-03-18 22:55:53', '2026-03-20 17:22:35', 1, '354SA4YQYUA7H2ZXM7QGXKQ4GYPNNY4J'),
(2, 'yasin', '$2y$10$nfnPdtMn15H5xT9FV9pPxeqOOVcqWSye5NTQSOKzMGuOB054r2dJ6', 'Yasin Khan', 2, 'yasink@selamatride.com', '+60123456789', NULL, NULL, 'active', '2026-03-20 15:39:18', '2026-03-18 22:55:53', '2026-03-20 15:39:18', 0, NULL),
(3, 'daus', '$2y$10$14usETHlGh3e9Bx.PbYY3O6wi7er1O.e06cMbTorPmwM1Twzt/c7W', 'Ahmad Firdaus', 2, 'ahmad.F@selamatride.com', '+60123456790', NULL, NULL, 'active', NULL, '2026-03-18 22:55:53', '2026-03-20 16:14:12', 0, NULL),
(4, 'izdihar', '$2y$10$Kr98NSKYi4M4zSqc3bo4COlSg7811IX8ZBPAATHCyMYWsOqtDNC6e', 'Izdihar Azmi', 3, 'izdihar.a@selamatride.com', '+60123456791', NULL, NULL, 'active', '2026-03-20 16:21:39', '2026-03-18 22:55:53', '2026-03-20 16:21:39', 0, NULL),
(5, 'kumar', '$2y$10$H1yuoENrFlLjKE6TzNlnB.eNtRQmbPbyoMumIoujsUyo/b1AnYzMq', 'Kumar Raju', 3, 'kumar@selamatride.com', '+60123456792', NULL, NULL, 'active', NULL, '2026-03-18 22:55:53', '2026-03-20 16:19:35', 0, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_student_overview`
-- (See below for the actual view)
--
CREATE TABLE `vw_student_overview` (
`student_id` int(11)
,`student_name` varchar(100)
,`rfid_uid` varchar(20)
,`payment_status` enum('paid','unpaid')
,`status` enum('active','inactive')
,`parent_name` varchar(100)
,`phone_primary` varchar(20)
,`bus_number` varchar(20)
,`bus_id` int(11)
,`driver_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_today_attendance`
-- (See below for the actual view)
--
CREATE TABLE `vw_today_attendance` (
`attendance_date` date
,`action` enum('boarded','dropped_off')
,`total_records` bigint(21)
,`unique_students` bigint(21)
,`buses_used` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_student_overview`
--
DROP TABLE IF EXISTS `vw_student_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_student_overview`  AS SELECT `s`.`student_id` AS `student_id`, `s`.`student_name` AS `student_name`, `s`.`rfid_uid` AS `rfid_uid`, `s`.`payment_status` AS `payment_status`, `s`.`status` AS `status`, `p`.`parent_name` AS `parent_name`, `p`.`phone_primary` AS `phone_primary`, `b`.`bus_number` AS `bus_number`, `b`.`bus_id` AS `bus_id`, `u`.`full_name` AS `driver_name` FROM (((`students` `s` left join `parents` `p` on(`s`.`parent_id` = `p`.`parent_id`)) left join `buses` `b` on(`s`.`bus_id` = `b`.`bus_id`)) left join `users` `u` on(`b`.`assigned_driver_id` = `u`.`user_id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `vw_today_attendance`
--
DROP TABLE IF EXISTS `vw_today_attendance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_today_attendance`  AS SELECT cast(`ar`.`timestamp` as date) AS `attendance_date`, `ar`.`action` AS `action`, count(0) AS `total_records`, count(distinct `ar`.`student_id`) AS `unique_students`, count(distinct `ar`.`bus_id`) AS `buses_used` FROM `attendance_records` AS `ar` WHERE cast(`ar`.`timestamp` as date) = curdate() GROUP BY cast(`ar`.`timestamp` as date), `ar`.`action``action`  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `bus_id` (`bus_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_date_action` (`timestamp`,`action`),
  ADD KEY `idx_composite` (`student_id`,`timestamp`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `buses`
--
ALTER TABLE `buses`
  ADD PRIMARY KEY (`bus_id`),
  ADD UNIQUE KEY `bus_number` (`bus_number`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD KEY `assigned_driver_id` (`assigned_driver_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_device` (`device_id`);

--
-- Indexes for table `daily_checklists`
--
ALTER TABLE `daily_checklists`
  ADD PRIMARY KEY (`checklist_id`),
  ADD UNIQUE KEY `unique_checklist` (`checklist_date`,`shift_type`,`staff_id`),
  ADD KEY `idx_date` (`checklist_date`),
  ADD KEY `idx_staff` (`staff_id`);

--
-- Indexes for table `iot_devices`
--
ALTER TABLE `iot_devices`
  ADD PRIMARY KEY (`device_id`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD KEY `bus_id` (`bus_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_heartbeat` (`last_heartbeat`);

--
-- Indexes for table `logout_feedback`
--
ALTER TABLE `logout_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `idx_user_role` (`user_role`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_recipient_read` (`recipient_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD KEY `idx_phone` (`phone_primary`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_date` (`payment_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `rfid_uid` (`rfid_uid`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_rfid` (`rfid_uid`),
  ADD KEY `idx_bus` (`bus_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment` (`payment_status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `buses`
--
ALTER TABLE `buses`
  MODIFY `bus_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_checklists`
--
ALTER TABLE `daily_checklists`
  MODIFY `checklist_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logout_feedback`
--
ALTER TABLE `logout_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `parent_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `buses`
--
ALTER TABLE `buses`
  ADD CONSTRAINT `buses_ibfk_1` FOREIGN KEY (`assigned_driver_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `daily_checklists`
--
ALTER TABLE `daily_checklists`
  ADD CONSTRAINT `daily_checklists_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `iot_devices`
--
ALTER TABLE `iot_devices`
  ADD CONSTRAINT `iot_devices_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
