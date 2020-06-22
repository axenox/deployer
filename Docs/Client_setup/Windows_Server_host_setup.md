# Setting up Windows Server hosts as deployment targets

**IMPORTANT:** all of the PowerShell commands must be performed in **Administrator mode**!!!

## Before you start

Make sure your web server (i.e. Apache/WAMP or IIS) and PHP are properly configured. See [core installation guide](https://github.com/ExFace/Core/blob/1.x-dev/Docs/Installation/index.md) for details.

## 1. Install OpenSSH

**NOTE:** on Windows Server 2019 and later OpenSSH is supposed to be built in. Try the [official tutorial](https://docs.microsoft.com/en-us/windows-server/administration/openssh/openssh_install_firstuse) from Microsoft. 

1. Download [OpenSSH from GitHub](https://github.com/PowerShell/Win32-OpenSSH/releases). Choose latest 64bit version in most cases.
2. Extract archive to `C:\Program Files`.
3. Open PowerShell as administrator and run `'C:\Program Files\OpenSSH-Win64\install-sshd.ps1'` to install the services
4. Open `Services` (e.g. via `run` > `services.msc`. 
    1. Find `OpenSSH SSH Server` and open it
    2. set `Startup type` to `Automatic`
    3. press `Apply`
    4. Press `Start` to start the service right now.
    5. Do the same for the service `OpenSS SSH Authentication Agent`.
5. Run the following commands PowerShell one-after-another and answer all questions with `Y`:

```
New-NetFirewallRule -Name sshd -DisplayName 'OpenSSH SSH Server' -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22
'C:\Program Files\OpenSSH-Win64\FixHostFilePermissions.ps1'
'C:\Program Files\OpenSSH-Win64\FixUserFilePermissions.ps1'
```

## 2. Generate an SSH key pair

1. Open PowerShell and run `'C:\Program Files\OpenSSH-Win64\ssh-keygen.exe' -t rsa -b 4096`
    - don't use a passphrase!!!
    - it does not matter, where you place the keys. Just remember the path.
    - this will generate 2 files: `id_rsa` (private key) and `id_rsa.pub` (public key)
2. Add the contents of the public key as a new line to the file `C:\ProgramData\ssh\administrators_authorized_keys`.
    - If the file does not exist yet, simply copy the public key file via `Copy-Item C:\Users\<username>\.ssh\id_rsa.pub C:\ProgramData\ssh\administrators_authorized_keys` in PowerShell
3. Place the contents of the private key files (´id_rsa´) in the configuration of your host's data connection in the deployer app.
4. Restart the SSH services.

**NOTE:** when copying the private key to the data connection configuration, make sure to copy **all** the contents of the file! Do not remove the trailing linebreak! 

**NOTE:** the file `administrators_authorized_keys` **must** only be accessible with elevated permissions. If you just created it, run the following command within `C:\ProgramData\ssh\`: 

```
icacls administrators_authorized_keys /inheritance:r
```

Right-click the file, select `Properties` > `Security` and make sure only Administrators and System users are listed there. Remove any others.

## 3. Install Cygwin

1. Download [cygwin](https://cygwin.com/install.html)
2. Install without chaging any options
3. Add cygwin to the `PATH` variable via PowerShell: 

```   
[Environment]::SetEnvironmentVariable("Path", $env:Path + ";C:\cygwin64\bin", "Machine")
```
    
## 4. Replace default SSH shell with Cygwin bash

Perform the following commands in PowerShell one-after-another:

```
New-ItemProperty -Path "HKLM:\Software\OpenSSH" -Name "DefaultShell" -Value ”C:\cygwin64\bin\bash.exe”  -PropertyType "String"
New-ItemProperty -Path "HKLM:\Software\OpenSSH" -Name "DefaultShellCommandOption" -Value ”-c”  -PropertyType "String"
New-ItemProperty -Path "HKLM:\Software\OpenSSH" -Name "DefaultShellEscapeArguments" -Value ”0”  -PropertyType "String"
```

Replace the path to cygwin bash in the first command if neccessary!

## Troubleshooting

### Enable SSH logging

1. Open the file `C:\ProgramData\ssh\sshd_config`. 
2. Replace the `# Logging` section as shown below.
3. Restart the OpenSSH service.

```
# Logging
SyslogFacility LOCAL0
LogLevel DEBUG3
```

This will produce a log file in `C:\ProgramData\ssh\sshd_config\logs`. If there are problems with your key files, the log will have detailed information.

### Check SSH config 

Open the file `C:\ProgramData\ssh\sshd_config`. 

Make sure, the following lines are there and not commented out

```
AuthorizedKeysFile .ssh/authorized_keys
```

```
Subsystem	sftp	sftp-server.exe
```

```
Match Group administrators
       AuthorizedKeysFile __PROGRAMDATA__/ssh/administrators_authorized_keys
```

### SSH error `Load key "...": invalid format`

Did you really copy ALL the contents of the private key to the connection configuration? See if the trailing linebrak aufter `-----END OPENSSH PRIVATE KEY-----` is there. It is important!
