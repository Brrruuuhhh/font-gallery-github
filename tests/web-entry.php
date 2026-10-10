<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Проверки общей точки входа PHP и Vue через HTTP без изменения данных. Требуется запущенный локальный сервер. */
function entryRequest(string $path, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    $responseHeaders = [];
    $curl = curl_init('http://127.0.0.1:8000' . $path);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        }];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    if ($response === false) {
        throw new RuntimeException(curl_error($curl));
    }
    return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => $responseHeaders, 'body' => $response];
}

function entryCheck(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . "\n";
}

try {
    $redirect = entryRequest('/admin');
    entryCheck($redirect['status'] === 308 && $redirect['headers']['location'] === '/admin/', 'Admin URL has a canonical trailing slash');
    $index = entryRequest('/admin/');
    entryCheck($index['status'] === 200 && str_contains($index['body'], '<div id="app"></div>'), 'Built Vue client loads through PHP');
    entryCheck($index['headers']['cache-control'] === 'no-store', 'Client HTML is not retained after a new build');
    foreach (['/admin/login', '/admin/fonts', '/admin/fonts/6/edit', '/admin/fonts/6/delete'] as $path) {
        $response = entryRequest($path);
        entryCheck($response['status'] === 200 && $response['body'] === $index['body'], 'Direct client route loads: ' . $path);
    }
    $head = entryRequest('/admin/fonts', 'HEAD');
    entryCheck($head['status'] === 200 && $head['body'] === ''
        && (int) $head['headers']['content-length'] === strlen($index['body']), 'Client HEAD returns size without a body');
    preg_match_all('~(?:src|href)="(/admin/(?:assets/[^" ]+|favicon\.svg))"~', $index['body'], $matches);
    entryCheck(count($matches[1]) >= 3, 'Built assets use the admin prefix');
    foreach ($matches[1] as $path) {
        $asset = entryRequest($path);
        entryCheck($asset['status'] === 200 && !str_starts_with($asset['headers']['content-type'], 'text/html'), 'Asset loads with its MIME: ' . $path);
        entryCheck(entryRequest($path, 'HEAD')['body'] === '', 'Asset HEAD has no body: ' . $path);
    }
    entryCheck(entryRequest('/admin/assets/missing.js')['status'] === 404, 'Missing JavaScript does not return client HTML');
    entryCheck(entryRequest('/admin/login', 'POST')['status'] === 405, 'Client page rejects write methods');
    foreach (['/admin-ui/src/App.vue', '/admin-ui/.env.development', '/admin-ui/dist/index.html',
        '/archive/legacy/php-admin/pages/admin.php', '/admin/assets/%2e%2e/%2e%2e/.private/admin.php'] as $path) {
        entryCheck(entryRequest($path)['status'] === 403, 'Source or traversal is blocked: ' . $path);
    }
    foreach (['/', '/catalog', '/comparison', '/about', '/sitemap'] as $path) {
        $page = entryRequest($path);
        entryCheck($page['status'] === 200 && !preg_match('~href=["\']/admin(?:/|["\'])~', $page['body']), 'Public page has no admin link: ' . $path);
    }
    entryCheck(entryRequest('/api/admin/fonts')['status'] === 401, 'Shared API still requires authorization');
    $login = entryRequest('/api/admin/auth', 'POST', ['Content-Type: application/json', 'Origin: http://127.0.0.1:8000'], '{}');
    entryCheck($login['status'] === 422, 'Same-origin login reaches payload validation');
    $foreign = entryRequest('/api/admin/auth', 'POST', ['Content-Type: application/json', 'Origin: https://untrusted.example'], '{}');
    entryCheck($foreign['status'] === 403, 'Unlisted cross-origin login is rejected');
    $preflight = entryRequest('/api/admin/auth', 'OPTIONS', ['Origin: http://127.0.0.1:5173',
        'Access-Control-Request-Method: POST', 'Access-Control-Request-Headers: content-type']);
    entryCheck($preflight['status'] === 204 && $preflight['body'] === ''
        && $preflight['headers']['access-control-allow-origin'] === 'http://127.0.0.1:5173', 'Vite development CORS remains available');
    echo "All shared-entry checks passed. Database records and download counters were not changed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
