-- ============================================================
-- Geo Auction System - Database Schema
-- Complete SQL for all 15+ tables
-- ============================================================

CREATE DATABASE IF NOT EXISTS geo_auction_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE geo_auction_db;

-- ============================================================
-- 1. Roles Table
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    permissions JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. Users Table
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'buyer', -- admin, seller, buyer
    avatar VARCHAR(255) NULL,
    bio TEXT NULL,
    gender ENUM('male', 'female', 'other') NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    email_verified_at TIMESTAMP NULL,
    reset_token VARCHAR(100) NULL,
    reset_token_expires_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL,
    settings JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 3. Categories Table
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(50) NULL DEFAULT 'fa-tag',
    image VARCHAR(255) NULL,
    parent_id INT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_categories_slug (slug),
    INDEX idx_categories_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 4. Items (Auctions) Table
-- ============================================================
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auction_code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    short_description VARCHAR(500) NULL,
    
    -- Pricing
    starting_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    reserve_price DECIMAL(12, 2) NULL,
    current_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    min_increment DECIMAL(12, 2) NOT NULL DEFAULT 1.00,
    buy_it_now_price DECIMAL(12, 2) NULL,
    
    -- Images
    featured_image VARCHAR(255) NULL,
    images JSON NULL, -- Array of additional images
    
    -- Specifications
    condition_type VARCHAR(50) NULL, -- new, used, refurbished
    brand VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    year INT NULL,
    mileage VARCHAR(50) NULL,
    engine_type VARCHAR(100) NULL,
    color VARCHAR(50) NULL,
    
    -- Location (for Google Maps)
    location_address VARCHAR(255) NULL,
    location_city VARCHAR(100) NULL,
    location_state VARCHAR(100) NULL,
    location_country VARCHAR(100) NULL,
    location_lat DECIMAL(10, 8) NULL,
    location_lng DECIMAL(11, 8) NULL,
    
    -- Timestamps
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    extended_time DATETIME NULL, -- Anti-sniping extension
    
    -- Status & Flags
    status ENUM('pending', 'active', 'paused', 'ended', 'cancelled', 'sold') NOT NULL DEFAULT 'pending',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    approved TINYINT(1) NOT NULL DEFAULT 0,
    views INT NOT NULL DEFAULT 0,
    
    -- Counts
    bid_count INT NOT NULL DEFAULT 0,
    watcher_count INT NOT NULL DEFAULT 0,
    
    -- Foreign Keys
    seller_id INT NOT NULL,
    category_id INT NULL,
    winner_id INT NULL,
    winning_bid_id INT NULL,
    
    -- Meta
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    meta_keywords VARCHAR(500) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_items_status (status),
    INDEX idx_items_featured (featured),
    INDEX idx_items_end_time (end_time),
    INDEX idx_items_seller (seller_id),
    INDEX idx_items_category (category_id),
    INDEX idx_items_slug (slug),
    FULLTEXT idx_items_search (title, description)
) ENGINE=InnoDB;

-- ============================================================
-- 5. Item Images Table
-- ============================================================
CREATE TABLE IF NOT EXISTS item_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_item_images_item (item_id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. Bids Table
-- ============================================================
CREATE TABLE IF NOT EXISTS bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    bid_amount DECIMAL(12, 2) NOT NULL,
    bid_status ENUM('active', 'outbid', 'winning', 'won', 'lost', 'cancelled') NOT NULL DEFAULT 'active',
    is_winning TINYINT(1) NOT NULL DEFAULT 0,
    is_auto_bid TINYINT(1) NOT NULL DEFAULT 0,
    max_bid_amount DECIMAL(12, 2) NULL, -- For auto-bidding
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bids_item (item_id),
    INDEX idx_bids_user (user_id),
    INDEX idx_bids_amount (bid_amount DESC),
    INDEX idx_bids_status (bid_status),
    INDEX idx_bids_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================
-- 7. Wishlist Table
-- ============================================================
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE KEY uk_wishlist (user_id, item_id),
    INDEX idx_wishlist_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. Notifications Table
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL, -- new_bid, outbid, won, lost, ending_soon, system, message
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    icon VARCHAR(50) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_global TINYINT(1) NOT NULL DEFAULT 0, -- For admin broadcast
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (user_id, is_read),
    INDEX idx_notifications_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================
-- 9. Messages Table
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    item_id INT NULL, -- Optional: for item-specific conversations
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    parent_id INT NULL, -- For threaded conversations
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_messages_sender (sender_id),
    INDEX idx_messages_receiver (receiver_id),
    INDEX idx_messages_read (receiver_id, is_read),
    INDEX idx_messages_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================
-- 10. Reviews Table
-- ============================================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT NOT NULL,
    user_id INT NOT NULL, -- The user being reviewed (seller/buyer)
    item_id INT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    INDEX idx_reviews_user (user_id),
    INDEX idx_reviews_rating (rating),
    INDEX idx_reviews_approved (is_approved)
) ENGINE=InnoDB;

-- ============================================================
-- 11. Payments Table
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    item_id INT NULL,
    bid_id INT NULL,
    
    -- Payment Info
    amount DECIMAL(12, 2) NOT NULL,
    fee DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    
    -- Payment Method
    payment_method VARCHAR(50) NULL, -- credit_card, paypal, stripe, bank_transfer
    payment_type VARCHAR(50) NOT NULL DEFAULT 'auction_win', -- auction_win, deposit, subscription, fee
    
    -- Transaction Details
    transaction_id VARCHAR(255) NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    
    -- Billing Info
    billing_name VARCHAR(100) NULL,
    billing_email VARCHAR(100) NULL,
    billing_phone VARCHAR(20) NULL,
    billing_address TEXT NULL,
    billing_city VARCHAR(100) NULL,
    billing_state VARCHAR(100) NULL,
    billing_zip VARCHAR(20) NULL,
    billing_country VARCHAR(100) NULL,
    
    -- Timestamps
    paid_at TIMESTAMP NULL,
    refunded_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    FOREIGN KEY (bid_id) REFERENCES bids(id) ON DELETE SET NULL,
    INDEX idx_payments_user (user_id),
    INDEX idx_payments_status (payment_status),
    INDEX idx_payments_invoice (invoice_no),
    INDEX idx_payments_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================
-- 12. Reports Table
-- ============================================================
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_user_id INT NULL,
    item_id INT NULL,
    report_type VARCHAR(50) NOT NULL, -- spam, fraud, inappropriate, fake, other
    description TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved', 'dismissed') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reports_status (status),
    INDEX idx_reports_type (report_type)
) ENGINE=InnoDB;

-- ============================================================
-- 13. Settings Table
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT NULL,
    `group` VARCHAR(50) NOT NULL DEFAULT 'general',
    type VARCHAR(20) NOT NULL DEFAULT 'text', -- text, textarea, image, boolean, json
    label VARCHAR(255) NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_settings_key (`key`),
    INDEX idx_settings_group (`group`)
) ENGINE=InnoDB;

-- ============================================================
-- 14. Contact Messages Table
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    replied TINYINT(1) NOT NULL DEFAULT 0,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_read (is_read),
    INDEX idx_contact_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================
-- 15. Activity Logs Table
-- ============================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NULL,
    model_type VARCHAR(100) NULL, -- e.g., App\Models\Item
    model_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_user (user_id),
    INDEX idx_activity_action (action),
    INDEX idx_activity_created (created_at DESC),
    INDEX idx_activity_model (model_type, model_id)
) ENGINE=InnoDB;

-- ============================================================
-- Insert Default Data
-- ============================================================

-- Default Admin User (password: admin123)
INSERT INTO users (name, email, password, role, status, email_verified_at) VALUES
('Admin User', 'admin@geoauction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW()),
('John Seller', 'seller@geoauction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 'active', NOW()),
('Jane Buyer', 'buyer@geoauction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', 'active', NOW());

-- Default Categories
INSERT INTO categories (name, slug, description, icon, sort_order) VALUES
('Motors', 'motors', 'Cars, bikes, boats, and vehicles', 'fa-car', 1),
('Properties', 'properties', 'Real estate, land, and properties', 'fa-building', 2),
('Prestige Plates', 'prestige-plates', 'VIP number plates and registrations', 'fa-hashtag', 3),
('Jewelry & Watches', 'jewelry-watches', 'Luxury jewelry, watches, and gems', 'fa-gem', 4),
('Electronics', 'electronics', 'Laptops, phones, gadgets, and tech', 'fa-laptop', 5),
('Art & Collectibles', 'art-collectibles', 'Art, antiques, and collectible items', 'fa-palette', 6),
('Government Surplus', 'government-surplus', 'Government and industrial surplus', 'fa-boxes-stacked', 7);

-- Default Settings
INSERT INTO settings (`key`, `value`, `group`, type, label, sort_order) VALUES
('site_name', 'Geo Auction System', 'general', 'text', 'Site Name', 1),
('site_description', 'Premium online auction platform for premium items', 'general', 'textarea', 'Site Description', 2),
('site_email', 'info@geoauction.com', 'general', 'text', 'Site Email', 3),
('site_phone', '+1-234-567-8900', 'general', 'text', 'Site Phone', 4),
('site_address', '123 Auction Street, New York, NY 10001', 'general', 'textarea', 'Site Address', 5),
('currency_symbol', '$', 'general', 'text', 'Currency Symbol', 6),
('currency_code', 'USD', 'general', 'text', 'Currency Code', 7),
('commission_rate', '5', 'auction', 'text', 'Commission Rate (%)', 8),
('min_bid_increment', '1', 'auction', 'text', 'Minimum Bid Increment ($)', 9),
('auto_extend_enabled', '1', 'auction', 'boolean', 'Auto Extend on Last Minute Bids', 10),
('auto_extend_minutes', '2', 'auction', 'text', 'Auto Extend Minutes', 11),
('max_image_size', '5', 'upload', 'text', 'Max Image Size (MB)', 12),
('allowed_image_types', 'jpg,jpeg,png,gif,webp', 'upload', 'text', 'Allowed Image Types', 13),
('smtp_host', '', 'email', 'text', 'SMTP Host', 14),
('smtp_port', '587', 'email', 'text', 'SMTP Port', 15),
('smtp_username', '', 'email', 'text', 'SMTP Username', 16),
('smtp_password', '', 'email', 'password', 'SMTP Password', 17),
('google_maps_api_key', '', 'api', 'text', 'Google Maps API Key', 18),
('enable_registration', '1', 'general', 'boolean', 'Enable User Registration', 19),
('require_email_verification', '0', 'general', 'boolean', 'Require Email Verification', 20);

