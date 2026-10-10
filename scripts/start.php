<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Находим исполняемый файл Node.js без командной оболочки, npm.cmd и путей, привязанных к конкретной ОС. */
function findStartNode(): string
{
    $name = PHP_OS_FAMILY === 'Windows' ? 'node.exe' : 'node';
    foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
        $candidate = rtrim(trim($directory, '"'), '/\\') . DIRECTORY_SEPARATOR . $name;
        if (is_file($candidate) && is_executable($candidate)) {
            return realpath($candidate) ?: $candidate;
        }
    }
    throw new RuntimeException('Node.js не найден в PATH. Установите Node.js или активируйте его версию и повторите запуск.');
}

/** Используем текущий терминал и передаём аргументы напрямую, в том числе пути с пробелами. */
function runStartCommand(array $command, string $directory): int
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $directory, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Не удалось запустить дочерний процесс.');
    }
    register_shutdown_function(static function () use ($process): void {
        if (is_resource($process)) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
    });
    return proc_close($process);
}

try {
    $arguments = array_slice($argv, 1);
    if ($arguments === ['--help']) {
        echo "Запуск: php scripts/start.php\n";
        echo "Проверка окружения без сборки и запуска: php scripts/start.php --check\n";
        echo "Нужны PHP 8.4+, Node.js, установленные зависимости и подготовленный PostgreSQL.\n";
        exit(0);
    }
    if ($arguments !== [] && $arguments !== ['--check']) {
        throw new RuntimeException('Неизвестные параметры. Справка: php scripts/start.php --help');
    }
    if (version_compare(PHP_VERSION, '8.4.0', '<')) {
        throw new RuntimeException('Нужен PHP 8.4 или новее.');
    }
    foreach (['pdo_pgsql', 'mbstring', 'fileinfo', 'iconv', 'zip'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('Включите расширение PHP: ' . $extension);
        }
    }
    if (!function_exists('proc_open')) {
        throw new RuntimeException('Для запуска необходима разрешённая функция PHP proc_open.');
    }

    $root = dirname(__DIR__);
    $node = findStartNode();
    $vite = $root . '/admin-ui/node_modules/vite/bin/vite.js';
    if (!is_file($vite)) {
        throw new RuntimeException('Не установлены зависимости админки. Выполните npm ci в папке admin-ui.');
    }
    if (!is_file($root . '/vendor/autoload.php')) {
        throw new RuntimeException('Не установлены PHP-зависимости. Выполните composer install из корня проекта.');
    }

    require $root . '/app/admin/bootstrap.php';
    adminJwtSettings();
    database()->query('SELECT 1 FROM fonts LIMIT 1');
    database()->query('SELECT 1 FROM catalog_state LIMIT 1');
    if (!adminDatabase()->query('SELECT 1 FROM admin_accounts WHERE active AND role = \'admin\' LIMIT 1')->fetchColumn()) {
        throw new RuntimeException('Не найдена активная учётная запись администратора. Выполните php scripts/setup-admin.php.');
    }
    echo "PHP, Node.js, зависимости и подключение к PostgreSQL проверены.\n";
    if ($arguments === ['--check']) {
        exit(0);
    }

    $listener = @stream_socket_client('tcp://127.0.0.1:8000', $errorCode, $errorMessage, 0.3);
    if (is_resource($listener)) {
        fclose($listener);
        throw new RuntimeException('Порт 8000 уже занят. Остановите прежний локальный сервер и повторите запуск.');
    }
    // В Unix-подобных системах и Windows нажатие Ctrl+C в текущем терминале останавливает запущенный сервер.
    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static fn () => exit(130));
        pcntl_signal(SIGTERM, static fn () => exit(143));
    }

    echo "Сборка административной панели…\n";
    // Это установленный в проекте файл сборки Vite, используемый командой npm run build; Node.js запускает его на любой ОС.
    if (runStartCommand([$node, $vite, 'build'], $root . '/admin-ui') !== 0) {
        throw new RuntimeException('Сборка админки не завершена. Исправьте показанную ошибку и повторите запуск.');
    }
    echo "\nСайт:   http://127.0.0.1:8000/\n";
    echo "Админка: http://127.0.0.1:8000/admin/\n";
    echo "Остановка сервера: Ctrl+C в этом терминале.\n\n";
    exit(runStartCommand([
        PHP_BINARY, '-d', 'upload_max_filesize=20M', '-d', 'post_max_size=128M',
        '-d', 'max_file_uploads=108', '-d', 'memory_limit=512M',
        '-S', '127.0.0.1:8000', '-t', $root, $root . '/router.php',
    ], $root));
} catch (Throwable $error) {
    $message = $error instanceof PDOException
        ? 'PostgreSQL недоступен или БД не подготовлена. Проверьте службу БД, закрытые настройки и выполненные скрипты установки.'
        : $error->getMessage();
    fwrite(STDERR, 'Запуск не выполнен: ' . $message . "\n");
    exit(1);
}
