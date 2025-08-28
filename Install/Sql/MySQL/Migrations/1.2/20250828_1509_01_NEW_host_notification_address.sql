-- UP

ALTER TABLE `axxdep_host`
    ADD `notification_address` varchar(1000) NULL;

-- DOWN

ALTER TABLE `axxdep_host`
    DROP COLUMN `notification_address`;