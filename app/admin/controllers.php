<?php
declare(strict_types=1);

function adminDispatch(): void
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $id = null;
    if ($path === '/api/admin/auth') {
        $resource = 'auth';
        $methods = ['POST'];
    } elseif ($path === '/api/admin/fonts') {
        $resource = 'fonts';
        $methods = ['GET', 'POST'];
    } elseif (preg_match('~^/api/admin/fonts/([1-9][0-9]{0,14})$~D', $path, $match)) {
        $resource = 'font';
        $id = (int) $match[1];
        $methods = ['GET', 'PUT', 'DELETE'];
    } else {
        // Разрешённому источнику запроса также возвращаем доступный для чтения JSON с ошибкой неизвестного адреса.
        adminCors(['GET', 'POST', 'PUT', 'DELETE']);
        throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Ресурс не найден.');
    }
    adminCors($methods);
    if ($method === 'OPTIONS') {
        adminJsonResponse(null, 204);
        return;
    }
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', [...$methods, 'OPTIONS']));
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Метод не поддерживается.');
    }
    if ($resource === 'auth') {
        [$payload] = adminRequestData();
        adminJsonResponse(['data' => adminLogin($payload)]);
        return;
    }
    adminAuthenticate();
    if ($method === 'GET') {
        adminJsonResponse($id === null ? adminFontList($_GET) : ['data' => adminGetFont($id)]);
    } elseif ($method === 'DELETE') {
        adminDeleteFont($id);
        adminJsonResponse(null, 204);
    } else {
        [$payload, $uploads] = adminRequestData(true);
        $font = adminSaveFont($id, $payload, $uploads);
        if ($method === 'POST') {
            header('Location: /api/admin/fonts/' . $font['id']);
        }
        adminJsonResponse(['data' => $font], $method === 'POST' ? 201 : 200);
    }
}
