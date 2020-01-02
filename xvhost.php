<?php

require_once __DIR__.'/support/Manager.php';
require_once __DIR__.'/support/Installer.php';

set_time_limit(0);

if (! getenv('XVHM_APP_DIR')) {
    echo PHP_EOL;
    echo 'This script does not accept running as a standalone application.' . PHP_EOL;
    echo 'Please run application from command "xvhost"';
    echo PHP_EOL;
    exit(1);
}

$banner = PHP_EOL
    . "###################################################################################" . PHP_EOL
    . "#  Xampp vHosts Manager, virtual hosts management system for Xampp on Windows OS  #" . PHP_EOL
    . "#---------------------------------------------------------------------------------#" . PHP_EOL
    . "#  Author: Jackie Do <anhvudo@gmail.com>                                          #" . PHP_EOL
    . "#---------------------------------------------------------------------------------#" . PHP_EOL
    . "#  License: MIT (c) Jackie Do <anhvudo@gmail.com>                                 #" . PHP_EOL
    . "###################################################################################" . PHP_EOL . PHP_EOL;

if (isset($_SERVER['argv'][1])) {
    echo $banner;

    if ($_SERVER['argv'][1] == 'install') {
        $installer = new Installer;
        $installer->install();
        exit;
    }

    $manager = new Manager;

    switch ($_SERVER['argv'][1]) {
        case 'listHosts':
            $manager->listHosts();
            break;

        case 'showHostInfo':
            $manager->showHostInfo($_SERVER['argv'][2]);
            break;

        case 'removeHost':
            $manager->removeHost($_SERVER['argv'][2]);
            break;

        case 'newHost':
            $manager->newHost($_SERVER['argv'][2]);
            break;

        case 'addSSL':
            $manager->addSSLtoHost($_SERVER['argv'][2]);
            break;

        case 'removeSSL':
            $manager->removeSSLOfHost($_SERVER['argv'][2]);
            break;

        case 'changeDocRoot':
            $manager->changeDocRoot($_SERVER['argv'][2]);
            break;

        case 'registerPath':
            $manager->registerPath();
            break;

        case 'stopApache':
            $manager->stopApache();
            break;

        case 'startApache':
            $manager->startApache();
            break;

        case 'restartApache':
            $manager->restartApache();
            break;

        default:
            break;
    }

    exit;
}

exit;
