<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/common.php';
require_once __DIR__ . '/../src/fetch_secret.php';
require_once __DIR__ . '/../src/check_and_run.php';

use Dotenv\Dotenv;

// Load the mock .env file for tests
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
