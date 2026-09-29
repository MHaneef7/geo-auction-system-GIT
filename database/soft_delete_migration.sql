-- =============================================================
-- Soft-Delete & Audit Trail Schema Migration for Items
-- =============================================================

ALTER TABLE `items`
ADD COLUMN IF NOT EXISTS `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL DEFAULT NULL AFTER `is_deleted`,
ADD COLUMN IF NOT EXISTS `deleted_by` INT(11) NULL DEFAULT NULL AFTER `deleted_at`,
ADD INDEX IF NOT EXISTS `idx_items_is_deleted` (`is_deleted`);
