-- UP

CREATE TABLE IF NOT EXISTS `axxdep_build_variant` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `project_oid` binary(16) NOT NULL,
  `composer_json` longtext NOT NULL,
  `composer_auth_json` longtext,
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

INSERT INTO axxdep_build_variant (
	project_oid,
	`name`,
	composer_json,
	composer_auth_json,
	oid,
	created_on,
	created_by_user_oid,
	modified_on,
	modified_by_user_oid
) SELECT 
		lb.project_oid,
		CONCAT(SUBSTRING_INDEX(lb.`version`,'.',1), '.x') AS `name`,
		lb.composer_json,
		lb.composer_auth_json,
		lb.oid,
		lb.created_on,
		lb.created_by_user_oid,
		lb.modified_on,
		lb.modified_by_user_oid
	FROM
		(
			SELECT 
				b1.*
			FROM axxdep_build b1 
				LEFT JOIN axxdep_build b2 ON (SUBSTRING_INDEX(b1.`version`,'.',1) = SUBSTRING_INDEX(b2.`version`,'.',1) AND b1.project_oid = b2.project_oid AND b1.created_on < b2.created_on)
			WHERE b2.oid IS NULL
		) lb;
	
ALTER TABLE `axxdep_build`
	ADD COLUMN `build_variant_oid` BINARY(16) NULL AFTER `project_oid`;
	
UPDATE axxdep_build b SET build_variant_oid = (
	SELECT oid 
	FROM axxdep_build_variant bv 
	WHERE 
		b.project_oid = bv.project_oid
		AND SUBSTRING_INDEX(bv.`name`, '.', 1) = SUBSTRING_INDEX(b.`version`, '.', 1)
);
	
ALTER TABLE `axxdep_build`
	CHANGE COLUMN `build_variant_oid` `build_variant_oid` BINARY(16) NOT NULL AFTER `project_oid`;
	
ALTER TABLE `axxdep_project`
	DROP COLUMN `default_composer_json`,
	DROP COLUMN `default_composer_auth_json`,
	DROP COLUMN `default_config`;

-- DOWN

ALTER TABLE `axxdep_project`
	ADD COLUMN `default_composer_json` LONGTEXT NULL DEFAULT NULL AFTER `build_recipe_custom_path`,
	ADD COLUMN `default_config` TEXT NULL DEFAULT NULL AFTER `default_composer_json`,
	ADD COLUMN `default_composer_auth_json` TEXT NULL AFTER `default_composer_json`;
	
ALTER TABLE `axxdep_build`
	DROP COLUMN `build_variant_oid`;
	
DROP TABLE IF EXISTS `axxdep_build_variant`;