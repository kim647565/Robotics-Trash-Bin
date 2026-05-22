CREATE DATABASE IF NOT EXISTS robo_system;

USE robo_system;

CREATE TABLE IF NOT EXISTS waste_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    sensor_distance_cm FLOAT NOT NULL,
    fill_percentage FLOAT NOT NULL,
    bin_status VARCHAR(50) NOT NULL
);