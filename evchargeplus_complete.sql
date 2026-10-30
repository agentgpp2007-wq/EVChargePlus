-- ============================================================
-- EVChargePlus Complete Database Dump
-- Generated for Deployment
-- Date: 2026-10-29 17:34:40
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Table structure and data for `booking_feedback`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `booking_feedback`;
CREATE TABLE `booking_feedback` (
  `feedback_id` int unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comments` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  UNIQUE KEY `unique_booking_feedback` (`booking_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `booking_feedback` VALUES
('1', '8', '6', '4', 'very good', '2026-10-20 17:04:52');

-- ------------------------------------------------------------
-- Table structure and data for `bookings`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `charger_id` int NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `booking_status` enum('BOOKED','COMPLETED','CANCELLED','NO_SHOW') DEFAULT 'BOOKED',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `user_id` (`user_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `idx_booking_overlap_lookup` (`charger_id`,`booking_date`,`booking_status`,`start_time`,`end_time`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_id`),
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`),
  CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`charger_id`) REFERENCES `chargers` (`charger_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `bookings` VALUES
('1', '1', '1', '1', '2026-09-01', '10:00:00', '11:00:00', 'COMPLETED', '2026-09-30 23:26:50'),
('2', '2', '2', '5', '2026-09-01', '12:00:00', '13:00:00', 'COMPLETED', '2026-09-30 23:26:50'),
('3', '3', '3', '6', '2026-09-02', '18:00:00', '19:00:00', 'COMPLETED', '2026-09-30 23:26:50'),
('4', '4', '4', '8', '2026-09-02', '15:00:00', '16:00:00', 'COMPLETED', '2026-09-30 23:26:50'),
('5', '6', '5', '1', '2026-10-10', '19:25:00', '19:45:00', 'BOOKED', '2026-10-10 17:24:37'),
('6', '6', '5', '10', '2026-10-10', '21:00:00', '22:00:00', 'CANCELLED', '2026-10-10 19:57:04'),
('7', '6', '5', '1', '2026-10-16', '10:41:00', '12:45:00', 'COMPLETED', '2026-10-16 08:41:41'),
('8', '6', '6', '8', '2026-10-17', '09:47:00', '10:15:00', 'COMPLETED', '2026-10-16 08:47:43'),
('9', '6', '6', '1', '2026-10-23', '09:00:00', '10:00:00', 'COMPLETED', '2026-10-22 11:19:00'),
('10', '6', '5', '1', '2026-10-27', '11:00:00', '12:00:00', 'COMPLETED', '2026-10-27 10:15:03'),
('11', '6', '5', '1', '2026-10-27', '21:00:00', '22:00:00', 'COMPLETED', '2026-10-27 17:17:23');

-- ------------------------------------------------------------
-- Table structure and data for `chargers`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `chargers`;
CREATE TABLE `chargers` (
  `charger_id` int NOT NULL AUTO_INCREMENT,
  `station_id` int NOT NULL,
  `charger_number` varchar(20) NOT NULL,
  `charger_type` enum('AC','DC_FAST','DC_ULTRA_FAST') NOT NULL,
  `power_rating` decimal(6,2) NOT NULL,
  `connector_type` varchar(30) NOT NULL,
  `price_per_kwh` decimal(8,2) NOT NULL,
  `charger_status` enum('AVAILABLE','OCCUPIED','MAINTENANCE','INACTIVE') DEFAULT 'AVAILABLE',
  PRIMARY KEY (`charger_id`),
  UNIQUE KEY `station_id` (`station_id`,`charger_number`),
  CONSTRAINT `chargers_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `chargingstations` (`station_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `chargers` VALUES
('1', '1', 'CH-01', 'DC_FAST', '60.00', 'CCS2', '14.00', 'AVAILABLE'),
('2', '1', 'CH-02', 'DC_FAST', '60.00', 'CCS2', '14.00', 'AVAILABLE'),
('3', '1', 'CH-03', 'AC', '22.00', 'Type-2', '10.00', 'AVAILABLE'),
('4', '1', 'CH-04', 'DC_ULTRA_FAST', '120.00', 'CCS2', '18.00', 'AVAILABLE'),
('5', '2', 'CH-01', 'DC_FAST', '60.00', 'CCS2', '15.00', 'AVAILABLE'),
('6', '2', 'CH-02', 'DC_ULTRA_FAST', '150.00', 'CCS2', '20.00', 'AVAILABLE'),
('7', '2', 'CH-03', 'AC', '22.00', 'Type-2', '11.00', 'AVAILABLE'),
('8', '3', 'CH-01', 'DC_FAST', '50.00', 'CCS2', '13.00', 'AVAILABLE'),
('9', '3', 'CH-02', 'AC', '22.00', 'Type-2', '9.00', 'AVAILABLE'),
('10', '4', 'CH-01', 'DC_FAST', '60.00', 'CCS2', '14.00', 'AVAILABLE'),
('11', '4', 'CH-02', 'AC', '22.00', 'Type-2', '10.00', 'AVAILABLE'),
('13', '5', 'CH-01', 'DC_FAST', '60.00', 'CCS2', '14.00', 'AVAILABLE'),
('14', '5', 'CH-02', 'AC', '22.00', 'Type-2', '10.00', 'AVAILABLE'),
('16', '6', 'CH-01', 'DC_FAST', '60.00', 'CCS2', '14.02', 'AVAILABLE'),
('17', '6', 'CH-02', 'AC', '22.00', 'Type-2', '10.00', 'AVAILABLE'),
('19', '7', 'CH-01', 'DC_FAST', '60.00', 'CCS2', '14.00', 'AVAILABLE'),
('20', '7', 'CH-02', 'AC', '22.00', 'Type-2', '10.00', 'AVAILABLE'),
('22', '8', 'CH-01', 'DC_FAST', '60.00', 'CCS2', '14.00', 'AVAILABLE'),
('23', '8', 'CH-02', 'AC', '22.00', 'Type-2', '10.00', 'AVAILABLE');

-- ------------------------------------------------------------
-- Table structure and data for `charging_history`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `charging_history`;
CREATE TABLE `charging_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `start_battery` decimal(5,2) NOT NULL,
  `target_battery` decimal(5,2) NOT NULL,
  `energy_kwh` decimal(10,2) NOT NULL,
  `estimated_minutes` int NOT NULL,
  `range_added_km` decimal(10,2) NOT NULL,
  `estimated_cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_charging_user` (`user_id`),
  KEY `idx_charging_vehicle` (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `charging_history` VALUES
('1', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.50', '2026-10-20 12:06:43'),
('2', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.51', '2026-10-22 11:19:39'),
('3', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.50', '2026-10-22 11:43:42'),
('4', '6', '6', '15.00', '80.00', '1.63', '7', '13.00', '16.25', '2026-10-22 11:44:23'),
('5', '6', '6', '15.00', '80.00', '1.63', '12', '13.00', '16.25', '2026-10-22 11:44:52'),
('6', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.49', '2026-10-24 12:31:26'),
('7', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.50', '2026-10-24 19:40:36'),
('8', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.50', '2026-10-24 19:55:21'),
('9', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.50', '2026-10-27 10:12:28'),
('10', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.56', '2026-10-27 10:13:40'),
('11', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.56', '2026-10-27 10:13:46'),
('12', '6', '6', '25.00', '80.00', '1.38', '2', '8.25', '16.56', '2026-10-27 10:13:51'),
('13', '6', '6', '25.00', '80.00', '1.38', '1', '8.25', '16.56', '2026-10-27 10:13:53'),
('14', '6', '6', '25.00', '80.00', '1.38', '12', '8.25', '16.50', '2026-10-27 11:03:42'),
('15', '6', '6', '25.00', '80.00', '1.38', '2', '8.25', '19.25', '2026-10-27 17:17:40'),
('16', '6', '6', '25.00', '80.00', '1.38', '1', '8.25', '24.75', '2026-10-29 09:35:40');

-- ------------------------------------------------------------
-- Table structure and data for `charging_receipts`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `charging_receipts`;
CREATE TABLE `charging_receipts` (
  `receipt_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `history_id` int DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `receipt_token` char(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`receipt_id`),
  UNIQUE KEY `uq_receipt_token` (`receipt_token`),
  UNIQUE KEY `uq_receipt_history` (`history_id`),
  UNIQUE KEY `uq_receipt_booking` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `charging_receipts` VALUES
('1', NULL, '1', '98a914ee9ed455e86e5bc42c37f4ea15b4f6b03bbe8ef9216a561b1c6467e577', '2026-10-27 09:48:47'),
('2', NULL, '2', 'ea0ffe0b438871e30eb747f678a884abada125b09bc31d9b74dddbfbf2e23322', '2026-10-27 09:48:47'),
('3', NULL, '3', '5b59306ce6379d005e8c032bbb588d53b3162a6d3e0331755ce359a4ece15ae5', '2026-10-27 09:48:47'),
('4', NULL, '4', '877864484de6f61580a9c91267a97be0c23240fcb7d1fb41739737f3367e81e7', '2026-10-27 09:48:47'),
('5', NULL, '7', '129c3b2b83258fe8053497a42eaa875ef369105d4ba95db44367ff9bb70ab825', '2026-10-27 09:48:47'),
('6', NULL, '8', 'e9ec0edca6f675b2c6d6b48f823423a82941f63727a17a1d54b71e3e35787005', '2026-10-27 09:48:47'),
('7', '9', NULL, 'd68fb7658c828e460bdbc4ce2d8a7cd8d49adae4487f84d67a9fa1f09871c6c1', '2026-10-27 10:12:29'),
('8', '10', NULL, 'ddd609d202d784fd51e72498c16f36a9d4f533c079a2c5880909210255a9ee2c', '2026-10-27 10:13:40'),
('9', '11', NULL, '50f204f00df0eb80df298b086f3dc8437da279b41ffe61b84fbf3ebeec7be040', '2026-10-27 10:13:46'),
('10', '12', NULL, '5908b325e98ebf8e4961863a220c3f22eff325e1aa936ec1f869fd0ca32d02fa', '2026-10-27 10:13:51'),
('11', '13', NULL, '11c0dca5b955b956405df3a5c3a40ebf72623378c521f45542d8b3d6f613a7b4', '2026-10-27 10:13:53'),
('12', '14', NULL, '3c960011739fe142c2842c12267118810a28d93f27c780ab84ce747e0f462994', '2026-10-27 11:03:42'),
('13', '15', NULL, 'a379d12e509858d8060fe1580523867a5e65f290f0b478bfc8e403b620aade10', '2026-10-27 17:17:40'),
('14', NULL, '11', '88c3e5559d612aec0e4d771eda979779255c890d8341492bc7df7315bc690147', '2026-10-29 09:33:28'),
('15', NULL, '9', 'b5c6e52725648f3fdf8d87bb07394d9bef778b40e6274ae68fcf74908f020faa', '2026-10-29 09:34:02'),
('16', '16', NULL, '8c41ccbea061f83ee94b07fe81c58b767ad7a2f2f9d7509202179c5c3e6fc92c', '2026-10-29 09:35:40');

-- ------------------------------------------------------------
-- Table structure and data for `chargingsessions`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `chargingsessions`;
CREATE TABLE `chargingsessions` (
  `session_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `starting_battery` decimal(5,2) DEFAULT NULL,
  `ending_battery` decimal(5,2) DEFAULT NULL,
  `session_status` enum('NOT_STARTED','CHARGING','COMPLETED','INTERRUPTED') DEFAULT 'NOT_STARTED',
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  CONSTRAINT `chargingsessions_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `chargingsessions` VALUES
('1', '1', '2026-09-01 10:02:00', '2026-09-01 10:50:00', '25.00', '80.00', 'COMPLETED'),
('2', '2', '2026-09-01 12:05:00', '2026-09-01 12:55:00', '30.00', '85.00', 'COMPLETED'),
('3', '3', '2026-09-02 18:00:00', '2026-09-02 19:00:00', '20.00', '80.00', 'COMPLETED'),
('4', '4', '2026-09-02 15:00:00', '2026-09-02 16:00:00', '20.00', '80.00', 'COMPLETED'),
('5', '7', '2026-10-16 10:41:00', '2026-10-16 12:45:00', '20.00', '80.00', 'COMPLETED'),
('6', '8', '2026-10-17 09:47:00', '2026-10-17 10:15:00', '20.00', '80.00', 'COMPLETED'),
('7', '11', '2026-10-27 21:00:00', '2026-10-27 22:00:00', '20.00', '80.00', 'COMPLETED'),
('8', '9', '2026-10-23 09:00:00', '2026-10-23 10:00:00', '20.00', '80.00', 'COMPLETED');

-- ------------------------------------------------------------
-- Table structure and data for `chargingstations`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `chargingstations`;
CREATE TABLE `chargingstations` (
  `station_id` int NOT NULL AUTO_INCREMENT,
  `station_name` varchar(100) NOT NULL,
  `location` varchar(150) NOT NULL,
  `city` varchar(50) NOT NULL,
  `total_chargers` int NOT NULL,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `station_status` enum('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
  PRIMARY KEY (`station_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `chargingstations` VALUES
('1', 'Green EV Hub', 'FC Road', 'Pune', '6', '06:00:00', '23:00:00', 'ACTIVE'),
('2', 'VoltCharge Station', 'Baner Road', 'Pune', '8', '00:00:00', '23:59:59', 'ACTIVE'),
('3', 'EcoCharge Point', 'Kothrud', 'Pune', '5', '06:00:00', '22:00:00', 'ACTIVE'),
('4', 'Hinjawadi EV Point', 'Hinjawadi', 'Pune', '2', '06:00:00', '23:00:00', 'ACTIVE'),
('5', 'Wakad Green Charge', 'Wakad', 'Pune', '2', '06:00:00', '23:00:00', 'ACTIVE'),
('6', 'Aundh EV Station', 'Aundh', 'Pune', '2', '06:00:00', '23:00:00', 'ACTIVE'),
('7', 'Pimple Saudagar Charge Point', 'Pimple Saudagar', 'Pune', '2', '06:00:00', '23:00:00', 'ACTIVE'),
('8', 'Pimpri EV Hub', 'Pimpri', 'Pune', '2', '06:00:00', '23:00:00', 'ACTIVE');

-- ------------------------------------------------------------
-- Table structure and data for `energyconsumption`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `energyconsumption`;
CREATE TABLE `energyconsumption` (
  `energy_id` int NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `energy_consumed` decimal(8,2) NOT NULL,
  `charging_duration` int DEFAULT NULL,
  `co2_saved` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`energy_id`),
  UNIQUE KEY `session_id` (`session_id`),
  CONSTRAINT `energyconsumption_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `chargingsessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `energyconsumption` VALUES
('1', '1', '22.50', '48', '16.20'),
('2', '2', '27.75', '50', '19.98'),
('3', '3', '54.45', '60', '39.20'),
('4', '4', '18.00', '60', '12.96'),
('5', '5', '30.38', '124', '21.87'),
('6', '6', '1.88', '28', '1.35'),
('7', '7', '30.38', '60', '21.87'),
('8', '8', '1.88', '60', '1.35');

-- ------------------------------------------------------------
-- Table structure and data for `feedback`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `feedback_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `station_id` int NOT NULL,
  `rating` int NOT NULL,
  `comments` varchar(500) DEFAULT NULL,
  `feedback_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  KEY `user_id` (`user_id`),
  KEY `station_id` (`station_id`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_id`),
  CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`station_id`) REFERENCES `chargingstations` (`station_id`),
  CONSTRAINT `feedback_chk_1` CHECK ((`rating` between 1 and 5))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `feedback` VALUES
('1', '1', '1', '5', 'Very fast charging and clean station', '2026-09-30 23:28:23'),
('2', '2', '2', '4', 'Good station but slightly crowded', '2026-09-30 23:28:23'),
('3', '3', '1', '5', 'Excellent charging experience', '2026-09-30 23:28:23'),
('4', '4', '3', '4', 'Affordable and convenient', '2026-09-30 23:28:23');

-- ------------------------------------------------------------
-- Table structure and data for `maintenance`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `maintenance`;
CREATE TABLE `maintenance` (
  `maintenance_id` int NOT NULL AUTO_INCREMENT,
  `charger_id` int NOT NULL,
  `maintenance_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `technician_name` varchar(100) DEFAULT NULL,
  `maintenance_cost` decimal(10,2) DEFAULT '0.00',
  `maintenance_status` enum('SCHEDULED','IN_PROGRESS','COMPLETED') DEFAULT 'SCHEDULED',
  PRIMARY KEY (`maintenance_id`),
  KEY `charger_id` (`charger_id`),
  CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`charger_id`) REFERENCES `chargers` (`charger_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `maintenance` VALUES
('1', '4', '2026-08-25', 'Connector inspection and cooling system check', 'Raj Technician', '1500.00', 'COMPLETED'),
('2', '7', '2026-08-28', 'Cable replacement', 'Vijay Technician', '2500.00', 'IN_PROGRESS');

-- ------------------------------------------------------------
-- Table structure and data for `payments`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('UPI','CARD','WALLET','CASH') NOT NULL,
  `payment_status` enum('PENDING','SUCCESS','FAILED','REFUNDED') DEFAULT 'PENDING',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `session_id` (`session_id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `chargingsessions` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `payments` VALUES
('1', '1', '315.00', 'UPI', 'SUCCESS', 'TXN10001', '2026-09-30 23:27:45'),
('2', '2', '416.25', 'CARD', 'SUCCESS', 'TXN10002', '2026-09-30 23:27:45'),
('3', '3', '1089.00', 'UPI', 'SUCCESS', 'TXN10003', '2026-09-02 19:00:00'),
('4', '4', '234.00', 'UPI', 'SUCCESS', 'TXN10004', '2026-09-02 16:00:00'),
('5', '5', '425.32', 'UPI', 'SUCCESS', 'TXN10007', '2026-10-16 12:45:00'),
('6', '6', '24.44', 'UPI', 'SUCCESS', 'TXN10008', '2026-10-17 10:15:00'),
('7', '7', '425.32', 'UPI', 'SUCCESS', 'TXN10011', '2026-10-27 22:00:00'),
('8', '8', '26.32', 'UPI', 'SUCCESS', 'TXN10009', '2026-10-23 10:00:00');

-- ------------------------------------------------------------
-- Table structure and data for `pricing`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `pricing`;
CREATE TABLE `pricing` (
  `pricing_id` int NOT NULL AUTO_INCREMENT,
  `charger_type` enum('AC','DC_FAST','DC_ULTRA_FAST') NOT NULL,
  `time_period` enum('OFF_PEAK','NORMAL','PEAK') NOT NULL,
  `price_per_kwh` decimal(8,2) NOT NULL,
  PRIMARY KEY (`pricing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `pricing` VALUES
('1', 'AC', 'OFF_PEAK', '8.00'),
('2', 'AC', 'NORMAL', '10.00'),
('3', 'AC', 'PEAK', '12.00'),
('4', 'DC_FAST', 'OFF_PEAK', '12.00'),
('5', 'DC_FAST', 'NORMAL', '14.00'),
('6', 'DC_FAST', 'PEAK', '17.00'),
('7', 'DC_ULTRA_FAST', 'OFF_PEAK', '16.00'),
('8', 'DC_ULTRA_FAST', 'NORMAL', '18.00'),
('9', 'DC_ULTRA_FAST', 'PEAK', '22.00');

-- ------------------------------------------------------------
-- Table structure and data for `rewards`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `rewards`;
CREATE TABLE `rewards` (
  `reward_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `points_earned` int DEFAULT '0',
  `points_redeemed` int DEFAULT '0',
  `reward_description` varchar(255) DEFAULT NULL,
  `reward_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reward_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `rewards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `rewards` VALUES
('1', '1', '100', '0', 'Charging session reward', '2026-09-30 23:28:39'),
('2', '2', '120', '20', 'Charging session reward', '2026-09-30 23:28:39'),
('3', '3', '150', '0', 'Eco charging reward', '2026-09-30 23:28:39'),
('4', '4', '80', '0', 'First charging reward', '2026-09-30 23:28:39'),
('5', '6', '50', '0', 'Automatic completion reward for booking #8', '2026-10-17 09:22:43'),
('6', '6', '50', '0', 'Automatic completion reward for booking #7', '2026-10-20 11:54:33'),
('7', '2', '50', '0', 'Automatic completion reward for booking #2', '2026-10-20 17:05:44'),
('8', '6', '50', '0', 'Automatic completion reward for booking #10', '2026-10-27 21:33:45'),
('9', '6', '10', '0', 'Reward points for completed charging session #11', '2026-10-29 09:33:28'),
('10', '6', '50', '0', 'Automatic completion reward for booking #11', '2026-10-29 09:33:48'),
('11', '6', '10', '0', 'Reward points for completed charging session #9', '2026-10-29 09:34:02'),
('12', '6', '50', '0', 'Automatic completion reward for booking #9', '2026-10-29 09:35:18');

-- ------------------------------------------------------------
-- Table structure and data for `users`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `User_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(12) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(200) NOT NULL,
  `role` enum('USER','ADMIN') DEFAULT 'USER',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`User_id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `users` VALUES
('1', 'Rahul Sharma', '9876543210', 'rahul@gmail.com', '$2y$10$PypjfHILIFnC47YrwxlkAeVnWzdsyTQCoZhPfnCOkt9qo4.tzw2qm', 'USER', '2026-09-30 23:25:06'),
('2', 'Priya Patil', '9876543211', 'priya@gmail.com', '$2y$10$QpcABj.RvxCH3gI87.JGZu9Vfk0cDv0lrG9q0FmmOWUznMMCU/8HC', 'USER', '2026-09-30 23:25:06'),
('3', 'Amit Kumar', '9876543212', 'amit@gmail.com', 'amit123', 'USER', '2026-09-30 23:25:06'),
('4', 'Sneha Joshi', '9876543213', 'sneha@gmail.com', 'sneha123', 'USER', '2026-09-30 23:25:06'),
('5', 'Admin', '9999999999', 'admin@evcharge.com', '$2y$10$N3OyiMh08oO29VCh5j8Is.Ll8V/rxMpdBr4qE/Y07j0q5UgB/lr7K', 'ADMIN', '2026-09-30 23:25:06'),
('6', 'Test User', '9000000001', 'test9000001@gmail.com', '$2y$10$ilkLHZsZoDT77d1I8w7O.u8XiKvQzJIz/WnNB8BrmGUnutTOyFlgK', 'USER', '2026-10-10 12:24:45');

-- ------------------------------------------------------------
-- Table structure and data for `vehicles`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `vehicle_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `vehicle_number` varchar(20) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `battery_capacity` decimal(6,2) NOT NULL,
  `connector_type` varchar(30) NOT NULL,
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `vehicle_number` (`vehicle_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `vehicles` VALUES
('1', '1', 'MH12AB1234', 'Tata', 'Nexon EV', '40.50', 'CCS2'),
('2', '2', 'MH14CD5678', 'MG', 'ZS EV', '50.30', 'CCS2'),
('3', '3', 'MH12EF9012', 'Hyundai', 'Ioniq 5', '72.60', 'CCS2'),
('4', '4', 'MH15GH3456', 'Tata', 'Tiago EV', '24.00', 'CCS2'),
('5', '6', 'MH12TEST01', 'Tata', 'Nexon EV', '40.50', 'CCS2'),
('6', '6', 'MH12CK7563', 'BAJAJ', 'CHETAK', '2.50', 'Type-2');

SET FOREIGN_KEY_CHECKS = 1;
