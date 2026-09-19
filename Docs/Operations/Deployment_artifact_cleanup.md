# Deployment artifact cleanup

The Deployer creates two different kinds of persistent artifacts:

- Build artifacts on the build server in `data/deployer/<project_alias>/builds`.
- Release directories on the target host in `<deploy_path>/releases`.

They have separate lifecycles and cleanup rules.

## Self-deployment flow

`Recipes/SelfExtractingPHP/SelfExtractingDeployment.php` is a template for a
self-extracting deployment file. It is not executed directly on the build
server.

The `self_deployment:create` task in `Recipes/SelfDeployment.php`:

1. Copies the template into the project's `builds` directory.
2. Replaces placeholders such as deployment paths, shared directories and
   `keep_releases`.
3. Appends the build's `.tar.gz` archive to the PHP template.
4. Produces a host-specific `.phx` file.

The `.phx` is transferred to the target host and executed there with PHP. The
script creates a release directory, extracts its appended archive, prepares
configuration and shared links, switches the `current` link, runs the app
installers and finally removes old releases.

The original reusable build artifact is the `.tar.gz` file. Azure deployment
instructions may temporarily wrap the `.phx` in a ZIP for transport, but that
ZIP is not the stored source build.

## Release cleanup on the target host

The target layout is:

```
<deploy_path>/
|-- .dep/
|   `-- releases
|-- current
|-- releases/
`-- shared/
```

`.dep/releases` is an append-only list of successfully completed releases. For
example, if the generated deployment has `basic_deploy_path=/local_host_pui_one`
and an empty `relative_deploy_path`, the file is:

```
/local_host_pui_one/.dep/releases
```

### Original failure mode

The self-deployment script creates the new release directory near the start of
the operation, but only adds it to `.dep/releases` near the end. A process
termination or an error outside the old `Exception` handler could therefore
leave an unregistered directory behind.

The regular cleanup derived its deletion candidates from `.dep/releases`.
Consequently, an unregistered failed release was ignored by every later
cleanup and could remain indefinitely.

### Implemented behavior

`SelfExtractingDeployment.php` now:

- catches every `Throwable` in the outer rollback path;
- checks whether deletion of the failed release succeeded and reports failure;
- verifies that the successful release was written to `.dep/releases`;
- removes all successful releases beyond `keep_releases`;
- also removes release directories not represented by the retained release
  log when they are older than 24 hours;
- always protects the release currently being deployed.

The 24-hour threshold prevents a cleanup from deleting a directory belonging
to another deployment that is still running. When `keep_releases` is `-1`,
release cleanup remains disabled, including cleanup of unregistered releases.

### Diagnosis

On the target host, compare the directory names with the second CSV field in
the release log:

```
ls -lt <deploy_path>/releases
cat <deploy_path>/.dep/releases
readlink <deploy_path>/current
```

A directory missing from `.dep/releases` is normally a failed or interrupted
release. It is removed by the next successful deployment after it is at least
24 hours old.

## OTA `.phx` cleanup on the build server

OTA recipes publish a host-specific file such as:

```
data/deployer/<project_alias>/builds/<build_name>_<host_alias>.phx
```

Keeping this file after a confirmed download duplicates almost the complete
`.tar.gz` build and can consume significant storage.

### Download confirmation

Deletion must not happen when the GET response is created. At that point the
HTTP body has not necessarily reached the target host.

The implemented protocol binds the download and its confirmation to one
deployment:

1. `DeployerFacade` selects a deployment in status 60 and reserves it as
   status 62 before streaming its `.phx`.
2. The response includes `X-Exface-Deployment-Uid`.
3. `UpdateDownloader` stores this UID from both regular Guzzle responses and
   responses reconstructed from CLI cURL headers.
4. Subsequent log POSTs include it as `deployment_uid`.
5. After the target has saved and size-checked the file, it reports status
   `65` (`Downloaded`).
6. `DeployerFacade` resolves that exact deployment and deletes its `.phx`.
   Status `70` (`Running .phx`) retries deletion if the first attempt failed.

The server only deletes the file if the confirmed deployment is still the
latest published deployment for that host. This prevents a delayed
confirmation from deleting a newly generated file with the same build and
host-based filename.

Deployments in status 65 or later are no longer offered by the download GET.
The source `.tar.gz` remains available, so an explicit redeployment can
generate a new `.phx`.

Older PackageManager clients do not send `deployment_uid`. Their status
updates continue to work, but the server deliberately keeps the `.phx`
because it cannot safely identify the file confirmed by that client.

### Concurrent update requests

Manual and scheduled self-updates can request the same update at nearly the
same time. The facade prevents duplicate downloads as follows:

1. Requests for the same project and host are guarded by a short-lived,
   non-blocking lock on the build server. A concurrent request immediately
   receives `304 No update available`.
2. While holding the lock, the facade searches for an active deployment with
   status 62 or greater and less than 80.
3. If one exists, the request receives `304 No update available`, including
   requests made with `redeploy=true`.
4. Otherwise, only a deployment in status 60 can be selected and changed to
   status 62.
5. The lock is released after the response and reservation have been created;
   it is not held for the duration of the file transfer.

The existing status calculation remains responsible for abandoned
deployments: a state from 62 through 80 with no update for five minutes is
read as status 80 (`Lost connection`). Status 80 is not considered active, so
a later request may proceed or explicitly redeploy.

## Downloaded `.phx` cleanup on the target host

The PackageManager stores OTA packages in:

```
<installation>/<SELF_UPDATE.LOCAL.DOWNLOAD_PATH>
```

An empty `SELF_UPDATE.LOCAL.DOWNLOAD_PATH` means the installation root.
`UpdateDownloader` cleans this directory after a new `.phx` has been
completely written and its size has been validated:

- the package downloaded by the current operation is retained;
- every other `*.phx` file in the same directory is removed;
- unrelated files are never considered;
- cleanup applies to both Guzzle and CLI cURL downloads;
- deletion failures are logged and reported as warnings without turning a
  successful download into a failed deployment.

Cleanup does not run after a failed download or a `304 No update available`
response. The previous package therefore remains available until a newer
package has been downloaded successfully. With `install=false`, the latest
package is retained for manual installation.

### Relevant implementation files

- `Facades/DeployerFacade.php`
- `Recipes/SelfDeployment.php`
- `Recipes/SelfExtractingPHP/SelfExtractingDeployment.php`
- `Recipes/Deploy/LocalBldUpdaterPull.php`
- `Recipes/Deploy/LocalBldUpdaterCron.php`
- PackageManager: `Common/Updater/UpdateDownloader.php`
- PackageManager: `Actions/SelfUpdate.php`
