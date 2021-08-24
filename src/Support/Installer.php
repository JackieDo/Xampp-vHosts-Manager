<?php

namespace VhostsManager\Support;

class Installer extends Application
{
    public function __construct()
    {
        parent::__construct(false, false);
    }

    public function install()
    {
        Console::line('Welcome to Xampp vHosts Manager installer.');
        Console::line('This installer will perform following tasks:');
        Console::breakline();

        Console::line('1. Register one Trusted Certificate Authority to authenticate your virtual host CSR later.');
        Console::line('2. Improve the Apache "httpd-vhosts.conf" file to include vhost config files later.');
        Console::line('3. Improve the Apache "httpd-ssl.conf" file to include vhost SSL config files later.');
        Console::line('4. Grant the necessary permissions to Windows hosts file so it can be edited from this app.');
        Console::line('5. Register path into Windows Path Environment Variable, so you can call it anywhere later.');
        Console::breakline();

        $continue = Console::confirm('Do you agree to continue?');

        if (! $continue) {
            Console::breakline();
            Console::line('The installation was canceled by user.');
            Console::terminate(null, 2, true);
        }

        Console::breakline();

        $this->askInstallConfig();

        Console::hrline();
        Console::line('Start intergrating Xampp vHosts Manager into your Xampp.');
        Console::breakline();

        $this->registerCA();
        $this->improveHttpdVhostsConfFile();
        $this->improveHttpdSslConfFile();
        $this->grantPermsToWinHostsFile();
        $this->registerPath();

        Console::breakline();
        Console::hrline();
        Console::line('Configure some more settings (option step).');

        $this->askMoreConfig();

        Console::breakline();
        Console::hrline();
        Console::line('XAMPP VHOSTS MANAGER WAS INSTALLED SUCCESSFULLY.');
        Console::line('TO START USING IT, PLEASE EXIT YOUR TERMINAL TO DELETE TEMPORARY PROCESS ENVIRONMENT VARIABLES.');
    }

    private function askInstallConfig()
    {
        $phpDir            = dirname(PHP_BINARY);
        $maybeXamppDir     = osstyle_path(dirname($phpDir));
        $xamppDirConfirmed = false;

        Console::line('First, provide the path to your Xampp directory for Xampp vHosts Manager.');

        if (maybe_xamppdir($maybeXamppDir)) {
            Console::line('Xampp vHosts Manager has detected that directory "' . $maybeXamppDir . '" could be your Xampp directory.');
            Console::breakline();

            $xamppDirConfirmed = Console::confirm('Is that the actual path to your Xampp directory?');

            Console::breakline();
        }

        $xamppDir = $xamppDirConfirmed ? $maybeXamppDir : $this->tryGetXamppDir();

        $this->setting->set('DirectoryPaths', 'Xampp', $xamppDir);

        $this->paths['xamppDir'] = $xamppDir;

        if (maybe_apachedir($xamppDir . '\apache')) {
            $this->paths['apacheDir'] = $xamppDir . '\apache';
        } else {
            Console::line('Next, because Xampp vHosts Manager does not detect the path to the Apache directory, you need to provide it manually.');
            Console::breakline();

            $apacheDir = $this->tryGetApacheDir();

            $this->setting->set('DirectoryPaths', 'Apache', $apacheDir);
            $this->paths['apacheDir'] = $apacheDir;
        }

        if (! $this->setting->save()) {
            Console::breakline();
            Console::line('Installation settings cannot be saved.');
            Console::terminate('Cancel the installation.', 1);
        }

        $this->setting->reload();
        $this->loadAdditionalPaths();
    }

    private function registerCA()
    {
        $message = 'Registering Trusted CA with name "' . getenv('XVHM_OPENSSL_SUBJECT_CN') . '"...';

        Console::line($message, false);

        $registered = false;
        $generated  = $this->createdCaCert();

        if ($generated && $this->addedCaCertToStore()) {
            $registered = true;
        } elseif (!$generated) {
            $this->powerExec('"' . $this->paths['caCertGenScript'] . '"', '-w -i -n', $arrOutput, $exitCode);
            $this->powerExec('CERTUTIL -addstore -enterprise -f -v Root "' . $this->paths['caCertDir'] . '\cacert.crt"', '-w -e -i -n', $arrOutput, $exitCode);

            $registered = true;
        } else {
            $this->powerExec('CERTUTIL -addstore -enterprise -f -v Root "' . $this->paths['caCertDir'] . '\cacert.crt"', '-w -e -i -n', $arrOutput, $exitCode);

            $registered = true;
        }

        if ($registered) {
            Console::line('Successful', true, max(73 - strlen($message), 1));

            return true;
        }

        Console::line('Failed', true, max(77 - strlen($message), 1));
        undir($this->paths['caCertDir']);
        Console::terminate('Cancel the installation.', 1);
    }

    private function improveHttpdVhostsConfFile()
    {
        $message = 'Backing up and improving the Apache "httpd-vhosts.conf" file...';

        Console::line($message, false);

        // Vars
        $httpd_vhosts_conf = $this->paths['apacheDir'] . '\conf\extra\httpd-vhosts.conf';
        $configLine        = 'IncludeOptional "' . relative_path($this->paths['apacheDir'], $this->paths['vhostConfigDir'], '/') . '/*.conf"';

        // Backup
        $this->backupFile($httpd_vhosts_conf, $this->paths['apacheDir'] . '\conf\extra\backup');

        // Improve httpd-vhosts.conf
        if (line_exists($configLine, $httpd_vhosts_conf)) {
            $httpd_vhosts_updated = true;
        } else {
            $httpd_vhosts_append  = PHP_EOL . '# Include all virtual host config files' . PHP_EOL . $configLine . PHP_EOL;
            $httpd_vhosts_updated = file_put_contents($httpd_vhosts_conf, $httpd_vhosts_append, FILE_APPEND);
        }

        if ($httpd_vhosts_updated) {
            Console::line('Successful', true, max(73 - strlen($message), 1));

            return true;
        }

        Console::line('Failed', true, max(77 - strlen($message), 1));
        Console::breakline();
        Console::line('You need to add the following lines to the "' .$httpd_vhosts_conf. '" file manually after installation:');
        Console::line($configLine);
        Console::breakline();

        return false;
    }

    private function improveHttpdSslConfFile()
    {
        $message = 'Backing up and improving the Apache "httpd-ssl.conf" file...';

        Console::line($message, false);

        // Vars
        $httpd_ssl_conf = $this->paths['apacheDir'] . '\conf\extra\httpd-ssl.conf';
        $configLine     = 'IncludeOptional "' . relative_path($this->paths['apacheDir'], $this->paths['vhostSSLConfigDir'], '/') . '/*.conf"';

        // Backup
        $this->backupFile($httpd_ssl_conf, $this->paths['apacheDir'] . '\conf\extra\backup');

        // Improve httpd-ssl.conf
        if (line_exists($configLine, $httpd_ssl_conf)) {
            $httpd_ssl_updated = true;
        } else {
            $httpd_ssl_append  = PHP_EOL . '# Include all virtual host ssl config files' . PHP_EOL . $configLine . PHP_EOL;
            $httpd_ssl_updated = file_put_contents($httpd_ssl_conf, $httpd_ssl_append, FILE_APPEND);
        }

        if ($httpd_ssl_updated) {
            Console::line('Successful', true, max(73 - strlen($message), 1));

            return true;
        }

        Console::line('Failed', true, max(77 - strlen($message), 1));
        Console::breakline();
        Console::line('You need to add the following lines to the "' .$httpd_ssl_conf. '" file manually after installation:');
        Console::line($configLine);
        Console::breakline();

        return false;
    }

    private function grantPermsToWinHostsFile()
    {
        $result = $this->grantPermsWinHosts(false);

        if (! $result) {
            Console::breakline();
            Console::line('You need set the Modify and Write permissions for group Users to the Windows hosts file manually after installation.');
        }
    }

    private function registerPath()
    {
        $result = $this->registerAppPath(false);

        if (! $result) {
            Console::breakline();
            Console::line('Don\'t worry. This does not affect the installation process.');
            Console::line('You can register the path manually or use the "xvhost register_path" command after installation.');
        }
    }

    private function backupFile($sourceFile, $targetDir, $forceOverwrite = false)
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (is_file($sourceFile)) {
            $targetFile = $targetDir . '\\' . basename($sourceFile);

            if ($forceOverwrite || !is_file($targetFile)) {
                copy($sourceFile, $targetFile);
            }
        }
    }

    private function askMoreConfig()
    {
        Console::breakline();
        Console::line('At this point, the installation process is complete.');
        Console::line('However, for Xampp vHosts Manager to work more perfectly you should configure some more settings.');
        Console::line('You can skip this step and configure the following via the "settings.ini" file.');
        Console::breakline();

        $configNow = Console::confirm('Would you like to do that now?');

        if ($configNow) {
            Console::breakline();
            Console::line('[+] The first setting ---');
            Console::line('Provide the path to directory used to propose as Document Root each vhost creation process.');
            Console::line('Note*: You can use the string {{host_name}} as the virtual host name placeholder.');
            Console::breakline();

            $docRootSuggestion = $this->setting->get('Suggestions', 'DocumentRoot', $this->paths['xamppDir'] . '\htdocs\{{host_name}}');
            $docRootSuggestion = Console::ask('Enter document root path suggestion', $docRootSuggestion);

            $this->setting->set('Suggestions', 'DocumentRoot', osstyle_path($docRootSuggestion));

            Console::breakline();
            Console::line('[+] The second setting ---');
            Console::line('Provide the email used to propose as Admin Email each vhost creation process.');
            Console::breakline();

            $adminEmailSuggestion = $this->setting->get('Suggestions', 'AdminEmail', 'webmaster@example.com');
            $adminEmailSuggestion = Console::ask('Enter admin email suggestion', $adminEmailSuggestion);

            $this->setting->set('Suggestions', 'AdminEmail', $adminEmailSuggestion);

            Console::breakline();
            Console::line('[+] The third setting ---');
            Console::line('Provide the number of records per page when listing the existing virtual hosts.');
            Console::breakline();

            $recordPerPage = $this->setting->get('ListViewMode', 'RecordPerPage', 3);
            $recordPerPage = Console::ask('Enter the virtual hosts record per page', $recordPerPage);

            $this->setting->set('ListViewMode', 'RecordPerPage', intval($recordPerPage));

            Console::breakline();

            $message = 'Saving settings to the "settings.ini" file...';

            Console::line($message, false);

            if ($this->setting->save()) {
                Console::line('Successful', true, max(73 - strlen($message), 1));

                return true;
            }

            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::line('You have to set settings through the "settings.ini" file manually.');

            return false;
        }
    }

    private function tryGetXamppDir()
    {
        $xamppDir = '';
        $repeat = 0;

        while (! maybe_xamppdir($xamppDir)) {
            if ($repeat == 4) {
                Console::line('You have not provided correct information multiple times.');
                Console::terminate('Cancel the installation.', 1);
            }

            if ($repeat == 0) {
                $xamppDir = Console::ask('Enter the path to your Xampp directory');
            } else {
                Console::line('The path provided is not the path to the actual Xampp directory.');

                $xamppDir = Console::ask('Please provide it again');
            }

            Console::breakline();

            $xamppDir = osstyle_path(rtrim($xamppDir, '/\\'));

            $repeat++;
        }

        return $xamppDir;
    }

    private function tryGetApacheDir()
    {
        $apacheDir = '';
        $repeat = 0;

        while (! maybe_apachedir($apacheDir)) {
            if ($repeat == 4) {
                Console::line('You have not provided correct information multiple times.');
                Console::terminate('Cancel the installation.', 1);
            }

            if ($repeat == 0) {
                $apacheDir = Console::ask('Enter the path to your Apache directory');
            } else {
                Console::line('The path provided is not the path to the actual Apache directory.');

                $apacheDir = Console::ask('Please provide it again');
            }

            Console::breakline();

            $apacheDir = osstyle_path(rtrim($apacheDir, '/\\'));

            $repeat++;
        }

        return $apacheDir;
    }
}
