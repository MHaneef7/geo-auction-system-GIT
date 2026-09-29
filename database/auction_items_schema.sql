-- ============================================================
-- Layer 1: MySQL Database Schema for Auction Item Cards
-- File: database/auction_items_schema.sql
-- Geo Auction System
-- ============================================================

CREATE TABLE IF NOT EXISTS `auction_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `image` VARCHAR(255) NOT NULL,
    `starting_bid` DECIMAL(12,2) NOT NULL,
    `current_bid` DECIMAL(12,2) DEFAULT NULL,
    `city` VARCHAR(100) NOT NULL,
    `latitude` DECIMAL(10,8) DEFAULT NULL,
    `longitude` DECIMAL(11,8) DEFAULT NULL,
    `end_time` DATETIME NOT NULL,
    `status` ENUM('active', 'ended') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status_endtime` (`status`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample rows for realistic testing (matches user reference image)
INSERT INTO `auction_items` 
(`title`, `description`, `image`, `starting_bid`, `current_bid`, `city`, `latitude`, `longitude`, `end_time`, `status`) 
VALUES
('2025 Foton View (Lot #660855)', 'GCC specs, low mileage passenger van, pristine engine condition. Direct insurance salvage clearance.', 'uploads/items/toyota-camry.jpg', 1100.00, 1100.00, 'Karachi', 24.8607, 67.0011, DATE_ADD(NOW(), INTERVAL 5 DAY) + INTERVAL 9 HOUR, 'active'),
('Toyota Camry SE - Clean Condition', 'Immaculate sedan with certified mechanical inspection, leather interior, and complete service history.', 'uploads/items/toyota-camry.jpg', 2500000.00, 2750000.00, 'Lahore', 31.5204, 74.3587, DATE_ADD(NOW(), INTERVAL 2 DAY) + INTERVAL 4 HOUR, 'active'),
('Honda Civic Oriel 2022', 'First owner vehicle, sunroof, push start, genuine mileage with brand new tyres and ceramic coating.', 'uploads/items/honda-civic-2022.jpg', 3500000.00, NULL, 'Islamabad', 33.6844, 73.0479, DATE_ADD(NOW(), INTERVAL 3 DAY) + INTERVAL 14 HOUR, 'active');
