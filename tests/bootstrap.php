<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Fresh SQLite schema for the functional tests (each test then runs in a rolled-back transaction).
passthru(sprintf('php "%s/bin/console" doctrine:schema:drop --force --full-database --env=test -q', dirname(__DIR__)));
passthru(sprintf('php "%s/bin/console" doctrine:schema:create --env=test -q', dirname(__DIR__)));
