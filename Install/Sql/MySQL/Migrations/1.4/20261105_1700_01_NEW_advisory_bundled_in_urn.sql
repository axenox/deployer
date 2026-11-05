/*
 * Retain the owning bundle for dependency advisories.
 *
 * DOWN preserves populated bundle URNs rather than deleting evidence.
 *
 * @author GitHub Copilot
 */
-- UP

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `axxdep_advisory` ADD `bundled_in_urn` VARCHAR(512) NULL',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_advisory' AND COLUMN_NAME = 'bundled_in_urn'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN

SET @populated = 0;
SET @sql = (
    SELECT IF(COUNT(*) > 0,
        'SELECT COUNT(*) INTO @populated FROM `axxdep_advisory` WHERE `bundled_in_urn` IS NOT NULL AND `bundled_in_urn` <> ''''',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_advisory' AND COLUMN_NAME = 'bundled_in_urn'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (
    SELECT IF(COUNT(*) > 0,
        IF(@populated > 0,
            'ALTER TABLE `axxdep_advisory` CHANGE `bundled_in_urn` `trash_bundled_in_urn` VARCHAR(512) NULL',
            'ALTER TABLE `axxdep_advisory` DROP COLUMN `bundled_in_urn`'),
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_advisory' AND COLUMN_NAME = 'bundled_in_urn'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
