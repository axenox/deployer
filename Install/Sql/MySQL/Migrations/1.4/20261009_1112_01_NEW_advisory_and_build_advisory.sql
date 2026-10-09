/*
 * Add security advisories and their assignments to builds.
 *
 * The DOWN migration preserves non-empty tables by renaming them with a
 * trash_ prefix instead of deleting their data.
 *
 * @author OpenAI
 */
-- UP

-- Store advisories reported for deployed packages.
CREATE TABLE IF NOT EXISTS `axxdep_advisory` (
    `oid` binary(16) NOT NULL,
    `created_on` datetime NOT NULL,
    `modified_on` datetime NOT NULL,
    `created_by_user_oid` binary(16) NOT NULL,
    `modified_by_user_oid` binary(16) NOT NULL,
    `name` varchar(100) NOT NULL,
    `public_id` varchar(50) NULL,
    `type` varchar(20) NOT NULL,
    `level` tinyint NOT NULL,
    `description` text,
    `remediation` text,
    `package` varchar(100) DEFAULT NULL,
    `details_url` varchar(500) DEFAULT NULL,
    `versions_affected` varchar(100) DEFAULT NULL,
    `version_fixed` varchar(50) DEFAULT NULL,
    PRIMARY KEY (`oid`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

-- Assign advisories to builds.
CREATE TABLE IF NOT EXISTS `axxdep_build_advisory` (
    `oid` binary(16) NOT NULL,
    `created_on` datetime NOT NULL,
    `modified_on` datetime NOT NULL,
    `created_by_user_oid` binary(16) NOT NULL,
    `modified_by_user_oid` binary(16) NOT NULL,
    `build_id` binary(16) NOT NULL,
    `advisory_id` binary(16) NOT NULL,
    PRIMARY KEY (`oid`),
    INDEX `IDX_axxdep_build_advisory_build` (`build_id`),
    INDEX `IDX_axxdep_build_advisory_advisory` (`advisory_id`),
    CONSTRAINT `FK_axxdep_build_advisory_build`
        FOREIGN KEY (`build_id`) REFERENCES `axxdep_build` (`oid`),
    CONSTRAINT `FK_axxdep_build_advisory_advisory`
        FOREIGN KEY (`advisory_id`) REFERENCES `axxdep_advisory` (`oid`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

-- DOWN

-- Remove the foreign keys before dropping or preserving the mapping table.
SET @sql = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE `axxdep_build_advisory` DROP FOREIGN KEY `FK_axxdep_build_advisory_build`',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_build_advisory'
      AND CONSTRAINT_NAME = 'FK_axxdep_build_advisory_build'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE `axxdep_build_advisory` DROP FOREIGN KEY `FK_axxdep_build_advisory_advisory`',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_build_advisory'
      AND CONSTRAINT_NAME = 'FK_axxdep_build_advisory_advisory'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Determine whether the mapping table contains data.
SET @table_rows = 0;
SET @sql = (
    SELECT IF(
        COUNT(*) > 0,
        'SELECT COUNT(*) INTO @table_rows FROM `axxdep_build_advisory`',
        'SELECT 0 INTO @table_rows'
    )
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_build_advisory'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Preserve populated mapping data under its original name with trash_ prefix.
SET @sql = (
    SELECT CASE
        WHEN COUNT(*) = 0 THEN 'SELECT 1'
        WHEN @table_rows = 0 THEN
            'DROP TABLE `axxdep_build_advisory`'
        ELSE
            'RENAME TABLE `axxdep_build_advisory` TO `trash_axxdep_build_advisory`'
    END
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_build_advisory'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Determine whether the advisory table contains data.
SET @table_rows = 0;
SET @sql = (
    SELECT IF(
        COUNT(*) > 0,
        'SELECT COUNT(*) INTO @table_rows FROM `axxdep_advisory`',
        'SELECT 0 INTO @table_rows'
    )
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_advisory'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Preserve populated advisories under their original name with trash_ prefix.
SET @sql = (
    SELECT CASE
        WHEN COUNT(*) = 0 THEN 'SELECT 1'
        WHEN @table_rows = 0 THEN 'DROP TABLE `axxdep_advisory`'
        ELSE 'RENAME TABLE `axxdep_advisory` TO `trash_axxdep_advisory`'
    END
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_advisory'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;