<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($requestPath === '/api' || str_starts_with($requestPath, '/api/')) {
    require __DIR__ . '/api/index.php';
    return;
}

if ($requestPath === '/admin' || str_starts_with($requestPath, '/admin/')) {
    require __DIR__ . '/app/admin-client.php';
    serveAdminClient($requestPath);
    return;
}

require __DIR__ . '/app/bootstrap.php';

try {
    dispatchRequest();
} catch (HttpException $error) {
    sendResponse(renderError($error->status, $error->getMessage()), cache: false);
} catch (Throwable $error) {
    error_log('Font Gallery: ' . $error->getMessage());
    sendResponse(renderError(503), cache: false);
}
