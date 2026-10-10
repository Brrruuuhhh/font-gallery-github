<?php
declare(strict_types=1);

function adminConfig(): array
{
    $path = BASE_DIR . '/.private/admin.php';
    $config = is_file($path) ? require $path : [];
    $config += [
        'origins' => ['http://127.0.0.1:5173'],
        'public_origin' => 'http://127.0.0.1:8000',
        'issuer' => 'font-gallery',
        'audience' => 'font-gallery-admin',
        'token_ttl' => 7200,
    ];
    foreach (['dsn' => 'DB_DSN', 'user' => 'DB_USER', 'password' => 'DB_PASSWORD',
        'jwt_secret' => 'JWT_SECRET', 'public_origin' => 'PUBLIC_ORIGIN'] as $key => $environment) {
        $value = getenv('FONT_GALLERY_ADMIN_' . $environment);
        if ($value !== false) {
            $config[$key] = $value;
        }
    }
    $origins = getenv('FONT_GALLERY_ADMIN_ORIGINS');
    if ($origins !== false) {
        $config['origins'] = array_values(array_filter(array_map('trim', explode(',', $origins))));
    }
    foreach (array_merge($config['origins'], [$config['public_origin']]) as $origin) {
        $parts = is_string($origin) ? parse_url($origin) : false;
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('Invalid administrative origin configuration.');
        }
    }
    return $config;
}

function adminDatabase(): PDO
{
    static $connection = null;
    if ($connection === null) {
        $config = adminConfig();
        if (empty($config['dsn']) || empty($config['user'])) {
            throw new RuntimeException('Administrative database configuration is missing.');
        }
        $connection = connectDatabase($config);
    }
    return $connection;
}
