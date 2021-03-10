-- UP

ALTER TABLE `axxdep_build`
	ADD COLUMN `composer_lock` LONGTEXT NULL DEFAULT NULL AFTER `log`;
	
-- DOWN
	
ALTER TABLE `axxdep_build`
	DROP COLUMN `composer_lock`;