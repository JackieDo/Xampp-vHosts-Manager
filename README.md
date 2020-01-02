# Xampp vHosts Manager
Virtual hosts (include SSL) management system (in console mode) for Xampp on Windows OS

Documentation languages >>> ( English | [Vietnamese](https://github.com/JackieDo/Xampp-vHosts-Manager/blob/master/README_vi.md) )

![Xampp vHosts Manager GitHub cover](https://user-images.githubusercontent.com/9862115/70820328-f78de800-1e0a-11ea-894a-b7021942c158.jpg)

Someone once asked me

> ***How to configure and manage Virtual Hosts for Xampp on Windows OS?***

A few others asked

> ***How do I add self-signed SSL certificates to Xampp Virtual Hosts as quickly as possible and easily manage them without the hassle of using OPENSSL commands?***

Therefore, this project was born, in order to strengthen Xampp, helping users take advantage of Xampp's inherent resources to make the above purposes as quickly and easily as possible.

> _Note: Currently this project only supports Windows users._

# Features of this project
* Create virtual host.
* Remove an existing virtual host.
* Display information of an existing virtual host.
* List all existing virtual hosts.
* Add SSL certificate to an existing virtual host.
* Remove SSL certificate of an existing virtual host.
* Change Document Root of an existing virtual host.
* Stop Xampp Apache Httpd.
* Start Xampp Apache Httpd.
* Restart Xampp Apache Httpd.

# Overview
Look at one of the following topics to learn more about Xampp vHosts Manager.

* [Compatibility](#compatibility)
* [Requirement](#requirement)
* [Installation](#installation)
    - [Via Composer Create-Project](#via-composer-create-project)
    - [Via Manual Download](#via-manual-download)
* [Usage](#usage)
    - [Display the help message](#display-the-help-message)
    - [Create new virtual host](#create-new-virtual-host)
    - [Display information of an existing virtual host](#display-information-of-an-existing-virtual-host)
    - [List all existing virtual hosts](#list-all-existing-virtual-hosts)
    - [Remove an existing virtual host](#remove-an-existing-virtual-host)
    - [Add SSL certificate to an existing virtual host](#add-ssl-certificate-to-an-existing-virtual-host)
    - [Remove SSL certificate of an existing virtual host](#remove-ssl-certificate-of-an-existing-virtual-host)
    - [Change Document Root of an existing virtual host](#change-document-root-of-an-existing-virtual-host)
    - [Stop Apache Httpd](#stop-apache-httpd)
    - [Start Apache Httpd](#start-apache-httpd)
    - [Restart Apache Httpd](#restart-apache-httpd)
    - [Register path of application](#register-path-of-application)
* [Configuration](#configuration)
* [License](#license)
* [Thanks from author](#thanks-for-use)

## Compatibility
Xampp vHosts Manager is compatible with all Xampp versions using PHP 5.4 or higher.

## Requirement
Xampp vHosts Manager takes full advantage of what's included in Xampp, nothing more needed. So, you just need following things:

* Installed Xampp (of course).
* Added the path to PHP directory of Xampp into Windows Path Environment Variable.
* (Optional) Installed Composer.

> _Note: See [here](https://helpdeskgeek.com/windows-10/add-windows-path-environment-variable/) to know how to add Windows Path Environment Variable._

## Installation
There are two installation methods, via Composer or manual download. It is recommended to use the method via Composer if you already have it installed.

#### Via Composer Create-Project
* Open a terminal.
* Navigate to the directory you want to install Xampp vHosts Manager into.
* Run composer create-project command:
```
$ composer create-project jackiedo/xampp-vhosts-manager xvhm "1.*"
```

#### Via Manual Download
* Download the [latest release](https://github.com/JackieDo/Xampp-vHosts-Manager/releases/latest)
* Extract the archive to a shared location `(example: D:\xvhm)`. Note: Should not place in `C:\Program Files` or anywhere else that would require Administrator access for modifying configuration files.
* Open a terminal in Administrator mode `(run as Administrator)`.
* Navigate to the directory you have placed Xampp vHosts Manager `(example: cd /D D:\xvhm)`.
* Execute the command `xvhost install` and follow the required steps.
* Exit terminal (to remove temporary environment variables).

> Note: See [here](https://www.howtogeek.com/194041/how-to-open-the-command-prompt-as-administrator-in-windows-8.1/) to know how to to open the command prompt as Administrator.

## Usage
Because of a path to the Xampp vHosts Manager application directory has been added to the Windows Path Environment Variables during the installation process, now you can just open the terminal `(no need to open in Administrator mode anymore)` anywhere and excute one of the following `xvhost` commands:

#### Display the help message

Syntax:
```
$ xvhost help
```

#### Create new virtual host

Syntax:
```
$ xvhost new [HOST_NAME]
```

Example:
```
$ xvhost new demo.local
```

> Note: The HOST_NAME parameter is optional. If you do not pass it to the command statement, you will also be asked to enter this information later.

#### Display information of an existing virtual host

Syntax:
```
$ xvhost show [HOST_NAME]
```

Example:
```
$ xvhost show demo.local
```

#### List all existing virtual hosts

Syntax:
```
$ xvhost list
```

#### Remove an existing virtual host

Syntax:
```
$ xvhost remove [HOST_NAME]
```

Example:
```
$ xvhost remove demo.local
```

#### Add SSL certificate to an existing virtual host

Syntax:
```
$ xvhost add_ssl [HOST_NAME]
```

Example:
```
$ xvhost add_ssl demo.local
```

#### Remove SSL certificate of an existing virtual host

Syntax:
```
$ xvhost remove_ssl [HOST_NAME]
```

Example:
```
$ xvhost remove_ssl demo.local
```

#### Change Document Root of an existing virtual host

Syntax:
```
$ xvhost change_docroot [HOST_NAME]
```

Example:
```
$ xvhost change_docroot demo.local
```

#### Stop Apache Httpd

Syntax:
```
$ xvhost stop_apache
```

#### Start Apache Httpd

Syntax:
```
$ xvhost start_apache
```

#### Restart Apache Httpd

Syntax:
```
$ xvhost restart_apache
```

#### Register path of application
This feature allows you to register the path to the application directory of Xampp vHosts Manager into the Windows Path Environment Variable. Usually, you rarely need this. It is only useful when you need to change the application directory name of Xampp vHosts Manager or move it to another location.

To do this, after you have changed the directory, navigate to the new location of application directory in the command prompt and run the following command:

Syntax:
```
$ xvhost register_path
```

> Note: You need to accept this process to be performed with Administrator permission.

## Configuration
All onfiguration are put in an ini file with name `settings.ini` located in Xampp vHosts Manager application directory. The structure of this file looks like this:

```
[Section_1]
setting_1 = "value"
setting_2 = "value"

[Section_2]
setting_1 = "value"
setting_2 = "value"
...
```

The whole configuration of XVHM includes:
```
[DirectoryPaths]
;The path to your Xampp directory.
Xampp = "D:\xampp"

[Suggestions]
;The path to directory used to propose as DocumentRoot config in vhost config file each vhost creation process.
;The variable {{host_name}} is used as virtual host name placeholder.
DocumentRoot = "D:\www\{{host_name}}"

;The email used to propose as ServerAdmin config in vhost config file each vhost creation process.
AdminEmail = "anhvudo@gmail.com"

[ListViewMode]
;The number of records will be displayed on each page when listing the existing virtual hosts.
RecordPerPage = "2"

```

## License
[MIT](LICENSE) © Jackie Do

## Thanks for use
Hopefully, this package is useful to you.
