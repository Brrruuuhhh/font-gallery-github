<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/admin/bootstrap.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$results = [];

function expect(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $GLOBALS['results'][] = 'PASS: ' . $label;
}

function expectApi(int $status, callable $action, string $label): void
{
    try {
        $action();
    } catch (ApiException $error) {
        expect($error->status === $status, $label . ' (' . $error->status . ')');
        return;
    }
    throw new RuntimeException('FAIL: ' . $label . ' did not reject the request');
}

function validPayload(): array
{
    return [
        'name' => 'Lora', 'author' => 'Author', 'family' => 'serif', 'license' => 'OFL',
        'licenseText' => 'License', 'sourceUrl' => 'https://example.com/font',
        'previewText' => 'Пример', 'dateAdded' => '2026-10-10', 'supportsCyrillic' => true,
        'tags' => [' текст ', 'текст', '', 'код'],
        'faces' => [['id' => 1, 'weight' => 400, 'style' => 'normal', 'files' => [['id' => 10]]]],
    ];
}

try {
    expect(adminDecodeJson('{"name":"Шрифт"}')['name'] === 'Шрифт', 'JSON object preserves UTF-8');
    foreach (['[]', 'null', '{broken', 'true', '"text"'] as $json) {
        expectApi(400, fn () => adminDecodeJson($json), 'Invalid JSON/object rejected');
    }
    foreach (['0', '-1', '1e2', '1.0', '001', [], '9223372036854775808'] as $value) {
        expectApi(400, fn () => adminPositiveInt($value, 'page'), 'Invalid integer rejected');
    }
    expect(adminPositiveInt('5', 'page') === 5, 'Positive integer accepted');
    $font = adminValidateFont(validPayload());
    expect($font['tags'] === ['текст', 'код'], 'Tags are normalized and deduplicated');
    foreach ([['downloads', 10], ['id', 1], ['slug', 'custom']] as [$key, $value]) {
        expectApi(400, fn () => adminValidateFont(validPayload() + [$key => $value]), 'Read-only/unknown field rejected: ' . $key);
    }
    foreach (['dateAdded' => '2026-02-30', 'sourceUrl' => 'javascript:alert(1)',
        'supportsCyrillic' => 'true', 'author' => [], 'name' => '', 'family' => 'other'] as $key => $value) {
        $bad = validPayload();
        $bad[$key] = $value;
        expectApi(422, fn () => adminValidateFont($bad), 'Invalid field rejected: ' . $key);
    }
    $bad = validPayload();
    $bad['faces'][] = $bad['faces'][0];
    expectApi(422, fn () => adminValidateFont($bad), 'Duplicate face rejected');
    $bad = validPayload();
    $bad['faces'][0]['files'][] = ['id' => 10];
    expectApi(422, fn () => adminValidateFont($bad), 'Duplicate file reference rejected');
    $bad = validPayload();
    $bad['faces'][0]['files'] = [['upload' => 'file_0']];
    expectApi(422, fn () => adminValidateFont($bad), 'Missing upload rejected');
    expectApi(422, fn () => adminValidateFont(validPayload(), ['file_9' => []]), 'Unreferenced upload rejected');
    $faces = [1 => ['weight' => 400, 'style' => 'normal']];
    $files = [10 => ['format' => 'woff']];
    adminCheckOwnership($font, $faces, $files);
    expect($font['faces'][0]['id'] === 1, 'Existing face retains ID');
    $foreign = adminValidateFont(validPayload());
    expectApi(422, function () use (&$foreign, $files) { adminCheckOwnership($foreign, [], $files); }, 'Foreign face rejected');
    $foreign = adminValidateFont(validPayload());
    expectApi(422, function () use (&$foreign, $faces) { adminCheckOwnership($foreign, $faces, []); }, 'Foreign file rejected');
    $duplicate = validPayload();
    $duplicate['faces'][0]['files'][] = ['id' => 11];
    $duplicate = adminValidateFont($duplicate);
    expectApi(422, function () use (&$duplicate, $faces) {
        adminCheckOwnership($duplicate, $faces, [10 => ['format' => 'woff'], 11 => ['format' => 'woff']]);
    }, 'Duplicate retained format rejected');
    foreach (glob(BASE_DIR . '/database/fixtures/*/*') as $path) {
        $format = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array($format, ADMIN_FORMATS, true)) {
            expect(adminFontSignature(file_get_contents($path), $format)
                && in_array((new finfo(FILEINFO_MIME_TYPE))->file($path), ADMIN_MIMES, true),
                'Real font structure and MIME accepted: ' . basename($path));
        }
    }
    foreach (ADMIN_FORMATS as $format) {
        expect(!adminFontSignature('<script>not a font</script>', $format), 'Renamed text rejected: ' . $format);
    }
    $woff = file_get_contents(BASE_DIR . '/database/fixtures/lora/lora.woff');
    expect(!adminFontSignature(substr($woff, 0, -1), 'woff'), 'Truncated WOFF rejected');
    expect(!adminFontSignature($woff, 'ttf'), 'Wrong extension rejected');
    $_SERVER = ['REQUEST_METHOD' => 'OPTIONS', 'HTTP_ORIGIN' => 'http://127.0.0.1:5173',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, content-type'];
    adminCors(['GET', 'POST']);
    expect(true, 'Preflight accepts Authorization on GET');
    $_SERVER['HTTP_ORIGIN'] = 'https://untrusted.example';
    expectApi(403, fn () => adminCors(['GET']), 'Unlisted origin rejected');
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => '127.0.0.1:8000', 'HTTP_ORIGIN' => 'http://127.0.0.1:8000'];
    adminCors(['POST']);
    expect(adminIsSameOrigin($_SERVER['HTTP_ORIGIN']), 'Unified local origin accepts POST');
    $_SERVER['HTTP_HOST'] = 'localhost:8000';
    $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8000';
    adminCors(['POST']);
    expect(adminIsSameOrigin($_SERVER['HTTP_ORIGIN']), 'Localhost alias accepts same-origin POST');
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'gallery.example:443', 'HTTPS' => 'on'];
    expect(adminIsSameOrigin('https://gallery.example'), 'HTTPS origin handles the default port');
    foreach (['http://gallery.example', 'https://gallery.example:444', 'https://gallery.example.evil',
        'https://gallery.example/path', 'https://user@gallery.example', 'null'] as $origin) {
        $_SERVER['HTTP_ORIGIN'] = $origin;
        expectApi(403, fn () => adminCors(['POST']), 'Different origin rejected: ' . $origin);
    }
    $_SERVER = ['REQUEST_METHOD' => 'GET'];
    expectApi(401, fn () => adminAuthenticate(), 'Missing token rejected before database access');
    $_SERVER = ['REQUEST_URI' => '/api/admin/fonts', 'REQUEST_METHOD' => 'GET'];
    expectApi(401, fn () => adminDispatch(), 'Administrative list requires login');
    $_SERVER['REQUEST_METHOD'] = 'PATCH';
    expectApi(405, fn () => adminDispatch(), 'Unsupported HTTP method rejected');
    $_SERVER = ['REQUEST_URI' => '/api/admin/fonts/1/extra', 'REQUEST_METHOD' => 'GET'];
    expectApi(404, fn () => adminDispatch(), 'Extra route segments rejected');
    ob_start();
    adminJsonResponse(null, 204);
    expect(ob_get_clean() === '', '204 response has no body');

    if (is_file(BASE_DIR . '/vendor/autoload.php')) {
        adminJwtLibrary();
        $config = ['jwt_secret' => str_repeat('test-only-key-', 4), 'issuer' => 'test-gallery',
            'audience' => 'test-admin', 'token_ttl' => 7200];
        $account = ['id' => 1, 'login' => 'admin', 'role' => 'admin'];
        $issued = adminIssueToken($account, $config);
        expect(adminVerifyToken($issued['token'], $config)['sub'] === '1', 'Issued JWT verifies');
        $parts = explode('.', $issued['token']);
        $parts[1] = rtrim(strtr(base64_encode('{"sub":"2"}'), '+/', '-_'), '=');
        expectApi(401, fn () => adminVerifyToken(implode('.', $parts), $config), 'Tampered JWT rejected');
        $claims = ['iss' => 'test-gallery', 'aud' => 'test-admin', 'sub' => '1',
            'role' => 'admin', 'iat' => time(), 'exp' => time() + 600];
        foreach (['exp' => time() - 1, 'aud' => 'another-app', 'iat' => time() + 3600, 'sub' => 1] as $key => $value) {
            $bad = $claims;
            $bad[$key] = $value;
            $token = Firebase\JWT\JWT::encode($bad, $config['jwt_secret'], 'HS256');
            expectApi(401, fn () => adminVerifyToken($token, $config), 'Invalid JWT claim rejected: ' . $key);
        }
        $bad = $claims;
        unset($bad['exp']);
        $token = Firebase\JWT\JWT::encode($bad, $config['jwt_secret'], 'HS256');
        expectApi(401, fn () => adminVerifyToken($token, $config), 'Missing expiry rejected');
        $bad = $claims;
        $bad['role'] = 'viewer';
        $token = Firebase\JWT\JWT::encode($bad, $config['jwt_secret'], 'HS256');
        expectApi(403, fn () => adminVerifyToken($token, $config), 'Non-admin role rejected');
    } else {
        $results[] = 'SKIP: JWT tests require composer install; no replacement implementation is used.';
    }
    echo implode("\n", $results) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, implode("\n", $results) . "\n" . $error->getMessage() . "\n");
    exit(1);
}
