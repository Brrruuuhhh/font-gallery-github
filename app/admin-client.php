<?php
declare(strict_types=1);

/** Выдаём собранную административную панель Vue через общую точку входа на Apache и локальном сервере PHP. */
function serveAdminClient(string $requestPath): void
{
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD');
        adminClientError(405, 'Метод запроса не поддерживается.');
        return;
    }
    if ($requestPath === '/admin') {
        header('Location: /admin/', true, 308);
        return;
    }

    $relative = substr(rawurldecode($requestPath), strlen('/admin/'));
    foreach (explode('/', $relative) as $segment) {
        if (str_starts_with($segment, '.') || str_contains($segment, "\0") || str_contains($segment, '\\')) {
            adminClientError(403, 'Доступ запрещён.');
            return;
        }
    }

    $root = dirname(__DIR__) . '/admin-ui/dist';
    $isAsset = str_starts_with($relative, 'assets/') || $relative === 'favicon.svg';
    if ($isAsset) {
        $types = ['js' => 'text/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
            'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf'];
        $type = $types[strtolower(pathinfo($relative, PATHINFO_EXTENSION))] ?? null;
        $base = realpath($root);
        $file = realpath($root . '/' . $relative);
        if (!$type || !$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
            adminClientError(404, 'Файл не найден.');
            return;
        }
        header('Cache-Control: public, max-age=3600');
    } else {
        // Адреса страниц обрабатывает Vue Router; вместо отсутствующих файлов нельзя возвращать эту HTML-страницу.
        $file = $root . '/index.html';
        $type = 'text/html; charset=UTF-8';
        if (!is_file($file)) {
            error_log('Font Gallery: admin client is not built. Run php scripts/start.php or npm run build in admin-ui.');
            adminClientError(503, 'Сервис временно недоступен.');
            return;
        }
    }

    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($file));
    if ($method !== 'HEAD') {
        readfile($file);
    }
}

function adminClientError(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $message;
    }
}
