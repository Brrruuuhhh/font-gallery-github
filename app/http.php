<?php
declare(strict_types=1);

class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function getRequestPath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!is_string($path) || str_contains($path, "\0")) {
        throw new HttpException(400, 'Некорректный адрес запроса.');
    }
    return '/' . trim($path, '/');
}

function siteOrigin(): string
{
    $configured = getenv('FONT_GALLERY_SITE_URL');
    if ($configured !== false && filter_var($configured, FILTER_VALIDATE_URL)) {
        return rtrim($configured, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/D', $host)) {
        $host = 'localhost';
    }
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    return $scheme . '://' . $host;
}

function getCanonicalUrl(): string
{
    return siteOrigin() . getRequestPath();
}

function getLastModifiedTimestamp(): int
{
    $latest = 0;
    foreach (['index.php', 'app/*.php', 'pages/*.php', 'includes/*.php', 'static/*.js', 'static/*.css'] as $pattern) {
        foreach (glob(BASE_DIR . '/' . $pattern) ?: [] as $file) {
            $latest = max($latest, filemtime($file));
        }
    }
    if (http_response_code() === 200 || http_response_code() === false) {
        $latest = max($latest, catalogModifiedTimestamp());
    }
    return min(time(), $latest ?: time());
}

/** Возвращает false при ответе 304 «Не изменено», чтобы не читать содержимое файла повторно. */
function prepareResponse(string $type, ?int $modified, bool $cache, string $etag): bool
{
    header('Content-Type: ' . $type);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    $status = http_response_code() ?: 200;
    if ($cache && $status === 200) {
        $modified ??= getLastModifiedTimestamp();
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        header('ETag: ' . $etag);
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        $notModified = false;
        if ($ifNoneMatch !== null) {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                if (trim($candidate) === '*' || preg_replace('/^W\//', '', trim($candidate)) === $etag) {
                    $notModified = true;
                }
            }
        } elseif (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            $notModified = $since !== false && $since >= $modified;
        }
        if ($notModified && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            http_response_code(304);
            return false;
        }
    } else {
        header('Cache-Control: no-store');
    }
    return true;
}

/** Заголовок If-None-Match имеет приоритет. Ответы с ошибками и скачиванием не заменяются на 304. */
function sendResponse(string $body, string $type = 'text/html; charset=UTF-8', ?int $modified = null, bool $cache = true, ?string $etag = null): void
{
    if (!prepareResponse($type, $modified, $cache, $etag ?? '"' . hash('sha256', $body) . '"')) {
        return;
    }
    header('Content-Length: ' . strlen($body));
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $body;
    }
}

function requestString(string $name, string $default = '', int $maxLength = 200): string
{
    $value = $_GET[$name] ?? $default;
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $maxLength || preg_match('/[\x00-\x1F]/', $value)) {
        throw new HttpException(400, 'Некорректный параметр: ' . $name);
    }
    return trim($value);
}

function requestPositiveInt(string $name, int $default = 1): int
{
    $value = requestString($name, (string) $default, 10);
    if (!preg_match('/^[1-9][0-9]*$/D', $value) || (int) $value > 1000000) {
        throw new HttpException(400, 'Некорректный параметр: ' . $name);
    }
    return (int) $value;
}

function requestValues(string $name, ?array $allowed = null): array
{
    $values = $_GET[$name] ?? [];
    if (!is_array($values) || count($values) > 50) {
        throw new HttpException(400, 'Некорректный фильтр: ' . $name);
    }
    foreach ($values as $value) {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 100 || preg_match('/[\x00-\x1F]/', $value)
            || ($allowed !== null && !in_array($value, $allowed, true))) {
            throw new HttpException(400, 'Некорректный фильтр: ' . $name);
        }
    }
    return array_values(array_unique($values));
}

function catalogCriteria(): array
{
    $sort = requestString('sort', 'dateAdded-desc', 30);
    if (!isset(FONT_SORTS[$sort])) {
        throw new HttpException(400, 'Неизвестный способ сортировки.');
    }
    return [
        'q' => requestString('q'), 'sort' => $sort,
        'family' => requestValues('family', ['serif', 'sans-serif', 'monospace', 'display', 'handwriting']),
        'license' => requestValues('license', ['free', 'commercial', 'OFL', 'UFL', 'SIL']),
        'tags' => requestValues('tags'),
    ];
}

function requestFontIds(): array
{
    $value = requestString('ids', '', 600);
    if ($value === '') {
        return [];
    }
    $ids = explode(',', $value);
    if (count($ids) > 50) {
        throw new HttpException(400, 'Слишком много шрифтов.');
    }
    foreach ($ids as $id) {
        if (!preg_match('/^[1-9][0-9]{0,8}$/D', $id)) {
            throw new HttpException(400, 'Некорректный идентификатор шрифта.');
        }
    }
    return array_values(array_unique(array_map('intval', $ids)));
}
