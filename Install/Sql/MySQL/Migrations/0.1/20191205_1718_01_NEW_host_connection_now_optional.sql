-- UP

ALTER TABLE `axxdep_host`
	CHANGE COLUMN `data_connection_oid` `data_connection_oid` BINARY(16) NULL AFTER `name`;

-- DOWN

ALTER TABLE `axxdep_host`
	CHANGE COLUMN `data_connection_oid` `data_connection_oid` BINARY(16) NOT NULL AFTER `name`;