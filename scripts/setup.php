<?php
declare(strict_types=1);

// Команда создаёт и настраивает локальную БД только из терминала; запуск через HTTP запрещён.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_DIR', dirname(__DIR__));
require BASE_DIR . '/app/database.php';

try {
    $adminUser = getenv('PGUSER') ?: getenv('USER');
    $adminPassword = getenv('PGPASSWORD') ?: '';
    $host = getenv('PGHOST') ?: '127.0.0.1';
    $port = getenv('PGPORT') ?: '5432';
    if (!preg_match('/^[a-zA-Z0-9.\/\-]+$/', $host) || !ctype_digit($port)) {
        throw new RuntimeException('Invalid PostgreSQL connection settings.');
    }
    $dsnPrefix = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=';
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
    $admin = new PDO($dsnPrefix . 'postgres', $adminUser, $adminPassword, $options);

    $configPath = BASE_DIR . '/.private/database.php';
    $roleExists = $admin->query("SELECT 1 FROM pg_roles WHERE rolname = 'font_gallery_app'")->fetchColumn();
    if ($roleExists && !is_file($configPath)) {
        throw new RuntimeException('The application role already exists; restore its private configuration before setup.');
    }
    $config = is_file($configPath) ? require $configPath : [
        'dsn' => $dsnPrefix . 'font_gallery',
        'user' => 'font_gallery_app',
        'password' => bin2hex(random_bytes(24)),
    ];
    if (!$roleExists) {
        $admin->exec('CREATE ROLE font_gallery_app LOGIN PASSWORD ' . $admin->quote($config['password']));
    }
    if (!$admin->query("SELECT 1 FROM pg_database WHERE datname = 'font_gallery'")->fetchColumn()) {
        $admin->exec('CREATE DATABASE font_gallery');
    }
    if (!is_dir(dirname($configPath))) {
        mkdir(dirname($configPath), 0700);
    }
    if (!is_file($configPath)) {
        file_put_contents($configPath, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
        chmod($configPath, 0600);
    }

    $connection = new PDO($dsnPrefix . 'font_gallery', $adminUser, $adminPassword, $options);
    $connection->beginTransaction();
    $connection->exec(file_get_contents(BASE_DIR . '/database/schema.sql'));
    $connection->exec('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
    $connection->exec('GRANT CONNECT ON DATABASE font_gallery TO font_gallery_app');
    $connection->exec('GRANT USAGE ON SCHEMA public TO font_gallery_app');
    $connection->exec('GRANT SELECT ON fonts, tags, font_tags, font_faces, font_files TO font_gallery_app');
    $connection->exec('GRANT UPDATE (downloads, updated_at) ON fonts TO font_gallery_app');
    $connection->commit();
    echo "Database font_gallery is ready. Private configuration created; credentials are not printed.\n";
    echo "Next: php scripts/import-fonts.php\n";
} catch (Throwable $error) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'Setup failed: ' . $error->getMessage() . "\n");
    exit(1);
}

