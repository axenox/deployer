-- UP

ALTER TABLE `axxdep_project` ADD COLUMN `alias` VARCHAR(50) NOT NULL AFTER `name`;
UPDATE axxdep_project  SET alias = LOWER(REPLACE(`name`, ' ', '_'));

-- DOWN

ALTER TABLE `axxdep_project` DROP `alias`;