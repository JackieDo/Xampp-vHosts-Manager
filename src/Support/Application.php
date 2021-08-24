<?php

namespace VhostsManager\Support;

class Application
{
    protected $setting = null;
    protected $paths   = [];

    public function __construct($checkInstalled = true, $detectXamppPaths = true)
    {
        Console::setDefaultMessages(['terminate' => 'Xampp vHosts Manager is terminating...']);
        $this->defineAppPaths();

        $this->setting = new Setting($this->paths['settingsStorage']);

        if ($checkInstalled) {
            $this->checkInstalled();
        }

        if ($detectXamppPaths) {
            $this->detectXamppPaths();
            $this->loadAdditionalPaths();
        }
    }

    final public function registerAppPath($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Do you want to change the path of XVHM to "' . $this->paths['appDir'] . '"?';
            $confirm  = Console::confirm($question);

            Console::breakline();
        } else {
            $confirm = true;
        }

        if ($confirm) {
            $message = 'Registering application\'s path into Windows Path Environment...';

            Console::line($message, false);

            $this->powerExec('cscript "' . $this->paths['pathRegister'] . '" "' .$this->paths['appDir']. '"', '-w -i -e -n', $outputVal, $exitCode);

            if ($exitCode == 0) {
                Console::line('Successful', true, max(73 - strlen($message), 1));

                return true;
            }

            Console::line('Failed', true, max(77 - strlen($message), 1));

            return false;
        }
    }

    final public function stopApache($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Are you sure you want to stop Apache?';
            $confirm  = Console::confirm($question);

            Console::breakline();
        } else {
            $confirm = true;
        }

        if ($confirm) {
            $message = 'Stopping Apache Httpd...';

            Console::line($message, false);

            $this->powerExec('"' . $this->paths['xamppDir'] . '\apache_stop.bat"', '-w -i -n');

            Console::line('Successful', true, max(73 - strlen($message), 1));
        }
    }

    final public function startApache($askConfirm = true, $question = null)
    {
        if ($this->isApacheRunning()) {
            $this->restartApache($askConfirm, ($question ? str_replace('start', 'restart', $question) : null));
        } else {
            if ($askConfirm) {
                $question = $question ?: 'Are you sure you want to start Apache?';
                $confirm  = Console::confirm($question);

                Console::breakline();
            } else {
                $confirm = true;
            }

            if ($confirm) {
                $message = 'Starting Apache Httpd...';

                Console::line($message, false);

            $this->powerExec('"' . $this->paths['xamppDir'] . '\apache_start.bat"', '-i -n');

                Console::line('Successful', true, max(73 - strlen($message), 1));
            }
        }
    }

    final public function restartApache($askConfirm = true, $question = null)
    {
        if ($askConfirm) {
            $question = $question ?: 'Are you sure you want to restart Apache?';
            $confirm  = Console::confirm($question);

            Console::breakline();
        } else {
            $confirm = true;
        }

        if ($confirm) {
            $this->stopApache(false);
            $this->startApache(false);
        }
    }

    final public function grantPermsWinHosts($askConfirm = true)
    {
        if ($askConfirm) {
            Console::line('This action will grant "Modify, Read & execute, Read, Write" permissions to the Windows hosts file for the "Users" account.');
            Console::line('This makes it possible for XVHM to add the host name to the Windows hosts file every time you create a virtual host.');
            Console::breakline();

            $confirm  = Console::confirm('Do you want to do this action?');
        } else {
            $confirm = true;
        }

        if ($confirm) {
            $message = 'Granting necessary permissions to Windows hosts file...';

            Console::line($message, false);

            /*------------------------------------------------------------------------------------
            * Now, we will grant "Modify, Read & execute, Read, Write" permissions for the "Users"
            * account using the "icacls" windows command.
            * -----------------------------------------------------------------------------------
            * Note:
            *  - The "icacls" need the Administrator right to run.
            *  - We will use the "Well-known SID" instead of the account name to avoid the case
            *    that the user's operating system uses different languages.
            *
            * Link: https://docs.microsoft.com/vi-vn/windows/win32/secauthz/well-known-sids
            *----------------------------------------------------------------------------------*/
            $this->powerExec('icacls "' . $this->paths['winHostsFile'] . '" /grant "*S-1-5-32-545":MRXRW', '-w -i -e -n', $arrOutput, $exitCode);

            if ($exitCode == 0) {
                Console::line('Successful', true, max(73 - strlen($message), 1));

                return true;
            }

            Console::line('Failed', true, max(77 - strlen($message), 1));

            return false;
        }
    }

    final protected function powerExec($command, $arguments = null, &$outputArray = null, &$statusCode = null)
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
        $outputArray = [$message];
        $statusCode  = 1;

        return $message;
    }

    final protected function isApacheRunning()
    {
        $lastRow = exec('tasklist /NH /FI "IMAGENAME eq httpd.exe" 2>nul', $output, $status);

        if ($lastRow == 'INFO: No tasks are running which match the specified criteria.') {
            return false;
        }

        return true;
    }

    final protected function checkInstalled()
    {
        if (!$this->setting->storageExists() || !$this->createdCaCert() || !$this->addedCaCertToStore()) {
            $this->requireInstall();
        }
    }

    final protected function createdCaCert()
    {
        $files = [
            'cacert.crt',
            'cacert.key',
            'cacert.key.pem',
            'cacert.pem'
        ];

        foreach ($files as $file) {
            if (!is_file($this->paths['caCertDir'] . '\\' . $file)) {
                return false;
            }
        }

        return true;
    }

    final protected function addedCaCertToStore()
    {
        $certSerial = null;

        // Get Cert serial number
        exec('CertUtil -enterprise -silent -verify "'. $this->paths['caCertDir'] .'\cacert.crt"', $arrOutput, $exitCode);

        foreach ($arrOutput as $line) {
            $line = trim($line);

            if (preg_match('/^Cert Serial Number\:\s+([^\s]+)\s*$/', $line, $matches)) {
                $certSerial = $matches[1];

                break;
            }
        }

        if ($certSerial) {
            $arrOutput = [];

            exec('CertUtil -silent -enterprise -verifystore Root ' . $certSerial, $arrOutput, $exitCode);

            foreach ($arrOutput as $line) {
                if (trim($line) == 'Serial Number: ' . $certSerial) {
                    return true;
                }
            }
        }

        return false;
    }

    final protected function requireInstall()
    {
        Console::breakline();
        Console::line('Xampp vHosts Manager has not been integrated into Xampp.');
        Console::line('Run command "xvhost install" in Administartor mode to integrate it.');
        Console::terminate(null, 1);
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

    private function defineAppPaths()
    {
        $appDir = realpath(getenv('XVHM_APP_DIR'));
        $srcDir = $appDir . '\src';

        $this->paths['appDir']                 = $appDir;
        $this->paths['tmpDir']                 = $appDir . '\tmp';
        $this->paths['caCertDir']              = $appDir . '\cacert';
        $this->paths['settingsStorage']        = $appDir . '\settings.ini';
        $this->paths['caCertGenScript']        = $srcDir . '\Tools\cacert_generate.bat';
        $this->paths['caCertGenConfig']        = $srcDir . '\Tools\cacert_generate.cnf';
        $this->paths['vhostConfigTemplate']    = $srcDir . '\Templates\vhost_config\vhost.conf.tpl';
        $this->paths['vhostSSLConfigTemplate'] = $srcDir . '\Templates\vhost_config\vhost_ssl.conf.tpl';
        $this->paths['vhostCertGenScript']     = $srcDir . '\Tools\vhostcert_generate.bat';
        $this->paths['vhostCertGenConfig']     = $srcDir . '\Tools\vhostcert_generate.cnf';
        $this->paths['pathRegister']           = $srcDir . '\Tools\path_register.vbs';
        $this->paths['powerExecutor']          = $srcDir . '\Tools\power_exec.vbs';

        if (array_key_exists('SystemRoot', $_SERVER)) {
            $this->paths['winHostsFile'] = realpath($_SERVER['SystemRoot'] . '\System32\drivers\etc\hosts');
        } else {
            $this->paths['winHostsFile'] = realpath($_SERVER['SYSTEMROOT'] . '\System32\drivers\etc\hosts');
        }

        // Prepare TMP dir
        if (! is_dir($this->paths['tmpDir'])) {
            @mkdir($this->paths['tmpDir'], 0755, true);
        }

        // Set environment variables
        putenv('XVHM_TMP_DIR=' . $this->paths['tmpDir']);
        putenv('XVHM_CACERT_DIR=' . $this->paths['caCertDir']);
        putenv('XVHM_CACERT_GENERATE_CONFIG=' . $this->paths['caCertGenConfig']);
        putenv('XVHM_VHOST_CERT_GENERATE_CONFIG=' . $this->paths['vhostCertGenConfig']);
    }

    private function detectXamppPaths()
    {
        // Force reload settings
        $this->setting->reload();

        $xamppDir  = realpath($this->setting->get('DirectoryPaths', 'Xampp'));
        $apacheDir = $this->setting->get('DirectoryPaths', 'Apache');
        $phpDir    = $this->setting->get('DirectoryPaths', 'Php');

        // define Xampp directory path
        if (! $xamppDir || ! maybe_xamppdir($xamppDir)) {
            Console::breakline();

            $message = 'Cannot find Xampp directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the Xampp directory in file "' . $this->paths['settingsStorage'] . '".';

            Console::terminate($message, 1);
        }

        $this->paths['xamppDir'] = $xamppDir;

        // define Apache directory path
        if (! $apacheDir) {
            $apacheDir = $xamppDir . '\apache';
        }

        if (! maybe_apachedir($apacheDir)) {
            Console::breakline();

            $message = 'Cannot find Apache directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the Apache directory in file "' . $this->paths['settingsStorage'] . '".';

            Console::terminate($message, 1);
        }

        $this->paths['apacheDir'] = $apacheDir;

        // define PHP directory path
        if (! $phpDir) {
            $phpDir = $xamppDir . '\php';
        }

        if (! maybe_phpdir($phpDir)) {
            Console::breakline();

            $message = 'Cannot find PHP directory.' . PHP_EOL;
            $message .= 'Please check the configuration path to the PHP directory in file "' . $this->paths['settingsStorage'] . '".';

            Console::terminate($message, 1);
        }

        $this->paths['phpDir'] = $phpDir;

        return $this;
    }
}
