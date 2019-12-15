<?php

define('DS', DIRECTORY_SEPARATOR);

require_once __DIR__.'/Console.php';
require_once __DIR__.'/Setting.php';

class Manager
{
    protected $settings = null;
    protected $paths    = array();
    protected $vhosts   = array();

    public function __construct()
    {
        Console::setDefaultMessages(array('terminate' => 'Xampp vHosts Manager is terminating...'));

        $this->loadPaths();
        $this->loadSettings();
        $this->loadAllHosts();
    }

    private function loadPaths()
    {
        // Dir paths
        $this->paths['appDir']            = realpath($_ENV['XVHM_APP_DIR']);
        $this->paths['xamppDir']          = realpath($_ENV['XVHM_XAMPP_DIR']);
        $this->paths['apacheDir']         = realpath($_ENV['XVHM_APACHE_DIR']);
        $this->paths['vhostConfigDir']    = $this->prepareVhostConfigDir();
        $this->paths['vhostSSLConfigDir'] = $this->prepareVhostSSLConfigDir();
        $this->paths['vhostCertDir']      = $this->prepareVhostCertDir();
        $this->paths['vhostCertKeyDir']   = $this->prepareVhostCertKeyDir();

        // File paths
        $this->paths['winHostsFile']           = realpath($_SERVER['SystemRoot'] . '\System32\drivers\etc\hosts');
        $this->paths['vhostConfigTemplate']    = $_ENV['XVHM_VHOST_CONFIG_TEMPLATE'];
        $this->paths['vhostSSLConfigTemplate'] = $_ENV['XVHM_VHOST_SSL_CONFIG_TEMPLATE'];
        $this->paths['vhostCertGenerator']     = $_ENV['XVHM_VHOST_CERT_GENERATOR'];
        $this->paths['hostsfilePermsGrantor']  = $_ENV['XVHM_HOSTSFILE_PERMS_GRANTOR'];
        $this->paths['apacheStartImplementer'] = $_ENV['XVHM_APACHE_START_IMPLEMENTER'];
        $this->paths['apacheStopImplementer']  = $_ENV['XVHM_APACHE_STOP_IMPLEMENTER'];
    }

    public function showHostInfo($hostName = null, $processStandalone = true, $indentSpaces = 2)
    {
        $hostName = $this->tryGetHostName($hostName);

        if (! $this->isExistHost($hostName)) {
            Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
        }

        $hostInfo     = $this->vhosts[$hostName];
        $addedSSL     = $this->isSSLHost($hostInfo);
        $indentString = str_repeat(' ', $indentSpaces);

        if ($processStandalone) {
            Console::line('The following is information about the virtual host that you requested:');
            Console::breakline();
        }

        Console::line($indentString . '- Host port             : ' . $hostInfo['hostPort']);
        Console::line($indentString . '- Host name             : ' . $hostInfo['serverName']);
        Console::line($indentString . '- Host alias            : ' . $hostInfo['serverAlias']);
        Console::line($indentString . '- Admin email           : ' . $hostInfo['serverAdmin']);
        Console::line($indentString . '- Document root         : ' . $hostInfo['documentRoot']);
        Console::line($indentString . '- Host config file      : ' . $hostInfo['vhostConfigFile']);
        Console::line($indentString . '- Added SSL certificate : ' . (($addedSSL) ? 'Yes' : 'No'));

        if ($addedSSL) {
            Console::line($indentString . '  ---------------------');
            Console::line($indentString . '- SSL port              : ' . $hostInfo['sslPort']);
            Console::line($indentString . '- SSL config file       : ' . $hostInfo['sslConfigFile']);
            Console::line($indentString . '- SSL certificate file  : ' . $hostInfo['certFile']);
            Console::line($indentString . '- SSL private key file  : ' . $hostInfo['certKeyFile']);
        }
    }

    public function listHosts()
    {
        $totalFound = count($this->vhosts);

        if ($totalFound <= 0) {
            Console::terminate('Not found any virtual host.');
        }

        Console::line('There are ' . $totalFound . ' virtual ' . (($totalFound == 1) ? 'host' : 'hosts') . ' found:');

        $count = 0;
        foreach ($this->vhosts as $hostName => $hostInfo) {
            $count++;

            Console::breakline();
            Console::hrline();
            Console::line($count . '. ' . $hostName);
            Console::breakline();
            $this->showHostInfo($hostName, false);

            $recordPerPage = $this->getSetting('ListViewMode', 'RecordPerPage', 2);

            if ($count < $totalFound && ($count % max(intval($recordPerPage), 2)) == 0) {
                $remain = $totalFound - $count;

                Console::breakline();
                Console::line('There are still ' .$remain. ' more ' . (($remain > 1) ? 'records' : 'record') . '.');
                $continue = Console::confirm('>>> Do you want to continue view more?');

                if (! $continue) {
                    Console::terminate();
                }
            }
        }
    }

    public function removeHost($hostName = null)
    {
        $hostName = $this->tryGetHostName($hostName);

        if (! $this->isExistHost($hostName)) {
            Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
        }

        $confirmRemove = Console::confirm('Are you sure you want to remove virtual host "' . $hostName . '"?', false);

        if (! $confirmRemove) {
            Console::terminate();
        }

        Console::breakline();
        Console::hrline();
        Console::line('Start remove virtual host "' . $hostName . '".');
        Console::breakline();

        $hostInfo = $this->vhosts[$hostName];

        // Remove vhost config file
        $this->removeFile($hostInfo['vhostConfigFile'], 'Removing host config file...');

        if (! is_null($hostInfo['sslConfigFile'])) {
            // Remove vhost SSL config file
            $this->removeFile($hostInfo['sslConfigFile'], 'Removing SSL config file...');
        }

        if (! is_null($hostInfo['certFile'])) {
            // Remove SSL certificate file
            $this->removeFile($hostInfo['certFile'], 'Removing SSL certificate file...');
        }

        if (! is_null($hostInfo['certKeyFile'])) {
            // Remove SSL certificate key file
            $this->removeFile($hostInfo['certKeyFile'], 'Removing SSL certificate key file...');
        }

        // Remove host name in Windows hosts file
        $this->removeHostInWindowsHost(array($hostInfo['serverName'], $hostInfo['serverAlias']));

        unset($this->vhosts[$hostName]);

        // Ask restart Apache
        Console::breakline();
        Console::hrline();
        $this->askRestartApache();
    }

    public function addSSLtoHost($hostName = null, $processStandalone = true)
    {
        $hostName = $this->tryGetHostName($hostName);

        if (! $this->isExistHost($hostName)) {
            Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
        }

        $hostInfo = $this->vhosts[$hostName];

        if ($this->isSSLHost($hostInfo)) {
            Console::terminate('Sorry! This host has been added an SSL certificate.');
        }

        $sslPort = $this->getSetting('Suggestions', 'HostSSLPort', '443');

        if ($processStandalone) {
            // Start adding
            Console::hrline();
            Console::line('Start adding SSL certificate for virtual host "' . $hostName . '".');
            Console::breakline();
        }

        // Create the certificate and private key file
        $createdCertAndKey = $this->createCertKeyFile($hostName);

        if (! $createdCertAndKey) {
            if ($processStandalone) {
                Console::breakline();
                Console::terminate('Error while create SSL certificate files');
            } else {
                Console::line('Cancelling adding SSL for this host...');
                return;
            }
        }

        // Create vhost SSL config file
        $sslConfigFile = $this->createSSLConfigFile($hostName, $sslPort, $hostInfo['serverAdmin'], $hostInfo['documentRoot']);

        if (is_null($sslConfigFile)) {
            if ($processStandalone) {
                Console::breakline();
                Console::terminate('Error while create SSL config file');
            } else {
                Console::line('Cancelling adding SSL for this host...');
                return;
            }
        }

        // Update vhosts info
        $this->vhosts[$hostName]['sslPort']       = $sslPort;
        $this->vhosts[$hostName]['sslConfigFile'] = $sslConfigFile;
        $this->vhosts[$hostName]['certFile']      = $this->paths['vhostCertDir'] .DS. $hostName . '.cert';
        $this->vhosts[$hostName]['certKeyFile']   = $this->paths['vhostCertKeyDir'] .DS. $hostName . '.key';

        if ($processStandalone) {
            // Show recent host info
            Console::breakline();
            Console::hrline();
            Console::line('The following is information about the virtual host that just updated:');
            Console::breakline();
            $this->showHostInfo($hostName, false);

            // Ask restart Apache
            Console::breakline();
            Console::hrline();
            $this->askRestartApache();
        }
    }

    public function removeSSLOfHost($hostName)
    {
        $hostName = $this->tryGetHostName($hostName);

        if (! $this->isExistHost($hostName)) {
            Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
        }

        $hostInfo = $this->vhosts[$hostName];

        if (! $this->isSSLHost($hostInfo)) {
            Console::terminate('Sorry! This host has not added an SSL certificate yet.');
        }

        $confirmRemove = Console::confirm('Are you sure you want to remove SSL for virtual host "' . $hostName . '"?', false);

        if (! $confirmRemove) {
            Console::terminate();
        }

        // Start removing
        Console::breakline();
        Console::hrline();
        Console::line('Start remove SSL certificate for virtual host "' . $hostName . '".');
        Console::breakline();

        // Remove vhost SSL config file
        $this->removeFile($hostInfo['sslConfigFile'], 'Removing vhost SSL config file...');

        // Remove SSL certificate file
        $this->removeFile($hostInfo['certFile'], 'Removing SSL certificate file...');

        // Remove SSL certificate key file
        $this->removeFile($hostInfo['certKeyFile'], 'Removing SSL certificate key file...');

        // Update vhosts info
        $this->vhosts[$hostName]['sslPort']       = null;
        $this->vhosts[$hostName]['sslConfigFile'] = null;
        $this->vhosts[$hostName]['certFile']      = null;
        $this->vhosts[$hostName]['certKeyFile']   = null;

        // Show recent host info
        Console::breakline();
        Console::hrline();
        Console::line('The following is information about the virtual host that just updated:');
        Console::breakline();
        $this->showHostInfo($hostName, false);

        // Ask restart Apache
        Console::breakline();
        Console::hrline();
        $this->askRestartApache();
    }

    public function newHost($hostName = null)
    {
        $hostName = $this->tryGetHostName($hostName);

        if ($this->isExistHost($hostName)) {
            Console::terminate('The host name you provided currently exists.');
        }

        $defaultDocRoot = $this->getSetting('Suggestions', 'DocumentRoot', $this->paths['xamppDir'] . '\htdocs\{{host_name}}');
        $documentRoot   = Console::ask('Enter the path to document root for this host', str_replace('{{host_name}}', $hostName, $defaultDocRoot));
        $documentRoot   = $this->normalizeDocumentRoot($documentRoot);

        Console::breakline();
        $adminEmail = Console::ask('Enter admin email for this host', $this->getSetting('Suggestions', 'AdminEmail', 'webmaster@example.com'));

        Console::breakline();
        $addSSL   = Console::confirm('Do you want to add SSL certificate for this host?');
        $hostPort = $this->getSetting('Suggestions', 'HostPort', '80');

        // Start adding
        Console::breakline();
        Console::hrline();
        Console::line('Start add new virtual host "' . $hostName . '".');
        Console::breakline();

        if (! is_dir($documentRoot)) {
            // Create docuemnt root
            $createdDocumentRoot = $this->createDocumentRoot($documentRoot);

            if (! $createdDocumentRoot) {
                Console::breakline();
                Console::terminate('Error while creating document root.');
            }
        }

        // Create vhost config file
        $vhostConfigFile = $this->createHostConfigFile($hostName, $hostPort, $adminEmail, $documentRoot);

        if (is_null($vhostConfigFile)) {
            Console::breakline();
            Console::terminate('Error while create host config file');
        }

        // Update vhosts info
        $recentHostInfo = $this->getHostInfo($vhostConfigFile);
        $serverName     = $recentHostInfo['serverName'];
        $serverAlias    = $recentHostInfo['serverAlias'];
        $this->vhosts[$serverName] = $recentHostInfo;

        // Add host name to Windows hosts file
        $this->addHostToWindowsHost(array($serverName, $serverAlias));

        if ($addSSL) {
            // Start adding
            Console::breakline();
            Console::hrline();
            Console::line('Start add SSL for virtual host "' . $hostName . '".');
            Console::breakline();

            // Add SSL certificate for this host
            $this->addSSLtoHost($serverName, false);
        }

        // Show recent host info
        Console::breakline();
        Console::hrline();
        Console::line('The following is information about the virtual host that just created:');
        Console::breakline();
        $this->showHostInfo($serverName, false);

        // Ask restart Apache
        Console::breakline();
        Console::hrline();
        $this->askRestartApache();
    }

    private function prepareVhostConfigDir()
    {
        $vhostsConfigDir = $_ENV['XVHM_VHOST_CONFIG_DIR'];

        if (! is_dir($vhostsConfigDir)) {
            mkdir($vhostsConfigDir, 0755, true);
        }

        return realpath($vhostsConfigDir);
    }

    private function prepareVhostSSLConfigDir()
    {
        $vhostsSSLConfigDir = $_ENV['XVHM_VHOST_SSL_CONFIG_DIR'];

        if (! is_dir($vhostsSSLConfigDir)) {
            mkdir($vhostsSSLConfigDir, 0755, true);
        }

        return realpath($vhostsSSLConfigDir);
    }

    private function prepareVhostCertDir()
    {
        $vhostsCertDir = $_ENV['XVHM_VHOST_CERT_DIR'];

        if (! is_dir($vhostsCertDir)) {
            mkdir($vhostsCertDir, 0755, true);
        }

        return realpath($vhostsCertDir);
    }

    private function prepareVhostCertKeyDir()
    {
        $vhostsCertKeyDir = $_ENV['XVHM_VHOST_CERT_KEY_DIR'];

        if (! is_dir($vhostsCertKeyDir)) {
            mkdir($vhostsCertKeyDir, 0755, true);
        }

        return realpath($vhostsCertKeyDir);
    }

    private function reduceApachePath($path, $directorySeparator = DS)
    {
        $apachePath = str_replace('/', DS, $this->paths['apacheDir']);
        $path       = str_replace('/', DS, $path);

        if (substr($path, 0, strlen($apachePath)) == $apachePath) {
            $path = substr($path, strlen($apachePath . DS));
        }

        return str_replace(DS, $directorySeparator, $path);
    }

    private function normalizeHostName($hostName)
    {
        $hostName = trim($hostName);
        $hostName = str_replace(' ', '', $hostName);
        $hostName = str_replace('www.', '', $hostName);

        return $hostName;
    }

    private function normalizeDocumentRoot($documentRoot)
    {
        $documentRoot = trim($documentRoot, './\\');
        $documentRoot = str_replace('/', DS, $documentRoot);

        if (strpos($documentRoot, ':') !== false) {
            return $documentRoot;
        }

        return $this->paths['xamppDir'] .DS. $documentRoot;
    }

    private function tryGetHostName($hostName = null, $message = null)
    {
        $hostName = $this->normalizeHostName($hostName);
        $message  = $message ?: 'Enter virtual host name';

        $repeat = 0;
        while (! $hostName) {
            if ($repeat == 4) {
                Console::terminate('You have not provided enough information many times.');
            }

            if ($repeat == 0) {
                $hostName = Console::ask($message);
            } else {
                Console::line('You have not provided enough information.');
                $hostName = Console::ask($message . ' again');
            }

            Console::breakline();
            $hostName = $this->normalizeHostName($hostName);
            $repeat++;
        }

        return $hostName;
    }

    private function isSSLHost($hostInfo)
    {
        return !is_null($hostInfo['sslConfigFile']) && !is_null($hostInfo['sslPort']) && !is_null($hostInfo['certFile']) && !is_null($hostInfo['certKeyFile']);
    }

    private function isExistHost($hostName)
    {
        return array_key_exists($hostName, $this->vhosts);
    }

    private function readHostConfigFile($vhostConfigFile)
    {
        $lines  = file($vhostConfigFile);
        $config = array();

        $start_tag_config = false;
        $end_tag_config   = false;
        $current_tag      = null;
        $current_tag_item = null;
        $tempValue        = null;

        foreach ($lines as $line) {
            $line = $tempValue . trim($line);

            if (substr($line, -1) == DS) {
                $tempValue = substr($line, 0, -1);
                continue;
            } else {
                $tempValue = null;
            }

            preg_match("/^(?P<lt_symbol>\<?)(?P<slash_symbol>\/?)(?P<key>\w+)\s*(?P<value>[^\>]*)(?P<gt_symbol>\>?)/", $line, $matches);

            if (isset($matches['key'])) {
                $value = trim($matches['value']);

                if (substr($value, 0, 1) == '"' && substr($value, -1) == '"' && substr_count($value, '"') == 2) {
                    $value = trim($value, '"');
                }

                if (!empty($matches['lt_symbol']) && !empty($matches['gt_symbol'])) {
                    $tagName = $matches['key'];

                    if (empty($matches['slash_symbol'])) {
                        if ($tagName == 'VirtualHost') {
                            $config['VirtualHost'] = $value;
                        } else {
                            if (! array_key_exists($tagName, $config)) {
                                $config[$tagName] = array();
                            }

                            if (! array_key_exists($value, $config[$tagName])) {
                                $config[$tagName][$value] = array();
                            }

                            $start_tag_config = true;
                            $end_tag_config   = false;
                            $current_tag      = $tagName;
                            $current_tag_item = $value;
                        }
                    } else {
                        $start_tag_config = false;
                        $end_tag_config   = true;
                        $current_tag      = null;
                        $current_tag_item = null;
                    }
                } else {
                    if ($start_tag_config && (! $end_tag_config) && (! is_null($current_tag)) && (! is_null($current_tag_item))) {
                        $config[$current_tag][$current_tag_item][$matches['key']] = $value;
                    } else {
                        $config[$matches['key']] = $value;
                    }
                }
            }
        }

        return $config;
    }

    private function getHostInfo($vhostConfigFile)
    {
        if (! is_file($vhostConfigFile)) {
            Console::terminate('The vhost config file with name "' . $vhostConfigFile . '" does not exist.');
        }

        $baseName  = basename($vhostConfigFile, ".conf");

        $sslConfigFile = $this->paths['vhostSSLConfigDir'] .DS. $baseName . '.conf';
        $sslConfigFile = (is_file($sslConfigFile)) ? realpath($sslConfigFile) : null;

        $certFile = $this->paths['vhostCertDir'] .DS. $baseName . '.cert';
        $certFile = (is_file($certFile)) ? realpath($certFile) : null;

        $certKeyFile = $this->paths['vhostCertKeyDir'] .DS. $baseName . '.key';
        $certKeyFile = (is_file($certKeyFile)) ? realpath($certKeyFile) : null;

        $vhostConfigs = $this->readHostConfigFile($vhostConfigFile);
        $hostPort     = explode(':', $vhostConfigs['VirtualHost'])[1];

        if (! is_null($sslConfigFile)) {
            $sslConfigs = $this->readHostConfigFile($sslConfigFile);
            $sslPort    = explode(':', $sslConfigs['VirtualHost'])[1];
        } else {
            $sslPort = null;
        }

        $output = array(
            'vhostConfigFile' => $vhostConfigFile,
            'hostPort'        => $hostPort,
            'serverName'      => $vhostConfigs['ServerName'],
            'serverAlias'     => $vhostConfigs['ServerAlias'] ?: null,
            'serverAdmin'     => $vhostConfigs['ServerAdmin'] ?: null,
            'documentRoot'    => str_replace('/', DS, $vhostConfigs['DocumentRoot']),
            'sslConfigFile'   => $sslConfigFile,
            'sslPort'         => $sslPort,
            'certFile'        => $certFile,
            'certKeyFile'     => $certKeyFile
        );

        return $output;
    }

    private function loadAllHosts()
    {
        // open vhosts dir
        if (! $handle = opendir($this->paths['vhostConfigDir'])) {
            return;
        }

        $hosts = array();

        // read all matching files
        while ($item = readdir($handle)) {
            if ($item == '.' || $item == '..' || substr($item, -5) !== '.conf') {
                continue;
            }

            $hostInfo   = $this->getHostInfo($this->paths['vhostConfigDir'] .DS. $item);
            $serverName = $hostInfo['serverName'];

            if (! array_key_exists($serverName, $hosts)) {
                $hosts[$serverName] = $hostInfo;
            }
        }

        uksort($hosts, 'strnatcmp');

        $this->vhosts = $hosts;

        closedir($handle);
    }

    private function loadSettings()
    {
        $xampp_settings = parse_ini_file($this->paths['xamppDir'] . '\xampp-control.ini', true);

        if ($xampp_settings['ServicePorts']['Apache'] && is_numeric($xampp_settings['ServicePorts']['Apache'])) {
            $default_port = $xampp_settings['ServicePorts']['Apache'];
        } else {
            $default_port = 80;
        }

        if ($xampp_settings['ServicePorts']['ApacheSSL'] && is_numeric($xampp_settings['ServicePorts']['ApacheSSL'])) {
            $default_ssl_port = $xampp_settings['ServicePorts']['ApacheSSL'];
        } else {
            $default_ssl_port = 443;
        }

        $settings = new Setting;
        $settings->set('Suggestions', 'HostPort', $default_port);
        $settings->set('Suggestions', 'HostSSLPort', $default_ssl_port);

        $this->settings = $settings;
    }

    private function getSetting($sectionName, $settingName, $defaultValue = null)
    {
        return $this->settings->get($sectionName, $settingName, $defaultValue);
    }

    private function createDocumentRoot($dirPath)
    {
        $message = 'Creating document root for virtual host...';

        Console::line($message, false);

        mkdir($dirPath, 0755, true);

        if (is_dir($dirPath)) {
            Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
            return true;
        }

        Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
        return false;
    }

    private function createHostConfigFile($hostName, $hostPort, $adminEmail, $documentRoot)
    {
        $message = 'Generating host config file...';
        Console::line($message, false);

        $search   = array();
        $search[] = '{{host_name}}';
        $search[] = '{{host_port}}';
        $search[] = '{{admin_email}}';
        $search[] = '{{document_root}}';

        $replace   = array();
        $replace[] = $hostName;
        $replace[] = $hostPort;
        $replace[] = $adminEmail;
        $replace[] = str_replace(DS, '/', $documentRoot);

        $template   = $this->paths['vhostConfigTemplate'];
        $configFile = $this->paths['vhostConfigDir'] .DS. $hostName . '.conf';
        $content    = str_replace($search, $replace, file_get_contents($template));

        if (file_put_contents($configFile, $content)) {
            Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
            return realpath($configFile);
        }

        Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
        return null;
    }

    private function createSSLConfigFile($hostName, $sslPort, $adminEmail, $documentRoot)
    {
        $message = 'Generating SSL config file...';
        Console::line($message, false);

        $search    = array();
        $search[]  = '{{host_name}}';
        $search[]  = '{{ssl_port}}';
        $search[]  = '{{admin_email}}';
        $search[]  = '{{document_root}}';
        $search[]  = '{{cert_file}}';
        $search[]  = '{{cert_key_file}}';

        $replace   = array();
        $replace[] = $hostName;
        $replace[] = $sslPort;
        $replace[] = $adminEmail;
        $replace[] = str_replace(DS, '/', $documentRoot);
        $replace[] = $this->reduceApachePath($this->paths['vhostCertDir'], '/') . '/' . $hostName . '.cert';
        $replace[] = $this->reduceApachePath($this->paths['vhostCertKeyDir'], '/') . '/' . $hostName . '.key';

        $template   = $this->paths['vhostSSLConfigTemplate'];
        $configFile = $this->paths['vhostSSLConfigDir'] .DS. $hostName . '.conf';
        $content    = str_replace($search, $replace, file_get_contents($template));

        if (file_put_contents($configFile, $content)) {
            Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
            return realpath($configFile);
        }

        Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
        return null;
    }

    private function createCertKeyFile($hostName)
    {
        $message = 'Generating the cert and private key files...';
        Console::line($message, false);

        exec('cscript //NoLogo "' . $this->paths['vhostCertGenerator'] . '" "' . $hostName . '"');

        if (is_file($this->paths['vhostCertDir'] .DS. $hostName . '.cert') && is_file($this->paths['vhostCertKeyDir'] .DS. $hostName . '.key')) {
            Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
            return true;
        }

        Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
        return false;
    }

    private function addHostToWindowsHost($hostNames)
    {
        $message = 'Adding host name to Windows hosts file...';
        Console::line($message, false);

        if (! is_array($hostNames)) {
            $tempArr   = array();
            $tempArr[] = $hostNames;

            $hostNames = $tempArr;
        }

        if (count($hostNames) > 0) {
            $content = null;

            foreach ($hostNames as $hostName) {
                $ipHost = '127.0.0.1   ' . $hostName;

                if ($hostName != 'localhost') {
                    $content .= PHP_EOL . $ipHost . str_repeat(' ', max(48 - strlen($ipHost), 1)) . '# Xampp virtual host';
                }
            }

            if (! is_null($content)) {
                $result = @file_put_contents($this->paths['winHostsFile'], $content, FILE_APPEND);

                if (! $result) {
                    Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
                    return false;
                }
            }
        }

        Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
        return true;
    }

    private function removeHostInWindowsHost($hostNames)
    {
        $message = 'Removing host name in Windows hosts file...';
        Console::line($message, false);

        if (! is_array($hostNames)) {
            $tempArr   = array();
            $tempArr[] = $hostNames;

            $hostNames = $tempArr;
        }

        if (count($hostNames) > 0) {
            $lines = file($this->paths['winHostsFile']);
            $keepLines = array();

            foreach ($lines as $line) {
                $line = trim($line);
                $matchLine = false;

                foreach ($hostNames as $hostName) {
                    preg_match("/^127\.0\.0\.1\s+" . preg_quote($hostName) . "\s*\#?.*/", $line, $matches);

                    if (isset($matches[0])) {
                        $matchLine = true;
                        break;
                    }
                }

                if (! $matchLine) {
                    $keepLines[] = $line;
                }
            }

            $content = implode(PHP_EOL, $keepLines);
            $updated = @file_put_contents($this->paths['winHostsFile'], $content);

            if (! $updated) {
                Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
                return false;
            }
        }

        Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
        return true;
    }

    private function removeFile($filePath, $message = null)
    {
        if (! is_null($message)) {
            Console::line($message, false);
            $removed = @unlink($filePath);

            if ($removed) {
                Console::line(str_repeat(' ', max(73 - strlen($message), 1)) . 'Successful');
            } else {
                Console::line(str_repeat(' ', max(77 - strlen($message), 1)) . 'Failed');
            }

            return $removed;
        }

        return @unlink($filePath);
    }

    private function askRestartApache()
    {
        $restartApache = Console::confirm('Do you want to restart Apache?');

        if ($restartApache) {
            Console::breakline();
            Console::line('Stopping Apache Httpd...');
            exec('cscript //NoLogo "' . $this->paths['apacheStopImplementer'] . '"');

            Console::line('Starting Apache Httpd...');
            exec('cscript //NoLogo "' . $this->paths['apacheStartImplementer'] . '"');
        }
    }
}
