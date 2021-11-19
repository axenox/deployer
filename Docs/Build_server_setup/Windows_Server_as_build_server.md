# Setting up a build server on Windows Server

## Install SSH

TODO

## Install the deployer app

TODO

## Configure the web server to run as an administrator user

In order for SSH-based deployments to work properly, PHP must be run by a named user with sufficient priviledges (referred to as the PHP-user in the following). In most cases, a local admin is a good option.

1. Create a local admin user in windows with regular password authentication (e.g. `wampuser`)
2. Open the Windows Service Manager (`services.msc`)
3. Find and open your web server service (e.g. `wampapache64` if using the WAMP server) 
4. In the tab `Log on` select `This account` and specify the user created above (e.g. `./wampuser`)
5. Click `OK`
6. Restart the service

Now whenever the service will be started automatically it will be run by its dedicated user. You can now use any user-specific configuration, cache or credential storage of windows for your build server explicitly.

## Access private Git repos

If you need the build server to access private Git repos (e.g. GitLab), you need to store credentials like access tokens somewhere in order for the build server to gain access to the repos.

### Using Composer (for each build variant separately)

You can store credentials for every build variant separately by putting them in the `auth.json` section of the variant. These credentials will be used by PHP Composer directly. Available options are described in the [official documentation of Composer](https://getcomposer.org/doc/articles/authentication-for-private-packages.md).

### Using Git credential storage (globally on the server)

**NOTE:** depending on the configuration of Git on the build server, this may or may not work properly!

This is an alternative to using composer-authentication, which is configurable within the deployers build variant. Using regular Git authentication is more native (to Git), but it requires Get to be set up in a way, that credentials are stored globally or at least for the dedicated PHP user (see above).

In most cases it is enough to clone a repo manually in the name of the PHP-user:

1. Open `cmd`
2. Type `runas /user:wampuser cmd` to open a new cmd-windows as the PHP-user (named `wampuser` in our example)
3. Run `git clone your-repo ...` or any other git command that requires authentication and follow the required steps. 

The credentials will be saved for the PHP-user and can now be used by the deployer without any additional configuration.