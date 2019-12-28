<?php

define('DS', DIRECTORY_SEPARATOR);

require_once __DIR__.'/Application.php';

class Installer extends Application
{
    public function __construct()
    {
        parent::__construct(false);

        if (is_file($this->paths['appDir'] . '\settings.ini') && is_file($this->paths['caCertDir'] . '\cacert.crt')) {
            Console::breakline();
            Console::line('Xampp vHosts Manager is already integrated into Xampp.');
            Console::line('No need to do it again.');
            exit;
        }
    }

    public function install()
    {
        Console::line('Welcome to Xampp vHosts Manager installer.');
        Console::line('This installer will perform following tasks:');
        Console::breakline();

        Console::line('1. Register one Trusted Certificate Authority to authenticate your virtual host CSR later.');
        Console::line('2. Grant the necessary permissions to Windows hosts file so it can be edited from this utility.');
        Console::line('3. Improve Apache\'s "httpd-vhosts.conf" file to include vhost config files later.');
        Console::line('4. Improve Apache\'s "httpd-ssl.conf" file to include vhost SSL config files later.');
        Console::line('5. Register path into Windows Path Environment Variable, so you can call it anywhere later.');
        Console::breakline();

        $continue = Console::confirm('Do you agree to continue?');

        if (! $continue) {
            Console::terminate(null, 1);
        }

        Console::breakline();

        $this->askInstallConfig();

        Console::hrline();
        Console::line('Start intergrating Xampp vHosts Manager into your Xampp.');
        Console::breakline();

        $this->registerCA();
        $this->grantPermsToWinHostsFile();
        $this->improveHttpdVhostsConfFile();
        $this->improveHttpdSslConfFile();
        $this->registerPath();

        Console::breakline();
        Console::hrline();
        Console::line('Configure some more settings (option step).');

        $this->askMoreConfig();

        Console::breakline();
        Console::hrline();
        Console::line('XAMPP VHOSTS MANAGER WAS INSTALLED SUCCESSFULLY.');
        Console::line('TO START USING IT, PLEASE EXIT YOUR TERMINAL TO');
        Console::line('DELETE TEMPORARY PROCESS ENVIRONMENT VARIABLES.');
    }

    private function askInstallConfig()
    {
        $phpDir = $this->paths['phpDir'];

        Console::line('First, provide the path to your Xampp directory for Xampp vHosts Manager.');

        if (is_file($phpDir . '\..\xampp-control.exe')) {
            $detectedXamppDir = realpath($phpDir . '\..\\');
            Console::line('Xampp vHosts Manager has detected that directory "' . strtoupper($detectedXamppDir) . '" could be your Xampp directory.');
            Console::breakline();
            $confirmXamppDir = Console::confirm('Is that the actual path to your Xampp directory?');
            Console::breakline();
        }

        $xamppDir = (isset($detectedXamppDir) && $confirmXamppDir) ? $detectedXamppDir : $this->tryGetXamppDir();
        $this->setting->set('DirectoryPaths', 'Xampp', $xamppDir);
        $this->paths['xamppDir'] = $xamppDir;

        if (is_file($xamppDir . '\apache\bin\httpd.exe')) {
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

        $this->setting->reloadSettings();
        $this->loadAdditionalPaths();
    }

    private function registerCA()
    {
        $message = 'Registering Trusted CA with name "' . getenv('XVHM_OPENSSL_SUBJECT_CN') . '"...';
        Console::line($message, false);

        $this->powerExec('"' . $this->paths['caCertGenScript'] . '"', '-w -i -n');

        if (is_file($this->paths['caCertDir'] . '\cacert.crt')) {
            $this->powerExec('CERTUTIL -addstore -enterprise -f -v Root "' . $this->paths['caCertDir'] . '\cacert.crt"', '-w -e -i -n', $outputVal, $exitCode);

            if ($exitCode == 0) {
                Console::line('Successful', true, max(73 - strlen($message), 1));
                return true;
            }
        }

        // If generating failed
        Console::line('Failed', true, max(77 - strlen($message), 1));

        $files = glob($this->paths['caCertDir'] .DS. '*'); // get all file names
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        Console::terminate('Cancel the installation.', 1);
    }

    private function grantPermsToWinHostsFile()
    {
        $message = 'Granting necessary permissions to Windows host file...';
        Console::line($message, false);

        $this->powerExec('icacls "' . $this->paths['winHostsFile'] . '" /grant Users:MRXRW', '-w -i -e -n', $outputVal, $exitCode);

        if ($exitCode == 0) {
            Console::line('Successful', true, max(73 - strlen($message), 1));
            return true;
        }

        Console::line('Failed', true, max(77 - strlen($message), 1));
        Console::breakline();
        Console::line('You need set the Modify and Write permissions for group Users...');
        Console::line('to Windows hosts file manually after installation.');
        return false;
    }

    private function improveHttpdVhostsConfFile()
    {
        $message = 'Backing up and improve Apache\'s "httpd-vhosts.conf" file...';
        Console::line($message, false);

        $backupDir = $this->paths['apacheDir'] . '\conf\extra\backup';
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Backup httpd-vhosts.conf
        $httpd_vhosts_conf = $this->paths['apacheDir'] . '\conf\extra\httpd-vhosts.conf';
        copy($httpd_vhosts_conf, $backupDir . '\httpd-vhosts.conf');

        // Improve httpd-vhosts.conf
        $configLine           = 'IncludeOptional "' . $this->reduceApachePath($this->paths['vhostConfigDir'], '/') . '/*.conf"';
        $httpd_vhosts_append  = PHP_EOL . '# Include all virtual host config files';
        $httpd_vhosts_append .= PHP_EOL . $configLine . PHP_EOL;
        $httpd_vhosts_updated = file_put_contents($httpd_vhosts_conf, $httpd_vhosts_append, FILE_APPEND);

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
        $message = 'Backing up and improve Apache\'s "httpd-ssl.conf" file...';
        Console::line($message, false);

        $backupDir = $this->paths['apacheDir'] . '\conf\extra\backup';
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Backup httpd-ssl.conf
        $httpd_ssl_conf = $this->paths['apacheDir'] . '\conf\extra\httpd-ssl.conf';
        copy($httpd_ssl_conf, $backupDir . '\httpd-ssl.conf');

        // Improve httpd-ssl.conf
        $configLine        = 'IncludeOptional "' . $this->reduceApachePath($this->paths['vhostSSLConfigDir'], '/') . '/*.conf"';
        $httpd_ssl_append  = PHP_EOL . '# Include all virtual host ssl config files';
        $httpd_ssl_append .= PHP_EOL . $configLine . PHP_EOL;
        $httpd_ssl_updated = file_put_contents($httpd_ssl_conf, $httpd_ssl_append, FILE_APPEND);

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

    private function registerPath()
    {
        $message = 'Registering application\'s path into Windows Path Environment Variable...';
        Console::line($message, false);

        $this->powerExec('cscript "' . $this->paths['pathRegister'] . '" "' .$this->paths['appDir']. '"', '-w -i -e -n', $outputVal, $exitCode);

        if ($exitCode == 0) {
            Console::line('Successful', true, max(73 - strlen($message), 1));
            return true;
        } else {
            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::line('Don\'t worry. This does not affect the installation process.');
            Console::line('You can register the path manually...');
            Console::line('or use the "xvhosts register_path" command after installation.');
            return false;
        }
    }

    private function askMoreConfig()
    {
        Console::breakline();
        Console::line('At this point, the installation process is complete.');
        Console::line('However, for Xampp vHosts Manager to work more perfectly');
        Console::line('you should configure some more settings. You can skip this');
        Console::line('step and configure the following via the "settings.ini" file.');
        Console::breakline();
        $configNow = Console::confirm('Would you like to do that now?');

        if ($configNow) {
            Console::breakline();
            Console::line('[+] The first setting ---');
            Console::line('Provide the path to directory used to propose as Document Root each vhost creation process.');
            Console::line('Note*: You can use the string {{host_name}} as the virtual host name placeholder.');
            Console::breakline();

            $docRootSuggestion = Console::ask('Enter document root path suggestion', $this->paths['xamppDir'] . '\htdocs\{{host_name}}');
            $this->setting->set('Suggestions', 'DocumentRoot', str_replace('/', DS, $docRootSuggestion));

            Console::breakline();
            Console::line('[+] The second setting ---');
            Console::line('Provide the email used to propose as Admin Email each vhost creation process.');
            Console::breakline();

            $adminEmailSuggestion = Console::ask('Enter admin email suggestion', 'webmaster@example.com');
            $this->setting->set('Suggestions', 'AdminEmail', $adminEmailSuggestion);

            Console::breakline();
            Console::line('[+] The third setting ---');
            Console::line('Provide the number of records per page when listing the existing virtual hosts.');
            Console::breakline();

            $recordPerPage = Console::ask('Enter the virtual hosts record per page', 2);
            $this->setting->set('ListViewMode', 'RecordPerPage', $recordPerPage);

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
        while (! is_file(rtrim($xamppDir, '\\/') . '\xampp-control.exe')) {
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
            $xamppDir = str_replace('/', DS, $xamppDir);
            $repeat++;
        }

        return $xamppDir;
    }

    private function tryGetApacheDir()
    {
        $apacheDir = '';

        $repeat = 0;
        while (! is_file(rtrim($apacheDir, '\\') . '\bin\httpd.exe')) {
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
            $apacheDir = str_replace('/', DS, $apacheDir);
            $repeat++;
        }

        return $apacheDir;
    }
}
