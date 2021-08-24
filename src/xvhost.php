<?php

require __DIR__ . '/../vendor/autoload.php';

use VhostsManager\Support\Application;
use VhostsManager\Support\Console;
use VhostsManager\Support\Manager;
use VhostsManager\Support\Installer;

set_time_limit(0);

if (! getenv('XVHM_APP_DIR')) {
    Console::breakline();
    Console::line('This script does not accept running as a standalone application.');
    Console::line('Please run application from command "xvhost"');
    Console::terminate(null, 1, true);
}

if (isset($_SERVER['argv'][1])) {
    Console::line(XVHM_BANNER);

    $job = $_SERVER['argv'][1];

    // Install app
    if ($job == 'install') {
        $installer = new Installer;

        $installer->install();
        Console::terminate(null, 0, true);
    }

    // Register app path to Windows Path Environment
    if ($job == 'registerPath') {
        $application = new Application(false, false);
        $result      = $application->registerAppPath();

        Console::breakline();

        if ($result) {
            Console::terminate('All jobs are completed.');
        }

        Console::terminate(null, 1);
    }

    if ($job == 'grantPermsWinHosts') {
        $application = new Application(false, false);
        $result      = $application->grantPermsWinHosts();

        Console::breakline();

        if ($result) {
            Console::terminate('All jobs are completed.');
        }

        Console::terminate(null, 1);
    }

    // Stop Apache
    if ($job == 'stopApache') {
        $application = new Application;

        $application->stopApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    // Start Apache
    if ($job == 'startApache') {
        $application = new Application;

        $application->startApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    // Restart Apache
    if ($job == 'restartApache') {
        $application = new Application;

        $application->restartApache();

        Console::breakline();
        Console::terminate('All jobs are completed.');
    }

    // Vhosts management jobs
    $manager = new Manager;

    switch ($job) {
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

        default:
            break;
    }

    Console::terminate(null, 0, true);
}

Console::terminate(null, 0, true);
