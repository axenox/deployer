-- UP

ALTER TABLE `axxdep_build`
	ADD COLUMN `composer.lock` LONGTEXT NULL DEFAULT NULL AFTER `log`;
	
-- DOWN
	
ALTER TABLE `axxdep_build`
	DROP COLUMN `composer.lock`;