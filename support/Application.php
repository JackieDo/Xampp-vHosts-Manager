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

    private function loadAppPaths()
    {
        $appDir = realpath(getenv('XVHM_APP_DIR'));

        $this->paths['appDir']                 = $appDir;
        $this->paths['phpDir']                 = dirname(PHP_BINARY);
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

    protected function powerExec($command, $arguments = null, &$outputValue = null, &$returnCode = null)
    {
        if (is_file($this->paths['powerExecutor'])) {
            if (is_array($arguments)) {
                $arguments = '"' . trim(implode('" "', $arguments)) . '"';
            } elseif (is_string($arguments)) {
                $arguments = trim($arguments);
            } else {
                $arguments = trim(strval($arguments));
            }

            return exec('cscript //NoLogo "' . $this->paths['powerExecutor'] . '" ' . $arguments . ' ' . $command, $outputValue, $returnCode);
        }

        $message     = 'Cannot find the "' . $this->paths['powerExecutor'] . '" implementer.';
        $outputValue = array($message);
        $returnCode  = 1;

        return $message;
    }
}
