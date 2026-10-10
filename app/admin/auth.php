<?php
declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function adminJwtLibrary(): void
{
    if (!class_exists(JWT::class)) {
        $autoload = BASE_DIR . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new ApiException(503, 'SERVICE_UNAVAILABLE', 'Сервис входа временно недоступен.');
        }
        require_once $autoload;
    }
}

function adminJwtSettings(?array $config = null): array
{
    $config ??= adminConfig();
    if (!is_string($config['jwt_secret'] ?? null) || strlen($config['jwt_secret']) < 32
        || !is_string($config['issuer'] ?? null) || !is_string($config['audience'] ?? null)
        || !is_int($config['token_ttl'] ?? null) || $config['token_ttl'] < 60 || $config['token_ttl'] > 7200) {
        throw new RuntimeException('JWT settings are missing or invalid.');
    }
    adminJwtLibrary();
    return $config;
}

function adminIssueToken(array $account, ?array $config = null): array
{
    $config = adminJwtSettings($config);
    $now = time();
    $expires = $now + $config['token_ttl'];
    $token = JWT::encode([
        'iss' => $config['issuer'], 'aud' => $config['audience'],
        'sub' => (string) $account['id'], 'role' => $account['role'],
        'iat' => $now, 'exp' => $expires,
    ], $config['jwt_secret'], 'HS256');
    return [
        'token' => $token, 'expiresAt' => $expires,
        'user' => ['id' => (int) $account['id'], 'login' => $account['login'], 'role' => $account['role']],
    ];
}

function adminVerifyToken(string $token, ?array $config = null): array
{
    $config = adminJwtSettings($config);
    try {
        $claims = (array) JWT::decode($token, new Key($config['jwt_secret'], 'HS256'));
    } catch (UnexpectedValueException | DomainException | InvalidArgumentException) {
        throw new ApiException(401, 'INVALID_TOKEN', 'Войдите в административную панель повторно.');
    }
    if (($claims['iss'] ?? null) !== $config['issuer'] || ($claims['aud'] ?? null) !== $config['audience']
        || !is_string($claims['sub'] ?? null) || !preg_match('/^[1-9][0-9]{0,14}$/D', $claims['sub'])
        || !is_int($claims['iat'] ?? null) || !is_int($claims['exp'] ?? null)
        || $claims['iat'] > time() || $claims['exp'] <= time()
        || $claims['exp'] <= $claims['iat'] || $claims['exp'] - $claims['iat'] > $config['token_ttl']) {
        throw new ApiException(401, 'INVALID_TOKEN', 'Войдите в административную панель повторно.');
    }
    if (($claims['role'] ?? null) !== 'admin') {
        throw new ApiException(403, 'ACCESS_DENIED', 'Недостаточно прав для выполнения операции.');
    }
    return $claims;
}

function adminAuthenticate(): array
{
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($authorization === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $authorization = $value;
            }
        }
    }
    if (strlen($authorization) > 4096 || !preg_match('/^Bearer ([a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+)$/Di', $authorization, $match)) {
        throw new ApiException(401, 'AUTHENTICATION_REQUIRED', 'Войдите в административную панель.');
    }
    $claims = adminVerifyToken($match[1]);
    $statement = adminDatabase()->prepare('SELECT id, login, role FROM admin_accounts WHERE id = :id AND active');
    $statement->execute(['id' => $claims['sub']]);
    $account = $statement->fetch();
    if (!$account) {
        throw new ApiException(401, 'INVALID_TOKEN', 'Войдите в административную панель повторно.');
    }
    if ($account['role'] !== 'admin') {
        throw new ApiException(403, 'ACCESS_DENIED', 'Недостаточно прав для выполнения операции.');
    }
    return $account;
}

function adminLogin(array $payload): array
{
    $login = $payload['login'] ?? null;
    $password = $payload['password'] ?? null;
    if (!is_string($login) || !preg_match('/^[a-zA-Z0-9_.-]{3,64}$/D', $login)
        || !is_string($password) || strlen($password) < 1 || strlen($password) > 72
        || array_diff(array_keys($payload), ['login', 'password'])) {
        throw new ApiException(422, 'VALIDATION_ERROR', 'Проверьте логин и пароль.', [
            'login' => 'Логин: от 3 до 64 латинских букв, цифр, точек, дефисов или подчёркиваний.',
            'password' => 'Укажите пароль длиной не более 72 байт.',
        ]);
    }
    // Проверяем наличие библиотеки и настройки перед обработкой данных для входа.
    adminJwtSettings();
    $statement = adminDatabase()->prepare('SELECT id, login, password_hash, role, active FROM admin_accounts WHERE login = :login');
    $statement->execute(['login' => $login]);
    $account = $statement->fetch();
    // Даже для отсутствующей учётной записи проверяем контрольное значение пароля, чтобы время ответа не выдавало её наличие.
    $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $verified = password_verify($password, $account['password_hash'] ?? $dummy);
    if (!$account || !$verified || !$account['active']) {
        throw new ApiException(401, 'INVALID_CREDENTIALS', 'Неверный логин или пароль.');
    }
    return adminIssueToken($account);
}
