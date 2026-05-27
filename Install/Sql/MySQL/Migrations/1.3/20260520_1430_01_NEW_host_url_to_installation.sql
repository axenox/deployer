/*
 * Add installation URL to deployer hosts.
 *
 * Adds a nullable URL field for storing a url link to the installation.
 * This SQL only executes the ALTER TABLE statement if the column does not already exist, 
 * making it safe to run multiple times without causing errors.
 *
 * @author sergej.riel
 */
-- UP

SET @sql = (
    SELECT IF(
                COUNT(*) = 0,
                'ALTER TABLE `axxdep_host` ADD COLUMN `url_to_installation` varchar(1000) NULL',
                'SELECT 1'
           )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_host'
      AND COLUMN_NAME = 'url_to_installation'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DOWN

SET @sql = (
    SELECT IF(
                COUNT(*) > 0,
                'ALTER TABLE `axxdep_host` DROP COLUMN `url_to_installation`',
                'SELECT 1'
           )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'axxdep_host'
      AND COLUMN_NAME = 'url_to_installation'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;