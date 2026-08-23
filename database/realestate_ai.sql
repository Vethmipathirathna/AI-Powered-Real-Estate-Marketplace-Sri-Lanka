-- =============================================================================
-- RealEstateAI — Initial MySQL Schema
-- Project: Intelligent Real Estate Marketplace System with Automated House
--          Price Estimation Using AI for Sri Lanka
-- Engine:  MySQL / MariaDB (XAMPP)
-- Charset: utf8mb4 / utf8mb4_unicode_ci
--
-- Import via phpMyAdmin or:
--   mysql -u root < database/realestate_ai.sql
--
-- This file creates the database foundation only.
-- No authentication, CRUD, or AI model logic is included.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `realestate_ai`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `realestate_ai`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Table: users
-- Canonical account identity for BUYER, SELLER, and ADMIN roles.
-- Passwords must be stored as hashes only (never plain text).
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `ai_predictions`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `property_images`;
DROP TABLE IF EXISTS `properties`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
    `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NULL DEFAULT NULL,
    `role` ENUM('BUYER', 'SELLER', 'ADMIN') NOT NULL,
    `status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: properties
-- Marketplace listings owned/listed by SELLER or ADMIN users.
-- Ownership column: listed_by_user_id → users.user_id
-- Includes attributes needed for listing display and future AI estimation.
-- -----------------------------------------------------------------------------
CREATE TABLE `properties` (
    `property_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listed_by_user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `district` VARCHAR(100) NOT NULL,
    `area` VARCHAR(150) NULL DEFAULT NULL,
    `address` VARCHAR(255) NULL DEFAULT NULL,
    `property_type` ENUM('HOUSE', 'APARTMENT', 'LAND', 'COMMERCIAL') NOT NULL,
    `perch` DECIMAL(10, 2) NULL DEFAULT NULL,
    `bedrooms` TINYINT UNSIGNED NULL DEFAULT NULL,
    `bathrooms` TINYINT UNSIGNED NULL DEFAULT NULL,
    `kitchen_area_sqft` DECIMAL(10, 2) NULL DEFAULT NULL,
    `parking_spots` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `has_garden` TINYINT(1) NOT NULL DEFAULT 0,
    `has_ac` TINYINT(1) NOT NULL DEFAULT 0,
    `water_supply` TINYINT(1) NOT NULL DEFAULT 0,
    `electricity` TINYINT(1) NOT NULL DEFAULT 0,
    `floors` TINYINT UNSIGNED NULL DEFAULT NULL,
    `year_built` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `asking_price_lkr` DECIMAL(15, 2) NOT NULL,
    `status` ENUM('AVAILABLE', 'PENDING', 'SOLD', 'INACTIVE') NOT NULL DEFAULT 'PENDING',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`property_id`),
    KEY `idx_properties_listed_by_user_id` (`listed_by_user_id`),
    KEY `idx_properties_district` (`district`),
    KEY `idx_properties_type` (`property_type`),
    KEY `idx_properties_status` (`status`),
    KEY `idx_properties_asking_price` (`asking_price_lkr`),
    CONSTRAINT `fk_properties_listed_by_user`
        FOREIGN KEY (`listed_by_user_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: property_images
-- File-path references only (no binary image storage in MySQL).
-- One property may have many images; one may be marked primary.
-- -----------------------------------------------------------------------------
CREATE TABLE `property_images` (
    `image_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `property_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`image_id`),
    KEY `idx_property_images_property_id` (`property_id`),
    KEY `idx_property_images_primary` (`property_id`, `is_primary`),
    CONSTRAINT `fk_property_images_property`
        FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: favorites
-- Saved properties for authenticated users (typically buyers).
-- UNIQUE(user_id, property_id) prevents duplicate saves.
-- -----------------------------------------------------------------------------
CREATE TABLE `favorites` (
    `favorite_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `property_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`favorite_id`),
    UNIQUE KEY `uq_favorites_user_property` (`user_id`, `property_id`),
    KEY `idx_favorites_property_id` (`property_id`),
    CONSTRAINT `fk_favorites_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_favorites_property`
        FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: messages
-- User-to-user communication, optionally linked to a property listing.
-- Buyers may contact the property lister (SELLER or ADMIN) via receiver_id.
-- -----------------------------------------------------------------------------
CREATE TABLE `messages` (
    `message_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_id` INT UNSIGNED NOT NULL,
    `receiver_id` INT UNSIGNED NOT NULL,
    `property_id` INT UNSIGNED NULL DEFAULT NULL,
    `message_text` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`),
    KEY `idx_messages_sender_id` (`sender_id`),
    KEY `idx_messages_receiver_id` (`receiver_id`),
    KEY `idx_messages_property_id` (`property_id`),
    KEY `idx_messages_created_at` (`created_at`),
    CONSTRAINT `fk_messages_sender`
        FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_messages_receiver`
        FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_messages_property`
        FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: ai_predictions
-- Stores future AI price-estimation input snapshots and predicted prices.
-- Does not run any model; history only.
-- -----------------------------------------------------------------------------
CREATE TABLE `ai_predictions` (
    `prediction_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `district` VARCHAR(100) NOT NULL,
    `area` VARCHAR(150) NULL DEFAULT NULL,
    `perch` DECIMAL(10, 2) NULL DEFAULT NULL,
    `bedrooms` TINYINT UNSIGNED NULL DEFAULT NULL,
    `bathrooms` TINYINT UNSIGNED NULL DEFAULT NULL,
    `kitchen_area_sqft` DECIMAL(10, 2) NULL DEFAULT NULL,
    `parking_spots` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `has_garden` TINYINT(1) NOT NULL DEFAULT 0,
    `has_ac` TINYINT(1) NOT NULL DEFAULT 0,
    `water_supply` TINYINT(1) NOT NULL DEFAULT 0,
    `electricity` TINYINT(1) NOT NULL DEFAULT 0,
    `floors` TINYINT UNSIGNED NULL DEFAULT NULL,
    `year_built` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `predicted_price_lkr` DECIMAL(15, 2) NOT NULL,
    `model_version` VARCHAR(50) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`prediction_id`),
    KEY `idx_ai_predictions_user_id` (`user_id`),
    KEY `idx_ai_predictions_created_at` (`created_at`),
    KEY `idx_ai_predictions_district` (`district`),
    CONSTRAINT `fk_ai_predictions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- End of RealEstateAI schema
-- =============================================================================
