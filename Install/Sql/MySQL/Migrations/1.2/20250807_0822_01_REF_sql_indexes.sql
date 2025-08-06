-- UP

ALTER TABLE `axxdep_deployment`
    ADD FOREIGN KEY (`build_oid`) REFERENCES `axxdep_build` (`oid`);
ALTER TABLE `axxdep_deployment`
    ADD FOREIGN KEY (`host_oid`) REFERENCES `axxdep_host` (`oid`);
ALTER TABLE `axxdep_deployment`
    ADD INDEX `host_oid_status_started_on` (`host_oid`, `status`, `started_on`);
ALTER TABLE `axxdep_host`
    ADD FOREIGN KEY (`project_oid`) REFERENCES `axxdep_project` (`oid`);
ALTER TABLE `axxdep_host`
    ADD FOREIGN KEY (`stage_oid`) REFERENCES `axxdep_stage` (`oid`);
ALTER TABLE `axxdep_project`
    ADD FOREIGN KEY (`project_group_oid`) REFERENCES `axxdep_project_group` (`oid`);
ALTER TABLE `axxdep_build`
    ADD FOREIGN KEY (`project_oid`) REFERENCES `axxdep_project` (`oid`);
ALTER TABLE `axxdep_build_variant`
    ADD FOREIGN KEY (`project_oid`) REFERENCES `axxdep_project` (`oid`);

-- DOWN