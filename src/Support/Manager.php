<?php

namespace VhostsManager\Support;

class Manager extends Application
{
    protected $vhosts      = [];
    private   $xamppConfig = null;

    public function __construct()
    {
        parent::__construct();

        $this->xamppConfig = new Setting($this->paths['xamppDir'] . '\xampp-control.ini');

        $this->prepareDirectories();
        $this->loadAllHosts();
    }

    public function showHostInfo($hostName = null)
    {
        $hostName = $this->tryGetHostName($hostName);

        if (! $this->isExistHost($hostName)) {
            Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
        }

        Console::line('The following is information about the virtual host that you requested:');
        Console::breakline();

        $this->presentHostInfo($this->vhosts[$hostName]);

        Console::breakline();
        Console::hrline();

        $showMore = Console::confirm('Do you want to show information of another virtual host?', false);

        if ($showMore) {
            Console::breakline();
            $this->showHostInfo();
        }

        Console::breakline();
        Console::terminate('All jobs are completed.');
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
            Console::line($count . '. [' . $hostName .']');
            Console::breakline();
            $this->presentHostInfo($hostInfo, false, 4);

            $recordPerPage = $this->setting->get('ListViewMode', 'RecordPerPage', 3);

            if ($count < $totalFound && ($count % max(intval($recordPerPage), 2)) == 0) {
                $remain = $totalFound - $count;

                Console::breakline();
                Console::hrline();
                Console::line('There are still ' .$remain. ' more ' . (($remain > 1) ? 'records' : 'record') . '.');

                $continue = Console::confirm('Do you want to continue view more?');

                if (! $continue) {
                    Console::terminate();
                }
            }
        }

        Console::breakline();
        Console::hrline();
        Console::terminate('Your request is completed.');
    }

    public function newHost($hostName = null, $exitIfFailed = true)
    {
        $hostName = $this->tryGetHostName($hostName, $exitIfFailed);

        if (!is_null($hostName)) {
            if ($this->isExistHost($hostName)) {
                Console::terminate('The host name you provided currently exists.');
            }

            $docRootSuggestion = $this->setting->get('Suggestions', 'DocumentRoot', $this->paths['xamppDir'] . '\htdocs\{{host_name}}');
            $documentRoot      = Console::ask('Enter the path to document root for this host', str_replace('{{host_name}}', $hostName, $docRootSuggestion));
            $documentRoot      = $this->normalizeDocumentRoot($documentRoot);

            Console::breakline();
            $emailSuggestion = $this->setting->get('Suggestions', 'AdminEmail', 'webmaster@example.com');
            $adminEmail      = Console::ask('Enter admin email for this host', $emailSuggestion);

            Console::breakline();
            $addSSL   = Console::confirm('Do you want to add SSL certificate for this host?');
            $hostPort = $this->xamppConfig->get('ServicePorts', 'Apache', '80');

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
            $this->addToWinHosts([$serverName, $serverAlias]);

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
            Console::line('Information about the virtual host that has just been created is:');
            Console::breakline();
            $this->presentHostInfo($this->vhosts[$serverName], true, 4);

            // Ask to create more
            Console::breakline();
            Console::hrline();
            $createMore = Console::confirm('Do you want to create another virtual host?', false);

            if ($createMore) {
                Console::breakline();
                $this->newHost(null, false);
            }
        }

        // Ask restart Apache
        parent::restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    public function removeHost($hostName = null, $exitIfFailed = true)
    {
        $hostName = $this->tryGetHostName($hostName, $exitIfFailed);

        if (!is_null($hostName)) {
            if (! $this->isExistHost($hostName)) {
                Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
            }

            $confirmRemove = Console::confirm('Are you sure you want to remove virtual host "' . $hostName . '"?', false);
            Console::breakline();

            if (! $confirmRemove) {
                Console::terminate('Cancel the action.');
            }

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
            $this->removeOutOfWinHosts([$hostInfo['serverName'], $hostInfo['serverAlias']]);

            unset($this->vhosts[$hostName]);

            // Ask to remove another
            Console::breakline();
            Console::hrline();
            $removeMore = Console::confirm('Do you want to remove another virtual host?', false);

            if ($removeMore) {
                Console::breakline();
                $this->removeHost(null, false);
            }
        }

        // Ask restart Apache
        parent::restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    public function addSSLtoHost($hostName = null, $processStandalone = true, $exitIfFailed = true)
    {
        $hostName = $this->tryGetHostName($hostName, $exitIfFailed);

        if (!is_null($hostName)) {
            if (! $this->isExistHost($hostName)) {
                Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
            }

            $hostInfo = $this->vhosts[$hostName];

            if ($this->isSSLHost($hostInfo)) {
                Console::terminate('Sorry! This host has been added an SSL certificate.');
            }

            $sslPort = $this->xamppConfig->get('ServicePorts', 'ApacheSSL', '443');

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
                Console::line('Information about the virtual host that has just been updated is:');
                Console::breakline();
                $this->presentHostInfo($this->vhosts[$hostName], true, 4);

                // Ask to add SSL for another
                Console::breakline();
                Console::hrline();

                $addMore = Console::confirm('Do you want to add SSL for another virtual host?', false);

                if ($addMore) {
                    Console::breakline();
                    $this->addSSLtoHost(null, true, false);
                }
            }
        }

        if ($processStandalone) {
            // Ask restart Apache
            parent::restartApache();

            Console::breakline();
            Console::terminate('All jobs are completed.');
        }
    }

    public function removeSSLOfHost($hostName = null, $exitIfFailed = true)
    {
        $hostName = $this->tryGetHostName($hostName, $exitIfFailed);

        if (!is_null($hostName)) {
            if (! $this->isExistHost($hostName)) {
                Console::terminate('Sorry! Do not found any virtual host with name "' . $hostName . '".');
            }

            $hostInfo = $this->vhosts[$hostName];

            if (! $this->isSSLHost($hostInfo)) {
                Console::terminate('Sorry! This host has not added an SSL certificate yet.');
            }

            $confirmRemove = Console::confirm('Are you sure you want to remove SSL for virtual host "' . $hostName . '"?', false);

            Console::breakline();

            if (! $confirmRemove) {
                Console::terminate('Cancel the action.');
            }

            // Start removing
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
            Console::line('Information about the virtual host that has just been updated is:');
            Console::breakline();
            $this->presentHostInfo($this->vhosts[$hostName], true, 4);

            // Ask to remove SSL for another
            Console::breakline();
            Console::hrline();

            $removeMore = Console::confirm('Do you want to remove SSL for another virtual host?', false);

            if ($removeMore) {
                Console::breakline();
                $this->removeSSLOfHost(null, false);
            }
        }

        // Ask restart Apache
        parent::restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    public function changeDocRoot($hostName = null, $exitIfFailed = true)
    {
        $hostName = $this->tryGetHostName($hostName, $exitIfFailed);

        if (!is_null($hostName)) {
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
            $replacePattern        = '/' . preg_quote(unixstyle_path($hostInfo['documentRoot']), '/') . '/';
            $vhostConfigContent    = preg_replace($replacePattern, unixstyle_path($newDocRoot), $vhostConfigContent);
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
                $replacePattern      = '/' . preg_quote(unixstyle_path($hostInfo['documentRoot']), '/') . '/';
                $sslConfigContent    = preg_replace($replacePattern, unixstyle_path($newDocRoot), $sslConfigContent);
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
            Console::line('Information about the virtual host that has just been updated is:');
            Console::breakline();
            $this->presentHostInfo($this->vhosts[$hostName], true, 4);

            // Ask to change more
            Console::breakline();
            Console::hrline();

            $changeMore = Console::confirm('Do you want to continue changing for another virtual host?', false);

            if ($changeMore) {
                Console::breakline();
                $this->changeDocRoot(null, false);
            }
        }

        // Ask restart Apache
        parent::restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
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
        $documentRoot = rtrim($documentRoot, '/\\');
        $documentRoot = osstyle_path($documentRoot);
        $firstChar    = substr($documentRoot, 0, 1);
        $secondChar   = substr($documentRoot, 1, 1);

        if ($firstChar == DS || $secondChar == ':') {
            return $documentRoot;
        }

        return absolute_path($this->paths['xamppDir'] .DS. $documentRoot);
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

    private function tryGetHostName($hostName = null, $exitIfFailed = true, $tryRepeat = 3, $message = null)
    {
        $hostName = $this->normalizeHostName($hostName);
        $message  = $message ?: 'Enter virtual host name';
        $repeat   = 0;

        while (! $hostName || ! filter_var('http://' . $hostName, FILTER_VALIDATE_URL)) {
            if ($repeat == ($tryRepeat + 1)) {
                Console::line('You have entered an incorrect format many times.');
                Console::line('Cancel current job.');

                if ($exitIfFailed) {
                    Console::terminate(null, 1);
                }

                Console::breakline();

                return null;
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
        $lines  = file_lines($vhostConfigFile);
        $config = [];

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
                                $config[$tagName] = [];
                            }

                            if (! array_key_exists($value, $config[$tagName])) {
                                $config[$tagName][$value] = [];
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

        $output = [
            'vhostConfigFile' => $vhostConfigFile,
            'hostPort'        => $hostPort,
            'serverName'      => $vhostConfigs['ServerName'],
            'serverAlias'     => $vhostConfigs['ServerAlias'] ?: null,
            'serverAdmin'     => $vhostConfigs['ServerAdmin'] ?: null,
            'documentRoot'    => osstyle_path($vhostConfigs['DocumentRoot']),
            'sslConfigFile'   => $sslConfigFile,
            'sslPort'         => $sslPort,
            'certFile'        => $certFile,
            'certKeyFile'     => $certKeyFile
        ];

        return $output;
    }

    private function loadAllHosts()
    {
        // open vhosts dir
        if (! $handle = opendir($this->paths['vhostConfigDir'])) {
            return;
        }

        $hosts = [];

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

        $search   = [];
        $search[] = '{{host_name}}';
        $search[] = '{{host_port}}';
        $search[] = '{{admin_email}}';
        $search[] = '{{document_root}}';

        $replace   = [];
        $replace[] = $hostName;
        $replace[] = $hostPort;
        $replace[] = $adminEmail;
        $replace[] = unixstyle_path($documentRoot);

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

        $search    = [];
        $search[]  = '{{host_name}}';
        $search[]  = '{{ssl_port}}';
        $search[]  = '{{admin_email}}';
        $search[]  = '{{document_root}}';
        $search[]  = '{{cert_file}}';
        $search[]  = '{{cert_key_file}}';

        $replace   = [];
        $replace[] = $hostName;
        $replace[] = $sslPort;
        $replace[] = $adminEmail;
        $replace[] = unixstyle_path($documentRoot);
        $replace[] = relative_path($this->paths['apacheDir'], $this->paths['vhostCertDir'], '/') . '/' . $hostName . '.cert';
        $replace[] = relative_path($this->paths['apacheDir'], $this->paths['vhostCertKeyDir'], '/') . '/' . $hostName . '.key';

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

    private function existInWinHosts($hostName)
    {
        return line_preg_match("/^\s*127\.0\.0\.1\s+" . preg_quote($hostName) . "\s*\#?.*/", $this->paths['winHostsFile']);
    }

    private function addToWinHosts($hostNames)
    {
        $message = 'Adding host name to Windows hosts file...';

        Console::line($message, false);

        if (! is_array($hostNames)) {
            $tempArr   = [];
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

    private function removeOutOfWinHosts($hostNames)
    {
        $message = 'Removing host name in Windows hosts file...';

        Console::line($message, false);

        if (! is_array($hostNames)) {
            $tempArr   = [];
            $tempArr[] = $hostNames;
            $hostNames = $tempArr;
        }

        if (count($hostNames) > 0) {
            $lines     = file_lines($this->paths['winHostsFile']);
            $keepLines = [];

            foreach ($lines as $line) {
                $line      = trim($line);
                $matchLine = false;

                foreach ($hostNames as $hostName) {
                    preg_match("/^\s*127\.0\.0\.1\s+" . preg_quote($hostName) . "\s*\#?.*/", $line, $matches);

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

    private function presentHostInfo($hostInfo, $withDetails = true, $indentSpaces = 4)
    {
        $indentString   = str_repeat(' ', $indentSpaces);
        $addedSSL       = $this->isSSLHost($hostInfo);
        $addedToWinHost = $this->existInWinHosts($hostInfo['serverName']);

        $url = $secureUrl = null;

        if ($addedToWinHost) {
            $url = 'http://' . $hostInfo['serverName'] . (($hostInfo['hostPort'] != 80) ? ':' . $hostInfo['hostPort'] : null);

            if ($addedSSL) {
                $secureUrl = 'https://' . $hostInfo['serverName'] . (($hostInfo['sslPort'] != 443) ? ':' . $hostInfo['sslPort'] : null);
            }
        }

        $alias = $this->existInWinHosts($hostInfo['serverAlias']) ? $hostInfo['serverAlias'] : null;

        if ($withDetails) {
            Console::line($indentString . '[+] Configuration information');
            Console::line($indentString . '-----------------------------');
            Console::breakline();
            Console::line($indentString . '- Host name             : ' . $hostInfo['serverName']);
            Console::line($indentString . '- Host alias            : ' . $alias);
            Console::line($indentString . '- Host port             : ' . $hostInfo['hostPort']);
            Console::line($indentString . '- Admin email           : ' . $hostInfo['serverAdmin']);
            Console::line($indentString . '- Document root         : ' . $hostInfo['documentRoot']);
            Console::line($indentString . '- Host config file      : ' . $hostInfo['vhostConfigFile']);
            Console::line($indentString . '- Added to WinHosts     : ' . ($addedToWinHost ? 'Yes' : 'No'));
            Console::line($indentString . '- Added SSL certificate : ' . ($addedSSL ? 'Yes' : 'No'));
            Console::line($indentString . '- SSL port              : ' . $hostInfo['sslPort']);
            Console::line($indentString . '- SSL config file       : ' . $hostInfo['sslConfigFile']);
            Console::line($indentString . '- SSL certificate file  : ' . $hostInfo['certFile']);
            Console::line($indentString . '- SSL private key file  : ' . $hostInfo['certKeyFile']);
            Console::breakline();
            Console::line($indentString . '[+] Urls information');
            Console::line($indentString . '--------------------');
            Console::breakline();
            Console::line($indentString . '- Host url              : ' . $url);
            Console::line($indentString . '- Secure url            : ' . $secureUrl);
        } else {
            Console::line($indentString . '- Added to WinHosts     : ' . ($addedToWinHost ? 'Yes' : 'No'));
            Console::line($indentString . '- Added SSL certificate : ' . ($addedSSL ? 'Yes' : 'No'));
            Console::line($indentString . '- Host url              : ' . $url);
            Console::line($indentString . '- Secure url            : ' . $secureUrl);
            Console::breakline();
            Console::line($indentString . '(Use the command "xvhost show ' . $hostInfo['serverName'] . '" for more details.)');
        }
    }
}
