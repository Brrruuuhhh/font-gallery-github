<?php
declare(strict_types=1);

// Запускать вручную с работающим локальным сервером PHP после установки зависимостей и настройки проекта.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/admin/bootstrap.php';
require BASE_DIR . '/app/admin/console.php';

function apiRequest(string $method, string $path, ?string $body = null, array $headers = []): array
{
    $context = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $headers),
        'content' => $body ?? '', 'ignore_errors' => true, 'timeout' => 60,
        'follow_location' => 0,
    ]]);
    $result = @file_get_contents('http://127.0.0.1:8000' . $path, false, $context);
    $responseHeaders = http_get_last_response_headers() ?? [];
    if ($result === false || !preg_match('/^HTTP\/\S+ (\d{3})/', $responseHeaders[0] ?? '', $match)) {
        throw new RuntimeException('Нет ответа локального PHP на 127.0.0.1:8000.');
    }
    $parsed = [];
    foreach (array_slice($responseHeaders, 1) as $header) {
        if (str_contains($header, ':')) {
            [$key, $value] = explode(':', $header, 2);
            $parsed[strtolower($key)] = trim($value);
        }
    }
    return ['status' => (int) $match[1], 'body' => $result, 'headers' => $parsed,
        'json' => json_decode($result, true)];
}

function apiExpect(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . "\n";
}

function apiJson(string $method, string $path, array $payload, array $headers): array
{
    return apiRequest($method, $path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        [...$headers, 'Content-Type: application/json']);
}

function apiMultipart(string $method, string $path, array $payload, array $files, array $headers): array
{
    $boundary = 'GalleryTest' . bin2hex(random_bytes(12));
    $body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"data\"\r\n\r\n"
        . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\r\n";
    foreach ($files as $key => $file) {
        $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$key\"; filename=\""
            . basename($file) . "\"\r\nContent-Type: application/octet-stream\r\n\r\n"
            . file_get_contents($file) . "\r\n";
    }
    $body .= '--' . $boundary . "--\r\n";
    return apiRequest($method, $path, $body, [...$headers, 'Content-Type: multipart/form-data; boundary=' . $boundary]);
}

function apiPayload(array $font): array
{
    $payload = array_intersect_key($font, array_flip([
        'name', 'author', 'family', 'license', 'licenseText', 'sourceUrl', 'previewText',
        'dateAdded', 'supportsCyrillic', 'tags',
    ]));
    $payload['faces'] = array_map(static fn (array $face): array => [
        'id' => $face['id'], 'weight' => $face['weight'], 'style' => $face['style'],
        'files' => array_map(static fn (array $file): array => ['id' => $file['id']], $face['files']),
    ], $font['faces']);
    return $payload;
}

$created = [];
$headers = [];
$failed = false;
try {
    if (!is_file(BASE_DIR . '/vendor/autoload.php') || !is_file(BASE_DIR . '/.private/admin.php')) {
        throw new RuntimeException('Сначала выполните установку PHP-JWT и php scripts/setup-admin.php по инструкции.');
    }
    $root = '/api/admin/fonts';
    $origin = 'http://127.0.0.1:5173';
    $response = apiRequest('GET', $root);
    apiExpect($response['status'] === 401 && isset($response['json']['error']['code']), 'Без токена: 401 и JSON');
    $response = apiRequest('OPTIONS', $root, null, ['Origin: ' . $origin,
        'Access-Control-Request-Method: GET', 'Access-Control-Request-Headers: authorization, content-type']);
    apiExpect($response['status'] === 204 && $response['body'] === ''
        && ($response['headers']['access-control-allow-origin'] ?? '') === $origin, 'CORS: предварительный GET с Authorization');
    $response = apiRequest('GET', $root, null, ['Origin: https://untrusted.example']);
    apiExpect($response['status'] === 403 && !isset($response['headers']['access-control-allow-origin']), 'Чужой origin отклонён');
    $login = readAdminInput('Логин администратора: ');
    $password = readAdminInput('Пароль (не отображается): ', true);
    $response = apiJson('POST', '/api/admin/auth', ['login' => $login, 'password' => bin2hex(random_bytes(24))], []);
    apiExpect($response['status'] === 401, 'Неверный пароль: 401');
    $response = apiJson('POST', '/api/admin/auth', ['login' => $login, 'password' => $password], ['Origin: ' . $origin]);
    unset($password);
    if ($response['status'] !== 200 || !is_string($response['json']['data']['token'] ?? null)) {
        throw new RuntimeException('Вход не выполнен. Проверьте учётные данные и готовность API; ответ ' . $response['status'] . '.');
    }
    $token = $response['json']['data']['token'];
    $headers = ['Authorization: Bearer ' . $token, 'Origin: ' . $origin];
    apiExpect(true, 'Вход выполнен; пароль и токен не выводятся');
    $parts = explode('.', $token);
    $parts[1] = rtrim(strtr(base64_encode('{"sub":"2"}'), '+/', '-_'), '=');
    apiExpect(apiRequest('GET', $root, null, ['Authorization: Bearer ' . implode('.', $parts)])['status'] === 401, 'Подделанный токен: 401');
    $config = adminJwtSettings();
    $expired = Firebase\JWT\JWT::encode(['iss' => $config['issuer'], 'aud' => $config['audience'],
        'sub' => '1', 'role' => 'admin', 'iat' => time() - 120, 'exp' => time() - 60], $config['jwt_secret'], 'HS256');
    apiExpect(apiRequest('GET', $root, null, ['Authorization: Bearer ' . $expired])['status'] === 401, 'Просроченный токен: 401');
    $viewer = Firebase\JWT\JWT::encode(['iss' => $config['issuer'], 'aud' => $config['audience'],
        'sub' => '1', 'role' => 'viewer', 'iat' => time(), 'exp' => time() + 60], $config['jwt_secret'], 'HS256');
    apiExpect(apiRequest('GET', $root, null, ['Authorization: Bearer ' . $viewer])['status'] === 403, 'Нет административного права: 403');
    unset($expired, $viewer, $config);
    $list = apiRequest('GET', $root . '?perPage=1', null, $headers);
    apiExpect($list['status'] === 200 && isset($list['json']['pagination']['total']), 'Список и серверная пагинация');
    $initialTotal = $list['json']['pagination']['catalogTotal'];
    $response = apiRequest('POST', $root, '{broken', [...$headers, 'Content-Type: application/json']);
    apiExpect($response['status'] === 400 && isset($response['json']['error']['message']), 'Повреждённый JSON: 400');
    apiExpect(apiRequest('PATCH', $root, null, $headers)['status'] === 405, 'Неподдерживаемый метод: 405');
    apiExpect(apiRequest('GET', '/api/unknown', null, $headers)['status'] === 404, 'Неизвестный ресурс: 404');

    $name = 'API check ' . bin2hex(random_bytes(6));
    $payload = ['name' => $name, 'author' => 'Cyreal', 'family' => 'serif', 'license' => 'OFL',
        'licenseText' => file_get_contents(BASE_DIR . '/database/fixtures/lora/LICENSE.txt'),
        'sourceUrl' => 'https://fonts.google.com/specimen/Lora', 'previewText' => 'Проверка шрифта',
        'dateAdded' => date('Y-m-d'), 'supportsCyrillic' => true, 'tags' => [],
        'faces' => [['weight' => 400, 'style' => 'normal', 'files' => [['upload' => 'file_0']]],
            ['weight' => 700, 'style' => 'normal', 'files' => [['upload' => 'file_1']]]]];
    $files = ['file_0' => BASE_DIR . '/database/fixtures/lora/lora.woff',
        'file_1' => BASE_DIR . '/database/fixtures/lora/lora.ttf'];
    $response = apiMultipart('POST', $root, $payload, $files, $headers);
    if (isset($response['json']['data']['id'])) {
        $created[] = $response['json']['data']['id'];
    }
    apiExpect($response['status'] === 201 && count($response['json']['data']['faces'] ?? []) === 2, 'POST: шрифт, два начертания и настоящие файлы');
    $font = $response['json']['data'];
    $id = $font['id'];
    apiExpect(($response['headers']['location'] ?? '') === $root . '/' . $id, 'POST возвращает Location');
    $detail = apiRequest('GET', $root . '/' . $id, null, $headers)['json']['data'];
    apiExpect($detail['licenseText'] === trim($payload['licenseText']) && $detail['downloads'] === 0, 'GET возвращает сохранённые сведения без увеличения счётчика');
    $fileId = $font['faces'][0]['files'][0]['id'];
    $preview = apiRequest('GET', '/font-files/' . $fileId);
    apiExpect($preview['status'] === 200 && hash('sha256', $preview['body']) === hash_file('sha256', $files['file_0']), 'Из PostgreSQL возвращаются исходные байты WOFF');
    $list = apiRequest('GET', $root . '?q=' . rawurlencode($name) . '&family=serif&license=OFL&perPage=1', null, $headers);
    apiExpect($list['json']['pagination']['total'] === 1 && $list['json']['data'][0]['id'] === $id, 'Поиск и фильтры выполняются сервером');
    $update = apiPayload($font);
    $update['name'] .= ' edited';
    // Меняем характеристики начертаний местами, сохраняя идентификаторы: отложенная проверка уникальности должна это разрешать.
    [$update['faces'][0]['weight'], $update['faces'][1]['weight']] = [$update['faces'][1]['weight'], $update['faces'][0]['weight']];
    $response = apiJson('PUT', $root . '/' . $id, $update, $headers);
    apiExpect($response['status'] === 200 && $response['json']['data']['name'] === $update['name'], 'PUT JSON: изменение сведений и обмен начертаний');
    $updated = $response['json']['data'];
    $response = apiJson('PUT', $root . '/' . $id, $update, $headers);
    apiExpect($response['status'] === 200 && $response['json']['data'] === $updated, 'Повторный PUT сохраняет те же идентификаторы и данные');
    $update = apiPayload($updated);
    $faceIndex = array_find_key($update['faces'], static fn (array $face): bool => $face['files'][0]['id'] === $fileId);
    $update['faces'][$faceIndex]['files'] = [['upload' => 'file_0']];
    $replacement = ['file_0' => BASE_DIR . '/database/fixtures/roboto/roboto.woff'];
    $response = apiMultipart('PUT', $root . '/' . $id, $update, $replacement, $headers);
    apiExpect($response['status'] === 200, 'PUT multipart читает новый файл');
    $updated = $response['json']['data'];
    $response = apiMultipart('PUT', $root . '/' . $id, $update, $replacement, $headers);
    apiExpect($response['status'] === 200 && $response['json']['data'] === $updated, 'Повторная загрузка не создаёт копий файлов');
    $preview = apiRequest('GET', '/font-files/' . $fileId);
    apiExpect($preview['status'] === 200 && hash('sha256', $preview['body']) === hash_file('sha256', $replacement['file_0']), 'Замена обновляет байты, сохраняя идентификатор файла');
    $bad = apiPayload($updated);
    $bad['name'] = '';
    apiExpect(apiJson('PUT', $root . '/' . $id, $bad, $headers)['status'] === 422, 'Неверные поля: 422');
    $response = apiMultipart('POST', $root, $payload, $files, $headers);
    if (isset($response['json']['data']['id'])) {
        $created[] = $response['json']['data']['id'];
    }
    apiExpect($response['status'] === 201, 'Отдельная запись для проверки принадлежности файла');
    $bad = apiPayload($updated);
    $bad['name'] = 'Must not persist';
    $bad['faces'][0]['files'] = [['id' => $response['json']['data']['faces'][0]['files'][0]['id']]];
    apiExpect(apiJson('PUT', $root . '/' . $id, $bad, $headers)['status'] === 422, 'Файл другого шрифта отклонён');
    apiExpect(apiRequest('GET', $root . '/' . $id, null, $headers)['json']['data'] === $updated, 'Ошибки не оставили частичных изменений');
    foreach ($created as $createdId) {
        $response = apiRequest('DELETE', $root . '/' . $createdId, null, $headers);
        apiExpect($response['status'] === 204 && $response['body'] === '', 'DELETE ' . $createdId . ': 204 без тела');
        apiExpect(apiRequest('DELETE', $root . '/' . $createdId, null, $headers)['status'] === 404, 'Повторный DELETE: 404');
    }
    apiExpect(apiRequest('GET', $root . '/' . $id, null, $headers)['status'] === 404
        && apiRequest('GET', '/font-files/' . $fileId)['status'] === 404, 'Удалены запись и двоичный файл');
    $remaining = apiRequest('GET', $root . '?perPage=1', null, $headers)['json']['pagination']['catalogTotal'];
    apiExpect($remaining === $initialTotal, 'Количество исходных шрифтов сохранено');
    echo "Проверка завершена. Исходные записи не редактировались.\n";
} catch (Throwable $error) {
    $failed = true;
    // Не выводим тела HTTP-ответов, ошибки подключения, пароли и данные подтверждения входа (JWT).
    $message = $error instanceof RuntimeException && !$error instanceof PDOException
        ? $error->getMessage() : 'Внутренняя ошибка сценария; проверьте установку и готовность сервера.';
    fwrite(STDERR, $message . "\n");
} finally {
    foreach ($created as $id) {
        try {
            $response = apiRequest('DELETE', '/api/admin/fonts/' . $id, null, $headers);
            if (!in_array($response['status'], [204, 404], true)) {
                throw new RuntimeException();
            }
        } catch (Throwable) {
            $failed = true;
            fwrite(STDERR, 'Проверьте удаление только проверочного шрифта ID ' . $id . ".\n");
        }
    }
}
exit($failed ? 1 : 0);
