<?php

define('DS', DIRECTORY_SEPARATOR);

require_once __DIR__.'/Console.php';
require_once __DIR__.'/Setting.php';

class Installer
{
    protected $settings = null;

    public function __construct()
    {
        Console::setDefaultMessages(array('terminate' => 'The installation is cancelling...'));
        $this->settings = new Setting;
    }

    public function startInstall()
    {
        if (is_file($_ENV['XVHM_APP_DIR'] . '\settings.ini')) {
            Console::line('Xampp vHosts Manager is already integrated into Xampp.');
            Console::line('No need to do it again.');
            return;
        }

        Console::line('Welcome to Xampp vHosts Manager installer.');
        Console::line('This installer will perform following tasks:');
        Console::breakline();

        Console::line('1. Register one Trusted Certificate Authority to authenticate your virtual host CSR later.');
        Console::line('2. Grant the necessary permissions to Windows hosts file so it can be edited from this utility.');
        Console::line('3. Improve Apache\'s "httpd-vhosts.conf" file to include vhost config files later.');
        Console::line('4. Improve Apache\'s "httpd-ssl.conf" file to include vhost SSL config files later.');
        Console::line('5. Register application\'s path to system environment, so you can call it anywhere later.');
        Console::breakline();

        $continue = Console::confirm('Do you agree to continue?');

        if (! $continue) {
            Console::terminate();
        }

        Console::breakline();
        $this->askInstallConfig();
    }

    private function askInstallConfig()
    {
        $phpDir = $_ENV['XVHM_PHP_DIR'];

        Console::line('First, provide the path to your Xampp directory for Xampp vHosts Manager.');

        if (is_file($phpDir . '\..\xampp-control.exe')) {
            $detectedXamppDir = realpath($phpDir . '\..\\');
            Console::line('Xampp vHosts Manager has detected that directory "' . $detectedXamppDir . '" could be your Xampp directory.');
            Console::breakline();
            $confirmXamppDir = Console::confirm('>>> Is that the actual path to your Xampp directory?');
            Console::breakline();
        }

        $xamppDir = (isset($detectedXamppDir) && $confirmXamppDir) ? $detectedXamppDir : $this->tryGetXamppDir();
        $this->settings->set('DirectoryPaths', 'Xampp', $xamppDir);

        if (! is_file($xamppDir . '\apache\bin\httpd.exe')) {
            Console::line('Next, because Xampp vHosts Manager does not detect the path to the Apache directory, you need to provide it manually.');
            Console::breakline();
            $apacheDir = $this->tryGetApacheDir();
            $this->settings->set('DirectoryPaths', 'Apache', $apacheDir);

        }

        if (! $this->settings->save()) {
            Console::breakline();
            $this->terminate('The installation process was interrupted.');
        }

        @file_put_contents($_ENV['XVHM_TMP_DIR'] . '\.installing', '');
    }

    public function continueInstall()
    {
        if (! is_file($_ENV['XVHM_TMP_DIR'] . '\.installing')) {
            Console::breakline();
            Console::line('The installation process was interrupted.');
            Console::line('Please delete file "settings.ini" and try from the beginning.');
            Console::terminate();
        }

        Console::hrline();
        Console::line('Start intergrating Xampp vHosts Manager into your Xampp.');
        Console::breakline();

        $this->registerCA();
        $this->grantPermsToWinHostsFile();
        $this->improveHttpdVhostsConfFile();
        $this->improveHttpdSslConfFile();
        $this->registerPath();
        @unlink($_ENV['XVHM_TMP_DIR'] . '\.installing');

        Console::breakline();
        Console::hrline();
        Console::line('Configure some more settings (option step).');
        $this->askMoreConfig();

        Console::breakline();
        Console::hrline();
        Console::line('Finish installing.');
    }

    private function registerCA()
    {
        $message = 'Registering Trusted CA with name "' .$_ENV['XVHM_OPENSSL_SUBJECT_CN']. '"...';
        Console::line($message, false);

        exec('cscript //NoLogo "' . $_ENV['XVHM_CACERT_GENERATOR'] . '"');

        if (is_file($_ENV['XVHM_CACERT_DIR'] . '\cacert.crt')) {
            Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
            return true;
        }

        // If generating failed
        Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');

        $files = glob($_ENV['XVHM_CACERT_DIR'] .DS. '*'); // get all file names
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        Console::terminate();
    }

    private function grantPermsToWinHostsFile()
    {
        $message = 'Granting permissions to Windows host file...';
        Console::line($message, false);

        exec('cscript //NoLogo "' . $_ENV['XVHM_HOSTSFILE_PERMS_GRANTOR'] . '"');

        Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
    }

    private function improveHttpdVhostsConfFile()
    {
        $message = 'Backing up and improve Apache\'s "httpd-vhosts.conf" file...';
        Console::line($message, false);

        $backupDir = $_ENV['XVHM_APACHE_DIR'] . '\conf\extra\backup';
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Backup httpd-vhosts.conf
        $httpd_vhosts_conf = $_ENV['XVHM_APACHE_DIR'] . '\conf\extra\httpd-vhosts.conf';
        copy($httpd_vhosts_conf, $backupDir . '\httpd-vhosts.conf');

        // Improve httpd-vhosts.conf
        $configLine           = 'IncludeOptional "' . $this->reduceApachePath($_ENV['XVHM_VHOST_CONFIG_DIR'], '/') . '/*.conf"';
        $httpd_vhosts_append  = PHP_EOL . '# Include all virtual host config files';
        $httpd_vhosts_append .= PHP_EOL . $configLine . PHP_EOL;
        $httpd_vhosts_updated = file_put_contents($httpd_vhosts_conf, $httpd_vhosts_append, FILE_APPEND);

        if ($httpd_vhosts_updated) {
            Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
        } else {
            Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
            Console::breakline();
            Console::line('You need to add the following line to the "' .$httpd_vhosts_conf. '" file manually:');
            Console::line($configLine);
            Console::breakline();
        }
    }

    private function improveHttpdSslConfFile()
    {
        $message = 'Backing up and improve Apache\'s "httpd-ssl.conf" file...';
        Console::line($message, false);

        $backupDir = $_ENV['XVHM_APACHE_DIR'] . '\conf\extra\backup';
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Backup httpd-ssl.conf
        $httpd_ssl_conf = $_ENV['XVHM_APACHE_DIR'] . '\conf\extra\httpd-ssl.conf';
        copy($httpd_ssl_conf, $backupDir . '\httpd-ssl.conf');

        // Improve httpd-ssl.conf
        $configLine        = 'IncludeOptional "' . $this->reduceApachePath($_ENV['XVHM_VHOST_SSL_CONFIG_DIR'], '/') . '/*.conf"';
        $httpd_ssl_append  = PHP_EOL . '# Include all virtual host ssl config files';
        $httpd_ssl_append .= PHP_EOL . $configLine . PHP_EOL;
        $httpd_ssl_updated = file_put_contents($httpd_ssl_conf, $httpd_ssl_append, FILE_APPEND);

        if ($httpd_ssl_updated) {
            Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
        } else {
            Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
            Console::breakline();
            Console::line('You need to add the following line to the "' .$httpd_ssl_conf. '" file manually:');
            Console::line($configLine);
            Console::breakline();
        }
    }

    private function registerPath()
    {
        $message = 'Registering application\'s path to system environment...';
        Console::line($message, false);
        exec('cscript //NoLogo "' . $_ENV['XVHM_REGISTER_APPDIR_IMPLEMENTER'] . '"');
        Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
    }

    private function askMoreConfig()
    {
        Console::breakline();
        Console::line('At this point, the installation process is complete.');
        Console::line('However, for Xampp vHosts Manager to work more perfectly, you should configure some more settings.');
        Console::line('You can skip this step and configure the following via the "settings.ini" file.');
        Console::breakline();
        $configNow = Console::confirm('>>> Would you like to do that now?');

        if ($configNow) {
            Console::breakline();
            Console::line('The first setting ---');
            Console::line('Provide the path to directory used to propose as Document Root each vhost creation process.');
            Console::line('Note*: You can use the string {{host_name}} as the virtual host name placeholder.');
            Console::breakline();
            $docRootSuggestion = Console::ask('>>> Enter document root path suggestion', $_ENV['XVHM_XAMPP_DIR'] . '\htdocs\{{host_name}}');
            $this->settings->set('Suggestions', 'DocumentRoot', str_replace('/', DS, $docRootSuggestion));

            Console::breakline();
            Console::line('The second setting ---');
            Console::line('Provide the email used to propose as Admin Email each vhost creation process.');
            Console::breakline();
            $adminEmailSuggestion = Console::ask('>>> Enter admin email suggestion', 'webmaster@example.com');
            $this->settings->set('Suggestions', 'AdminEmail', $adminEmailSuggestion);

            Console::breakline();
            Console::line('The third setting ---');
            Console::line('You want to show how many records per page when listing the existing virtual hosts.');
            Console::breakline();
            $recordPerPage = Console::ask('>>> Enter the virtual hosts record per page', 2);
            $this->settings->set('ListViewMode', 'RecordPerPage', $recordPerPage);

            Console::breakline();
            $message = 'Saving settings to the "settings.ini" file...';
            Console::line($message, false);

            if ($this->settings->save()) {
                Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
            } else {
                Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
                Console::breakline();
                Console::line('You have to set settings through the "settings.ini" file manually.');
            }
        }
    }

    private function tryGetXamppDir()
    {
        $xamppDir = '';

        $repeat = 0;
        while (! is_file(rtrim($xamppDir, '\\') . '\xampp-control.exe')) {
            if ($repeat == 4) {
                Console::terminate('You have not provided correct information multiple times.');
            }

            if ($repeat == 0) {
                $xamppDir = Console::ask('>>> Enter the path to your Xampp directory');
            } else {
                Console::line('The path provided is not the path to the actual Xampp directory.');
                $xamppDir = Console::ask('>>> Please provide it again');
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
                Console::terminate('You have not provided correct information multiple times.');
            }

            if ($repeat == 0) {
                $apacheDir = Console::ask('>>> Enter the path to your Apache directory');
            } else {
                Console::line('The path provided is not the path to the actual Apache directory.');
                $apacheDir = Console::ask('>>> Please provide it again');
            }

            Console::breakline();
            $apacheDir = str_replace('/', DS, $apacheDir);
            $repeat++;
        }

        return $apacheDir;
    }

    private function reduceApachePath($path, $directorySeparator = DS)
    {
        $apachePath = str_replace('/', DS, $_ENV['XVHM_APACHE_DIR']);
        $path       = str_replace('/', DS, $path);

        if (substr($path, 0, strlen($apachePath)) == $apachePath) {
            $path = substr($path, strlen($apachePath . DS));
        }

        return str_replace(DS, $directorySeparator, $path);
    }
}
