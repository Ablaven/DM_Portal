-- Create unavailability table for blocking doctor time slots
-- Run this in phpMyAdmin or MySQL command line

USE digital_marketing_portal;

CREATE TABLE IF NOT EXISTS `unavailability` (
  `unavailability_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` INT UNSIGNED NOT NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unavailability_id`),
  KEY `idx_doctor_dates` (`doctor_id`, `start_datetime`, `end_datetime`),
  CONSTRAINT `fk_unavailability_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
