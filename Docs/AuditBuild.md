# Auditing Saved Builds

Use `axenox.Deployer.AuditBuild` on one or more `axenox.Deployer.build` rows
to collect advisories for existing builds, including already deployed builds.
The action scans each build's saved `composer_lock`, not the current installation.
No deployment status filter is applied: the caller selects the builds to audit.

For example, add this button to a build table:

```json
{
    "widget_type": "Button",
    "caption": "Audit builds",
    "action_alias": "axenox.Deployer.AuditBuild"
}
```

Input must contain build UIDs. A DataCollector reads missing `composer_lock`
values. Missing UIDs or empty locks fail before any scanner is called. Malformed
locks and scanner failures propagate as errors; they do not trigger an audit of
the current installation instead.

## Persistence

For each build, the action calls `axenox.PackageManager.Audit` through the normal
action lifecycle with a fresh task containing only that build's lock. Its findings
are saved through DataSheets as `axenox.Deployer.advisory` records. Their persisted
UIDs are linked to the build using `axxdep_build` and `axxdep_advisory` on
`axenox.Deployer.build_advisory`. Both writes share the action's transaction.
The returned DataSheet contains the persisted build-advisory links; the result
message contains the summary. The action logbook contains each build's scanner
status, hints and findings table, followed by the summary.

The advisory mapping preserves `level`, `name`, `type`, `package`, `details_url`,
`description`, `remediation`, `versions_affected`, `version_fixed` and `public_id`.
Audit's deterministic `ID` is not used as a database UID. Scanner provenance and
installed versions are not saved because the destination objects have no such
attributes. A zero-finding audit creates no records. Scanner hints remain visible:
zero findings alone do not prove that all scanners completed.

There is no custom deduplication or deletion of previous findings. Repeated audits
create new advisories and links until deduplication is configured in the model.
A future `PreventDuplicatesBehavior` on advisories should use duplicate **update**
handling so every submitted finding returns its existing or newly created UID.
Duplicate **ignore** handling that drops rows cannot supply all required links.
Deduplicating build links can be configured separately on the build/advisory pair.
Previously saved links remain even when a later scan no longer reports a finding.

## Prerequisites

PackageManager must be installed, and the advisory objects and their backing
tables must be installed and writable. Scanner configuration, executable and
network requirements follow the [PackageManager audit guide](../../packagemanager/Docs/Audit.md).
AuditBuild never installs scanner prerequisites automatically. Normal action
authorization and DataSheet behaviors apply.

## Tests

From the containing installation:

```console
php vendor/bin/phpunit -c vendor/axenox/deployer/phpunit.xml.dist --testsuite unit
```

Standalone installations use `php vendor/bin/phpunit -c phpunit.xml.dist`.
Unit tests cover prototype defaults and the model-aligned advisory mapping without
a database or mocked Workbench. Application-level validation requires a persisted
metamodel and backing tables: select multiple builds, audit them, verify each
advisory link, then repeat with the desired duplicate behavior. Also check empty
locks, clean scans, scanner failure and transaction rollback in that environment.