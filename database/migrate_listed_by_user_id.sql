-- =============================================================================
-- RealEstateAI — Migration: seller_id → listed_by_user_id
-- Safe rename preserving existing property ownership IDs.
-- =============================================================================

USE `realestate_ai`;

-- Drop old FK before renaming the column.
ALTER TABLE `properties`
    DROP FOREIGN KEY `fk_properties_seller`;

-- Rename ownership column (data preserved).
ALTER TABLE `properties`
    CHANGE COLUMN `seller_id` `listed_by_user_id` INT UNSIGNED NOT NULL;

-- Replace index name to match the new column.
ALTER TABLE `properties`
    DROP INDEX `idx_properties_seller_id`,
    ADD KEY `idx_properties_listed_by_user_id` (`listed_by_user_id`);

-- Recreate FK to users.user_id with the same lifecycle rules.
ALTER TABLE `properties`
    ADD CONSTRAINT `fk_properties_listed_by_user`
        FOREIGN KEY (`listed_by_user_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;
