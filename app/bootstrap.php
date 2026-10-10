<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('BASE_DIR', dirname(__DIR__));
define('INCLUDES_DIR', BASE_DIR . '/includes');
define('PAGES_DIR', BASE_DIR . '/pages');
define('SITE_NAME', 'Галерея Шрифтов');
define('SITE_DESCRIPTION', 'Каталог шрифтов с предпросмотром, сравнением и готовым CSS-кодом для веб-использования.');

require BASE_DIR . '/app/database.php';
require BASE_DIR . '/app/font-repository.php';
require BASE_DIR . '/app/http.php';
require BASE_DIR . '/app/font-service.php';
require BASE_DIR . '/app/font-download.php';
require BASE_DIR . '/app/view.php';
require BASE_DIR . '/app/controllers.php';
