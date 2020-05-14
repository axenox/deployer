-- UP

ALTER TABLE `axxdep_host`
	ADD COLUMN `deploy_config` MEDIUMTEXT NULL DEFAULT NULL AFTER `php_cli`;

UPDATE axxdep_host h SET h.deploy_config = (SELECT p.default_config FROM axxdep_project p WHERE p.oid = h.project_oid);

ALTER TABLE `axxdep_project`
	DROP COLUMN `default_config`;
	
-- DOWN
	
ALTER TABLE `axxdep_project`
	ADD COLUMN `default_config` TEXT NULL DEFAULT NULL AFTER `default_composer_auth_json`;
	
UPDATE axxdep_project p SET p.default_config = (SELECT h.deploy_config FROM axxdep_host h WHERE p.oid = h.project_oid LIMIT 1);
	
ALTER TABLE `axxdep_host`
	DROP COLUMN `deploy_config`;