<?php

require_once __DIR__.'/support/Manager.php';
require_once __DIR__.'/support/Setting.php';
require_once __DIR__.'/support/Installer.php';

set_time_limit(0);

if (! (array_key_exists('XVHM_APP_DIR', $_ENV)) || ! array_key_exists('XVHM_APP_STARTED', $_ENV) || $_ENV['XVHM_APP_STARTED'] != "true") {
    echo PHP_EOL;
    echo 'This script does not accept running as a standalone application.' , PHP_EOL;
    echo 'Please run application from command "xvhosts"';
    echo PHP_EOL;
    exit;
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
    if ($_SERVER['argv'][1] == 'getSetting') {
        $settings = new Setting;

        echo $settings->get($_SERVER['argv'][2], $_SERVER['argv'][3], $_SERVER['argv'][4]);
        exit;
    }

    if ($_SERVER['argv'][1] == 'install') {
        $installer = new Installer;

        if ($_SERVER['argv'][2] == 'start') {
            echo $banner;
            $installer->startInstall();
            exit;
        }

        $installer->continueInstall();
        exit;
    }

    echo $banner;

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

        default:
            break;
    }

    exit;
}

exit;
