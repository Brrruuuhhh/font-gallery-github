<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__, 2));
}

require_once BASE_DIR . '/app/database.php';
require_once BASE_DIR . '/app/font-repository.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/font-validation.php';
require_once __DIR__ . '/font-repository.php';
require_once __DIR__ . '/font-service.php';
require_once __DIR__ . '/controllers.php';
