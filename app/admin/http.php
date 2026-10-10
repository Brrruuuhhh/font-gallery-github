<?php
declare(strict_types=1);

const ADMIN_MAX_BODY = 134217728;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}

function adminJsonResponse(?array $body, int $status = 200): void
{
    http_response_code($status);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if ($status !== 204) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

function adminErrorResponse(ApiException $error): void
{
    if ($error->status === 401) {
        header('WWW-Authenticate: Bearer realm="font-gallery-admin"');
    }
    $details = ['code' => $error->errorCode, 'message' => $error->getMessage()];
    if ($error->fields) {
        $details['fields'] = $error->fields;
    }
    adminJsonResponse(['error' => $details], $error->status);
}

/** Запросы с того же адреса сайта работают локально и после размещения на домене без отдельного разрешения CORS. */
function adminIsSameOrigin(string $origin): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!preg_match('/^(?:[a-zA-Z0-9.-]+|\[[a-fA-F0-9:]+\])(?::[0-9]{1,5})?$/D', $host)) {
        return false;
    }
    $scheme = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
    $request = parse_url($scheme . '://' . $host);
    $source = parse_url($origin);
    if (!$request || !$source || isset($source['user']) || isset($source['pass'])
        || isset($source['path']) || isset($source['query']) || isset($source['fragment'])) {
        return false;
    }
    $defaultPort = $scheme === 'https' ? 443 : 80;
    return ($source['scheme'] ?? '') === $scheme
        && strtolower($source['host'] ?? '') === strtolower($request['host'])
        && ($source['port'] ?? $defaultPort) === ($request['port'] ?? $defaultPort);
}

function adminCors(array $methods): void
{
    header('Vary: Origin');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && !adminIsSameOrigin($origin)) {
        if (!in_array($origin, adminConfig()['origins'], true)) {
            throw new ApiException(403, 'ORIGIN_NOT_ALLOWED', 'Доступ с этого адреса не разрешён.');
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', [...$methods, 'OPTIONS']));
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'OPTIONS') {
        return;
    }
    $requestedMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '';
    if ($requestedMethod !== '' && !in_array($requestedMethod, $methods, true)) {
        header('Allow: ' . implode(', ', [...$methods, 'OPTIONS']));
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Метод не поддерживается.');
    }
    $headers = array_filter(array_map('trim', explode(',', strtolower($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? ''))));
    if (array_diff($headers, ['authorization', 'content-type'])) {
        throw new ApiException(403, 'HEADERS_NOT_ALLOWED', 'Заголовки запроса не разрешены.');
    }
}

function adminDecodeJson(string $json): array
{
    try {
        $value = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        if (!$value instanceof stdClass) {
            throw new JsonException('An object is required.');
        }
        return json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ApiException(400, 'INVALID_JSON', 'Передайте корректный JSON-объект.');
    }
}

/** Читаем тело запроса один раз: PHP не заполняет $_FILES автоматически при отправке формы с файлами методом PUT. */
function adminRequestData(bool $allowFiles = false): array
{
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > ADMIN_MAX_BODY) {
        throw new ApiException(413, 'REQUEST_TOO_LARGE', 'Общий размер запроса превышает 128 МБ.');
    }
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type === 'application/json') {
        $body = file_get_contents('php://input', false, null, 0, ADMIN_MAX_BODY + 1);
        if ($body === false || strlen($body) > ADMIN_MAX_BODY) {
            throw new ApiException(413, 'REQUEST_TOO_LARGE', 'Общий размер запроса превышает 128 МБ.');
        }
        return [adminDecodeJson($body), []];
    }
    if ($allowFiles && $type === 'multipart/form-data') {
        try {
            [$fields, $files] = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
                ? [$_POST, $_FILES]
                : request_parse_body(['post_max_size' => '128M', 'upload_max_filesize' => '20M', 'max_file_uploads' => 108]);
        } catch (RequestParseBodyException) {
            throw new ApiException(400, 'INVALID_MULTIPART', 'Не удалось прочитать сведения и файлы запроса.');
        }
        if (!isset($fields['data']) || !is_string($fields['data']) || count($fields) !== 1) {
            throw new ApiException(400, 'INVALID_MULTIPART', 'Передайте сведения о шрифте в поле data.');
        }
        return [adminDecodeJson($fields['data']), $files];
    }
    throw new ApiException(415, 'UNSUPPORTED_MEDIA_TYPE', 'Формат запроса не поддерживается.');
}

function adminPositiveInt(mixed $value, string $field, int $maximum = PHP_INT_MAX): int
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
        || strlen((string) $value) > strlen((string) $maximum)
        || (strlen((string) $value) === strlen((string) $maximum) && strcmp((string) $value, (string) $maximum) > 0)) {
        throw new ApiException(400, 'INVALID_PARAMETER', 'Некорректный параметр: ' . $field . '.');
    }
    return (int) $value;
}
