-- UP

ALTER TABLE `axxdep_build_variant`
	CHANGE COLUMN `name` `name` VARCHAR(100) NOT NULL COLLATE 'utf8_general_ci' AFTER `modified_by_user_oid`,
	ADD COLUMN `description` TEXT NULL AFTER `name`;

-- DOWN

ALTER TABLE `axxdep_build_variant`
	CHANGE COLUMN `name` `name` VARCHAR(50) NOT NULL COLLATE 'utf8_general_ci' AFTER `modified_by_user_oid`,
	DROP COLUMN `description`;