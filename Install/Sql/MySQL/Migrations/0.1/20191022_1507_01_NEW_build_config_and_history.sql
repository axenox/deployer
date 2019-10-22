-- UP

ALTER TABLE `axxdep_host`
	ADD COLUMN `php_cli` VARCHAR(100) NULL DEFAULT NULL AFTER `operating_system`;
	
ALTER TABLE `axxdep_project`
	CHANGE COLUMN `project_group_oid` `project_group_oid` BINARY(16) NULL AFTER `alias`;

CREATE TABLE IF NOT EXISTS `axxdep_build` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `project_oid` binary(16) NOT NULL,
  `version` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `build_recipe_path` varchar(100) DEFAULT NULL,
  `comment` varchar(100) DEFAULT NULL,
  `composer_json` longtext,
  `composer_auth_json` longtext,
  `notes` varchar(500) DEFAULT NULL,
  `log` longtext,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `axxdep_deployment` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `build_oid` binary(16) NOT NULL,
  `host_oid` binary(16) NOT NULL,
  `log` longtext,
  `error_flag` tinyint(1) DEFAULT '0',
  `deploy_recipe_file` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
COMMIT;


-- DOWN

ALTER TABLE `axxdep_host` DROP `php_cli`;

ALTER TABLE `axxdep_project`
	CHANGE COLUMN `project_group_oid` `project_group_oid` BINARY(16) NOT NULL AFTER `alias`;
	
DROP TABLE `axxdep_deployment`;
DROP TABLE `axxdep_build`;