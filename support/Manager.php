<?php

define('DS', DIRECTORY_SEPARATOR);

require_once __DIR__.'/Application.php';

class Manager extends Application
{
    protected $vhosts = array();

    public function __construct()
    {
        if (! is_file(getenv('XVHM_APP_DIR') . '\settings.ini')) {
            $this->requireInstall();
        }

        parent::__construct();

        if (! is_file($this->paths['caCertDir'] . '\cacert.crt')) {
            $this->requireInstall();
        }

        $this->prepareDirectories();
        $this->loadApacheSettings();
        $this->loadAllHosts();
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

        Console::line($indentString . '- Url                   : ' . (($addedSSL) ? 'https://' : 'http://') . $hostInfo['serverName']);
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

        if ($processStandalone) {
            Console::breakline();
            Console::hrline();
            $showMore = Console::confirm('Do you want to show information of another virtual host?');

            if ($showMore) {
                Console::breakline();
                $this->showHostInfo();
            }

            Console::breakline();
            Console::terminate('All jobs are completed.');
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
            Console::line($count . '. [' . $hostName . ']');
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

        Console::breakline();
        Console::hrline();
        Console::terminate('Your request is completed.');
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

        // Ask to remove another
        Console::breakline();
        Console::hrline();
        $removeMore = Console::confirm('Do you want to remove another virtual host?');

        if ($removeMore) {
            Console::breakline();
            $this->removeHost();
        }

        // Ask restart Apache
        parent::restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
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
                Console::terminate('Error while create SSL certificate files', 1);
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
                Console::terminate('Error while create SSL config file', 1);
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
            Console::line('The following is information about the virtual host that just updated:');
            Console::breakline();
            $this->showHostInfo($hostName, false);

            // Ask to add SSL for another
            Console::breakline();
            Console::hrline();
            $addMore = Console::confirm('Do you want to add SSL for another virtual host?');

            if ($addMore) {
                Console::breakline();
                $this->addSSLtoHost();
            }

            // Ask restart Apache
            parent::restartApache();

            Console::breakline();
            Console::terminate('All jobs are completed.');
        }
    }

    public function removeSSLOfHost($hostName = null)
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
        Console::line('The following is information about the virtual host that just updated:');
        Console::breakline();
        $this->showHostInfo($hostName, false);

        // Ask to remove SSL for another
        Console::breakline();
        Console::hrline();
        $removeMore = Console::confirm('Do you want to remove SSL for another virtual host?');

        if ($removeMore) {
            Console::breakline();
            $this->removeSSLOfHost();
        }

        // Ask restart Apache
        parent::restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    public function newHost($hostName = null)
    {
        $hostName = $this->tryGetHostName($hostName);

        if ($this->isExistHost($hostName)) {
            Console::terminate('The host name you provided currently exists.');
        }

        $suggestDocRoot = $this->getSetting('Suggestions', 'DocumentRoot', $this->paths['xamppDir'] . '\htdocs\{{host_name}}');
        $documentRoot   = Console::ask('Enter the path to document root for this host', str_replace('{{host_name}}', $hostName, $suggestDocRoot));
        $documentRoot   = $this->normalizeDocumentRoot($documentRoot);

        Console::breakline();
        $adminEmail = Console::ask('Enter admin email for this host', $this->getSetting('Suggestions', 'AdminEmail', 'webmaster@example.com'));

        Console::breakline();
        $addSSL   = Console::confirm('Do you want to add SSL certificate for this host?');
        $hostPort = $this->getSetting('Suggestions', 'HostPort', '80');

        // Start adding
        Console::breakline();
        Console::hrline();
        Console::line('Start creating new virtual host "' . $hostName . '".');
        Console::breakline();

        if (! is_dir($documentRoot)) {
            // Create docuemnt root
            $this->createDocumentRoot($documentRoot);
        }

        // Create vhost config file
        $vhostConfigFile = $this->createHostConfigFile($hostName, $hostPort, $adminEmail, $documentRoot);

        if (is_null($vhostConfigFile)) {
            Console::breakline();
            Console::terminate('Error while create host config file', 1);
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
            Console::line('Start adding SSL for virtual host "' . $hostName . '".');
            Console::breakline();

            // Add SSL certificate for this host
            $this->addSSLtoHost($serverName, false);
        }

        // Show recent host info
        Console::breakline();
        Console::line('The following is information about the virtual host that just created:');
        Console::breakline();
        $this->showHostInfo($serverName, false);

        // Ask to create more
        Console::breakline();
        Console::hrline();
        $createMore = Console::confirm('Do you want to create another virtual host?');

        if ($createMore) {
            Console::breakline();
            $this->newHost();
        }

        // Ask restart Apache
        parent::restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    public function changeDocRoot($hostName = null)
    {
        $hostName = $this->tryGetHostName($hostName);

        if (! $this->isExistHost($hostName)) {
            Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
        }

        $hostInfo = $this->vhosts[$hostName];

        Console::line('Current Document Root of this vhost is: ' . $hostInfo['documentRoot']);
        Console::breakline();

        $newDocRoot = Console::ask('Enter new path to document root for this host');
        $newDocRoot = trim($newDocRoot);
        Console::breakline();

        if (empty($newDocRoot)) {
            Console::terminate('Nothing to change.');
        }

        $newDocRoot = $this->normalizeDocumentRoot($newDocRoot);

        if ($newDocRoot == $hostInfo['documentRoot']) {
            Console::terminate('Nothing to change.');
        }

        Console::hrline();
        Console::line('Start changing document root for virtual host "' . $hostName . '".');
        Console::breakline();

        if (! is_dir($newDocRoot)) {
            $this->createDocumentRoot($newDocRoot, 'Creating new document root for virtual host...');
        }

        $message = 'Updating host config file...';
        Console::line($message, false);

        $vhostConfigContent    = @file_get_contents($hostInfo['vhostConfigFile']);
        $replacePattern        = '/' . preg_quote(str_replace(DS, '/', $hostInfo['documentRoot']), '/') . '/';
        $vhostConfigContent    = preg_replace($replacePattern, str_replace(DS, '/', $newDocRoot), $vhostConfigContent);
        $vhostConfigFileUpdate = @file_put_contents($hostInfo['vhostConfigFile'], $vhostConfigContent);

        if (! $vhostConfigFileUpdate) {
            Console::line('Failed', true, max(77 - strlen($message), 1));
            Console::breakline();
            Console::terminate('Error while updating host config file.', 1);
        }

        Console::line('Successful', true, max(73 - strlen($message), 1));

        if ($this->isSSLHost($hostInfo)) {
            $message = 'Updating SSL config file...';
            Console::line($message, false);

            $sslConfigContent    = @file_get_contents($hostInfo['sslConfigFile']);
            $replacePattern      = '/' . preg_quote(str_replace(DS, '/', $hostInfo['documentRoot']), '/') . '/';
            $sslConfigContent    = preg_replace($replacePattern, str_replace(DS, '/', $newDocRoot), $sslConfigContent);
            $sslConfigFileUpdate = @file_put_contents($hostInfo['sslConfigFile'], $sslConfigContent);

            if (! $sslConfigFileUpdate) {
                Console::line('Failed', true, max(77 - strlen($message), 1));
                Console::breakline();
                Console::terminate('Error while updating SSL config file.', 1);
            }

            Console::line('Successful', true, max(73 - strlen($message), 1));
        }

        // Update vhosts info
        $this->vhosts[$hostName]['documentRoot'] = $newDocRoot;

        // Show recent host info
        Console::breakline();
        Console::line('The following is information about the virtual host that just updated:');
        Console::breakline();
        $this->showHostInfo($hostName, false);

        // Ask to change more
        Console::breakline();
        Console::hrline();
        $changeMore = Console::confirm('Do you want to continue changing for another virtual host?');

        if ($changeMore) {
            Console::breakline();
            $this->changeDocRoot();
        }

        // Ask restart Apache
        parent::restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    public function registerPath($askConfirm = true, $question = null)
    {
        $question = 'Do you want to change the path of XVHM to "' . $this->paths['appDir'] . '"?';
        $confirm  = Console::confirm($question);

        if ($confirm) {
            Console::breakline();
            $result = parent::registerPath(false);

            if ($result) {
                Console::terminate();
            }

            Console::terminate(null, 1);
        }
    }

    public function stopApache($askConfirm = true, $question = null)
    {
        parent::stopApache(true, 'Are you sure you want to stop Apache?');
    }

    public function startApache($askConfirm = true, $question = null)
    {
        parent::startApache(true, 'Are you sure you want to start Apache?');
    }

    public function restartApache($askConfirm = true, $question = null)
    {
        parent::restartApache(true, 'Are you sure you want to restart Apache?');
    }

    private function requireInstall()
    {
        Console::breakline();
        Console::line('Xampp vHosts Manager has not been integrated into Xampp.');
        Console::line('Run command "xvhosts install" in Administartor mode to integrate it.');
        Console::terminate(null, 1);
    }

    private function prepareDirectories()
    {
        if (! is_dir($this->paths['vhostConfigDir'])) {
            mkdir($this->paths['vhostConfigDir'], 0755, true);
        }

        if (! is_dir($this->paths['vhostSSLConfigDir'])) {
            mkdir($this->paths['vhostSSLConfigDir'], 0755, true);
        }

        if (! is_dir($this->paths['vhostCertDir'])) {
            mkdir($this->paths['vhostCertDir'], 0755, true);
        }

        if (! is_dir($this->paths['vhostCertKeyDir'])) {
            mkdir($this->paths['vhostCertKeyDir'], 0755, true);
        }
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

    private function normalizeHostName($hostName)
    {
        $hostName = trim($hostName);
        $hostName = str_replace(' ', '', $hostName);

        if (stripos($hostName, 'http://') === 0) {
            $hostName = substr($hostName, 7);
        }

        if (stripos($hostName, 'https://') === 0) {
            $hostName = substr($hostName, 8);
        }

        if (stripos($hostName, 'www.') === 0) {
            $hostName = substr($hostName, 4);
        }

        return $hostName;
    }

    private function tryGetHostName($hostName = null, $message = null)
    {
        $hostName = $this->normalizeHostName($hostName);
        $message  = $message ?: 'Enter virtual host name';

        $repeat = 0;
        while (! $hostName || ! filter_var('http://' . $hostName, FILTER_VALIDATE_URL)) {
            if ($repeat == 4) {
                Console::terminate('You have entered an incorrect format many times.', 1);
            }

            if ($repeat == 0) {
                $hostName = Console::ask($message);
            } else {
                Console::line('You have entered an incorrect format.');
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

    private function loadApacheSettings()
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

        // $settingsContainer = new Setting;
        // $settingsContainer->set('Suggestions', 'HostPort', $default_port);
        // $settingsContainer->set('Suggestions', 'HostSSLPort', $default_ssl_port);

        // $this->setting = $settingsContainer;
        $this->setting->set('Suggestions', 'HostPort', $default_port);
        $this->setting->set('Suggestions', 'HostSSLPort', $default_ssl_port);
    }

    private function getSetting($sectionName, $settingName, $defaultValue = null)
    {
        return $this->setting->get($sectionName, $settingName, $defaultValue);
    }

    private function createDocumentRoot($dirPath, $message = null)
    {
        $message = $message ?: 'Creating document root for virtual host...';

        Console::line($message, false);

        mkdir($dirPath, 0755, true);

        if (is_dir($dirPath)) {
            Console::line('Successful', true, max(73 - strlen($message), 1));
            return true;
        }

        Console::line('Failed', true, max(77 - strlen($message), 1));
        Console::breakline();
        Console::terminate('Error while creating document root.', 1);
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
            Console::line('Successful', true, max(73 - strlen($message), 1));
            return realpath($configFile);
        }

        Console::line('Failed', true, max(77 - strlen($message), 1));
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
            Console::line('Successful', true, max(73 - strlen($message), 1));
            return realpath($configFile);
        }

        Console::line('Failed', true, max(77 - strlen($message), 1));
        return null;
    }

    private function createCertKeyFile($hostName)
    {
        $message = 'Generating the cert and private key files...';
        Console::line($message, false);

        $this->powerExec('"' . $this->paths['vhostCertGenScript'] . '" "' . $hostName . '"', '-w -i -n', $arrOutput, $exitCode);

        if ($exitCode == 0 && is_file($this->paths['vhostCertDir'] .DS. $hostName . '.cert') && is_file($this->paths['vhostCertKeyDir'] .DS. $hostName . '.key')) {
            Console::line('Successful', true, max(73 - strlen($message), 1));
            return true;
        }

        Console::line('Failed', true, max(77 - strlen($message), 1));
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
                    Console::line('Failed', true, max(77 - strlen($message), 1));
                    return false;
                }
            }
        }

        Console::line('Successful', true, max(73 - strlen($message), 1));
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
                Console::line('Failed', true, max(73 - strlen($message), 1));
                return false;
            }
        }

        Console::line('Successful', true, max(73 - strlen($message), 1));
        return true;
    }

    private function removeFile($filePath, $message = null)
    {
        if (! is_null($message)) {
            Console::line($message, false);
            $removed = @unlink($filePath);

            if ($removed) {
                Console::line('Successful', true, max(73 - strlen($message), 1));
            } else {
                Console::line('Failed', true, max(77 - strlen($message), 1));
            }

            return $removed;
        }

        return @unlink($filePath);
    }
}
