<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/common.php';

use Dotenv\Dotenv;

// Load the mock .env file for tests
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
