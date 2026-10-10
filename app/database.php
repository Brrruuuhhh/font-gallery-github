<?php
declare(strict_types=1);

/** Настройки подключения не попадают в HTML, общедоступные файлы и обработчик адресов сайта. */
function databaseConfig(): array
{
    $path = BASE_DIR . '/.private/database.php';
    $config = is_file($path) ? require $path : [];

    foreach (['dsn', 'user', 'password'] as $key) {
        $value = getenv('FONT_GALLERY_DB_' . strtoupper($key));
        if ($value !== false) {
            $config[$key] = $value;
        }
    }

    if (empty($config['dsn']) || empty($config['user'])) {
        throw new RuntimeException('Database configuration is missing. Run scripts/setup.php.');
    }

    return $config;
}

function database(): PDO
{
    static $connection = null;
    return $connection ??= connectDatabase(databaseConfig());
}

/** Общие настройки PDO; публичное и административное подключения сохраняют отдельные данные для входа. */
function connectDatabase(array $config): PDO
{
    $connection = new PDO($config['dsn'], $config['user'], $config['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET TIME ZONE 'UTC'");
    return $connection;
}
