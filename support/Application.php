<?php

define('DS', DIRECTORY_SEPARATOR);

require_once __DIR__.'/Console.php';
require_once __DIR__.'/Setting.php';

class Application
{
    protected $setting = null;
    protected $paths   = array();

    public function __construct($detectXamppPaths = true)
    {
        Console::setDefaultMessages(array('terminate' => 'Xampp vHosts Manager is terminating...'));

        $this->setting = new Setting;

        $this->loadAppPaths();

        if ($detectXamppPaths) {
            $this->detectXamppPaths();
        }
    }

    protected function stopApache($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Do you want to stop Apache?';
            $confirm  = Console::confirm($question);
        } else {
            $confirm = true;
        }

        if ($confirm) {
            Console::line('Stopping Apache Httpd...');
            self::powerExec('"' . $this->paths['xamppDir'] . '\apache_stop.bat"', '-w -i -n');
        }
    }

    protected function startApache($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Do you want to start Apache?';
            $confirm  = Console::confirm($question);
        } else {
            $confirm = true;
        }

        if ($confirm) {
            Console::line('Starting Apache Httpd...');
            self::powerExec('"' . $this->paths['xamppDir'] . '\apache_start.bat"', '-i -n');
        }
    }

    protected function restartApache($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Do you want to restart Apache?';
            $confirm  = Console::confirm($question);
        } else {
            $confirm = true;
        }

        if ($confirm) {
            Console::breakline();

            self::stopApache(false);
            self::startApache(false);
        }
    }

    protected function registerPath($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Do you want to change the path of this app to "' . $this->paths['appDir'] . '"?';
            $confirm  = Console::confirm($question);
        } else {
            $confirm = true;
        }

        if ($confirm) {
            $message = 'Registering application\'s path into Windows Path Environment...';
            Console::line($message, false);

            self::powerExec('cscript "' . $this->paths['pathRegister'] . '" "' .$this->paths['appDir']. '"', '-w -i -e -n', $outputVal, $exitCode);

            if ($exitCode == 0) {
                Console::line('Successful', true, max(73 - strlen($message), 1));
                return true;
            }

            Console::line('Failed', true, max(77 - strlen($message), 1));
            return false;
        }
    }

    private function loadAppPaths()
    {
        $appDir = realpath(getenv('XVHM_APP_DIR'));

        $this->paths['appDir']                 = $appDir;
        $this->paths['tmpDir']                 = $appDir . '\tmp';
        $this->paths['caCertDir']              = $appDir . '\cacert';
        $this->paths['caCertGenScript']        = $appDir . '\cacert_generate.bat';
        $this->paths['caCertGenConfig']        = $appDir . '\cacert_generate.cnf';
        $this->paths['vhostConfigTemplate']    = $appDir . '\templates\vhost_config\vhost.conf.tpl';
        $this->paths['vhostSSLConfigTemplate'] = $appDir . '\templates\vhost_config\vhost_ssl.conf.tpl';
        $this->paths['vhostCertGenScript']     = $appDir . '\vhostcert_generate.bat';
        $this->paths['vhostCertGenConfig']     = $appDir . '\vhostcert_generate.cnf';
        $this->paths['pathRegister']           = $appDir . '\support\PathRegister.vbs';
        $this->paths['powerExecutor']          = $appDir . '\support\PowerExec.vbs';
        $this->paths['winHostsFile']           = realpath($_SERVER['SystemRoot'] . '\System32\drivers\etc\hosts');

        // Set environment variables
        putenv('XVHM_TMP_DIR=' . $this->paths['tmpDir']);
        putenv('XVHM_CACERT_DIR=' . $this->paths['caCertDir']);
        putenv('XVHM_CACERT_GENERATE_CONFIG=' . $this->paths['caCertGenConfig']);
        putenv('XVHM_VHOST_CERT_GENERATE_CONFIG=' . $this->paths['vhostCertGenConfig']);
    }

    protected function detectXamppPaths()
    {
        // Force reload settings
        $this->setting->reloadSettings();

        $xamppDir  = realpath($this->setting->get('DirectoryPaths', 'Xampp'));
        $apacheDir = $this->setting->get('DirectoryPaths', 'Apache');
        $phpDir    = $this->setting->get('DirectoryPaths', 'Php');

        if (! $xamppDir || ! is_file($xamppDir . '\xampp-control.exe')) {
            Console::breakline();

            $message = 'Cannot find Xampp directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the Xampp directory in file "' . $this->paths['appDir'] . '\settings.ini".';

            Console::terminate($message, 1);
        }

        $this->paths['xamppDir'] = $xamppDir;

        if (! $apacheDir) {
            $apacheDir = $xamppDir . '\apache';
        }

        if (! is_file($apacheDir . '\bin\httpd.exe')) {
            Console::breakline();

            $message = 'Cannot find Apache directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the Apache directory in file "' . $this->paths['appDir'] . '\settings.ini".';

            Console::terminate($message, 1);
        }

        $this->paths['apacheDir'] = $apacheDir;

        if (! $phpDir) {
            $phpDir = $xamppDir . '\php';
        }

        if (! is_file($phpDir . '\php.exe')) {
            Console::breakline();

            $message = 'Cannot find PHP directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the PHP directory in file "' . $this->paths['appDir'] . '\settings.ini".';

            Console::terminate($message, 1);
        }

        $this->paths['phpDir'] = $phpDir;

        return $this->loadAdditionalPaths();
    }

    protected function loadAdditionalPaths()
    {
        if (! array_key_exists('apacheDir', $this->paths)) {
            Console::breakline();
            Console::terminate('The identification of the path to Apache directory has not yet been conducted', 1);
        }

        $this->paths['vhostCertDir']      = $this->paths['apacheDir'] . '\conf\extra\certs';
        $this->paths['vhostCertKeyDir']   = $this->paths['apacheDir'] . '\conf\extra\keys';
        $this->paths['vhostConfigDir']    = $this->paths['apacheDir'] . '\conf\extra\vhosts';
        $this->paths['vhostSSLConfigDir'] = $this->paths['apacheDir'] . '\conf\extra\vhosts_ssl';
        $this->paths['opensslBin']        = $this->paths['apacheDir'] . '\bin\openssl.exe';

        // Set environment variables
        putenv('XVHM_VHOST_CERT_DIR=' . $this->paths['vhostCertDir']);
        putenv('XVHM_VHOST_CERT_KEY_DIR=' . $this->paths['vhostCertKeyDir']);

        putenv('XVHM_OPENSSL_BIN=' . $this->paths['opensslBin']);
        putenv('XVHM_OPENSSL_SUBJECT_CN=Xampp Certificate Authority');
        putenv('XVHM_OPENSSL_SUBJECT_O=OpenSSL Software Foundation');
        putenv('XVHM_OPENSSL_SUBJECT_OU=Server Certificate Provider');

        return $this;
    }

    protected function reduceApachePath($path, $directorySeparator = DS)
    {
        $apachePath = str_replace('/', DS, $this->paths['apacheDir']);
        $path       = str_replace('/', DS, $path);

        if (substr($path, 0, strlen($apachePath)) == $apachePath) {
            $path = substr($path, strlen($apachePath . DS));
        }

        return str_replace(DS, $directorySeparator, $path);
    }

    protected function powerExec($command, $arguments = null, &$outputArray = null, &$statusCode = null)
    {
        if (is_file($this->paths['powerExecutor'])) {
            if (is_array($arguments)) {
                $arguments = '"' . trim(implode('" "', $arguments)) . '"';
            } elseif (is_string($arguments)) {
                $arguments = trim($arguments);
            } else {
                $arguments = trim(strval($arguments));
            }

            $outputArray = $statusCode = null;

            return exec('cscript //NoLogo "' . $this->paths['powerExecutor'] . '" ' . $arguments . ' ' . $command, $outputArray, $statusCode);
        }

        $message     = 'Cannot find the "' . $this->paths['powerExecutor'] . '" implementer.';
        $outputArray = array($message);
        $statusCode  = 1;

        return $message;
    }
}
