<?php
declare(strict_types=1);

// Без обработки PHP выдаются только разрешённые общедоступные файлы. Исходные данные и настройки недоступны извне.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$segments = explode('/', trim($path, '/'));
$protected = ['app', 'database', 'scripts', 'tests', 'data', 'includes', 'pages', 'fonts', 'vendor', 'archive', 'admin-ui'];
$forbidden = in_array($segments[0] ?? '', $protected, true);
foreach ($segments as $segment) {
    $forbidden = $forbidden || str_starts_with($segment, '.') || str_contains($segment, "\0") || str_contains($segment, '\\');
}
if ($forbidden) {
    require __DIR__ . '/app/bootstrap.php';
    sendResponse(renderError(403), cache: false);
    return true;
}

$asset = preg_match('~^/static/[a-zA-Z0-9_./-]+\.(css|js|svg)$~D', $path) || $path === '/favicon.svg';
if ($asset && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    $file = realpath(__DIR__ . $path);
    if ($file !== false && str_starts_with($file, __DIR__ . '/') && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';
