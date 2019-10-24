-- UP

ALTER TABLE `axxdep_project`
	ADD COLUMN `default_composer_json` LONGTEXT NULL DEFAULT NULL AFTER `build_recipe_custom_path`,
	ADD COLUMN `default_config` TEXT NULL DEFAULT NULL AFTER `default_composer_json`,
	ADD COLUMN `default_composer_auth_json` TEXT NULL AFTER `default_composer_json`;

ALTER TABLE `axxdep_build`
	CHANGE COLUMN `status` `status` INT(2) NOT NULL AFTER `name`;
	
ALTER TABLE `axxdep_deployment`
	ADD COLUMN `status` INT(2) NOT NULL AFTER `deploy_recipe_file`,
	ADD COLUMN `started_on` DATETIME NULL AFTER `host_oid`,
	ADD COLUMN `completed_on` DATETIME NULL AFTER `started_on`,
	DROP COLUMN `error_flag`;


-- DOWN

ALTER TABLE `axxdep_project` 
	DROP `default_composer_json`,
	DROP `default_composer_auth_json`,
	DROP `default_config`;
	
ALTER TABLE `axxdep_build` DROP `status`;

ALTER TABLE `axxdep_deployment`
	DROP `status`,
	DROP `started_on`,
	DROP `completed_on`,
	ADD COLUMN `error_flag` INT(1) NOT NULL DEFAULT 0 AFTER `deploy_recipe_file`,