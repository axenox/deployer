--
-- Tabellenstruktur für Tabelle `axxdep_host`
--

DROP TABLE IF EXISTS `axxdep_host`;
CREATE TABLE IF NOT EXISTS `axxdep_host` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL DEFAULT '',
  `data_connection_oid` binary(16) NOT NULL,
  `project_oid` binary(16) NOT NULL,
  `stage_oid` binary(16) NOT NULL,
  `path_abs_to_api` varchar(100) NOT NULL DEFAULT '',
  `path_rel_to_releases` varchar(100) NOT NULL DEFAULT '',
  `operating_system` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `axxdep_project`
--

DROP TABLE IF EXISTS `axxdep_project`;
CREATE TABLE IF NOT EXISTS `axxdep_project` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `project_group_oid` binary(16) NOT NULL,
  `deployment_recipe` varchar(100) NOT NULL,
  `deployment_recipe_custom_path` varchar(100) DEFAULT NULL,
  `build_recipe` varchar(100) NOT NULL,
  `build_recipe_custom_path` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `axxdep_project_group`
--

DROP TABLE IF EXISTS `axxdep_project_group`;
CREATE TABLE IF NOT EXISTS `axxdep_project_group` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `axxdep_stage`
--

DROP TABLE IF EXISTS `axxdep_stage`;
CREATE TABLE IF NOT EXISTS `axxdep_stage` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

--
-- Daten für Tabelle `axxdep_stage`
--

INSERT IGNORE INTO `axxdep_stage` (`oid`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`, `name`) VALUES
(0x11e9e101ee250aa9bacde4b318306b9a, '2019-09-27 08:36:41', '2019-09-27 09:39:54', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, 'Testing'),
(0x11e9e101f1331292bacde4b318306b9a, '2019-09-27 08:36:47', '2019-09-27 09:40:00', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, 'Development'),
(0x11e9e101f4251591bacde4b318306b9a, '2019-09-27 08:36:51', '2019-09-27 09:40:12', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, 'Integration'),
(0x11e9e101f6dd8acdbacde4b318306b9a, '2019-09-27 08:36:56', '2019-09-27 09:40:19', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000, 'Production');
COMMIT;
