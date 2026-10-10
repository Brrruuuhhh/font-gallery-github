<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_DIR', dirname(__DIR__));
require BASE_DIR . '/app/database.php';

require BASE_DIR . '/app/admin/console.php';

try {
    $public = databaseConfig();
    $path = BASE_DIR . '/.private/admin.php';
    $config = is_file($path) ? require $path : [
        'dsn' => $public['dsn'], 'user' => 'font_gallery_admin', 'password' => bin2hex(random_bytes(24)),
        'jwt_secret' => bin2hex(random_bytes(32)), 'issuer' => 'font-gallery',
        'audience' => 'font-gallery-admin', 'token_ttl' => 7200,
        'origins' => ['http://127.0.0.1:5173'], 'public_origin' => 'http://127.0.0.1:8000',
    ];
    if (($config['user'] ?? null) !== 'font_gallery_admin' || empty($config['password']) || empty($config['jwt_secret'])) {
        throw new RuntimeException('Проверьте закрытую конфигурацию административного API.');
    }
    $connection = new PDO($config['dsn'], getenv('PGUSER') ?: getenv('USER'), getenv('PGPASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $roleExists = $connection->query("SELECT 1 FROM pg_roles WHERE rolname = 'font_gallery_admin'")->fetchColumn();
    if ($roleExists && !is_file($path)) {
        throw new RuntimeException('Роль API уже существует. Сначала восстановите её закрытую конфигурацию.');
    }
    $connection->beginTransaction();
    $connection->exec(file_get_contents(BASE_DIR . '/database/admin.sql'));
    if (!$roleExists) {
        $connection->exec('CREATE ROLE font_gallery_admin LOGIN PASSWORD ' . $connection->quote($config['password']));
    }
    $databaseName = $connection->query('SELECT current_database()')->fetchColumn();
    $connection->exec('GRANT CONNECT ON DATABASE "' . str_replace('"', '""', $databaseName) . '" TO font_gallery_admin');
    $connection->exec('GRANT USAGE ON SCHEMA public TO font_gallery_admin');
    $connection->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON fonts, font_tags, font_faces, font_files TO font_gallery_admin');
    $connection->exec('GRANT SELECT, INSERT ON tags TO font_gallery_admin');
    $connection->exec('GRANT SELECT ON admin_accounts TO font_gallery_admin');
    $connection->exec('GRANT SELECT, UPDATE ON catalog_state TO font_gallery_admin');
    $connection->exec('GRANT SELECT ON catalog_state TO font_gallery_app');
    foreach (['fonts', 'tags', 'font_faces', 'font_files'] as $table) {
        $sequence = $connection->query("SELECT pg_get_serial_sequence('$table', 'id')")->fetchColumn();
        $connection->exec('GRANT USAGE, SELECT ON SEQUENCE ' . $sequence . ' TO font_gallery_admin');
    }
    $exists = $connection->query('SELECT login FROM admin_accounts WHERE id = 1')->fetchColumn();
    if (!$exists || in_array('--reset-password', $argv, true)) {
        $login = $exists ?: readAdminInput('Логин администратора: ');
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,64}$/D', $login)) {
            throw new RuntimeException('Логин: от 3 до 64 латинских букв, цифр, точек, дефисов или подчёркиваний.');
        }
        $password = readAdminInput('Пароль администратора (не отображается): ', true);
        $confirmation = readAdminInput('Повторите пароль: ', true);
        if (strlen($password) < 12 || strlen($password) > 72 || !hash_equals($password, $confirmation)) {
            throw new RuntimeException('Пароли должны совпадать; длина — от 12 до 72 байт.');
        }
        $statement = $connection->prepare('INSERT INTO admin_accounts (id, login, password_hash) VALUES (1, ?, ?) '
            . 'ON CONFLICT (id) DO UPDATE SET password_hash = EXCLUDED.password_hash, active = true');
        $statement->execute([$login, password_hash($password, PASSWORD_BCRYPT)]);
        unset($password, $confirmation);
        if ($exists) {
            $config['jwt_secret'] = bin2hex(random_bytes(32));
        }
    }
    $temporary = $path . '.tmp';
    if (file_put_contents($temporary, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) {
        throw new RuntimeException('Не удалось записать закрытую конфигурацию.');
    }
    chmod($temporary, 0600);
    $connection->commit();
    if (!rename($temporary, $path)) {
        throw new RuntimeException('Не удалось сохранить закрытую конфигурацию.');
    }
    echo "Административная БД и учётная запись готовы. Каталог сохранён, секреты не выведены.\n";
    echo "Повторный запуск сохраняет пароль. Для смены: php scripts/setup-admin.php --reset-password\n";
} catch (Throwable $error) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }
    // Ошибки подключения могут содержать данные для входа; выводим только безопасные сообщения проверки настроек.
    $message = $error instanceof PDOException ? 'Ошибка подключения или настройки PostgreSQL. Проверьте права PGUSER и работу БД.' : $error->getMessage();
    fwrite(STDERR, 'Настройка не завершена: ' . $message . "\n");
    exit(1);
}
