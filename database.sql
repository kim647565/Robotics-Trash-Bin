CREATE DATABASE IF NOT EXISTS robo_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE robo_system;

CREATE TABLE IF NOT EXISTS waste_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sensor_distance_cm DECIMAL(10,2) NOT NULL,
  fill_percentage TINYINT UNSIGNED NOT NULL,
  bin_status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_waste_logs_timestamp (`timestamp`),
  KEY idx_waste_logs_fill_percentage (fill_percentage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO waste_logs (`timestamp`, sensor_distance_cm, fill_percentage, bin_status) VALUES
  (CURRENT_TIMESTAMP - INTERVAL 4 HOUR, 18.50, 8, 'AVAILABLE'),
  (CURRENT_TIMESTAMP - INTERVAL 3 HOUR, 14.20, 29, 'AVAILABLE'),
  (CURRENT_TIMESTAMP - INTERVAL 2 HOUR, 9.80, 51, 'NEARLY FULL'),
  (CURRENT_TIMESTAMP - INTERVAL 1 HOUR, 4.10, 80, 'NEARLY FULL'),
  (CURRENT_TIMESTAMP, 1.20, 94, 'COLLECTION REQUIRED');