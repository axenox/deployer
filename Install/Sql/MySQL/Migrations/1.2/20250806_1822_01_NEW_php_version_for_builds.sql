-- UP

ALTER TABLE `axxdep_build`
    ADD COLUMN `php_version` VARCHAR(10) NULL AFTER `notes`;

-- DOWN