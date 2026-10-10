<?php
declare(strict_types=1);

const ADMIN_FAMILIES = ['serif', 'sans-serif', 'monospace', 'display', 'handwriting'];
const ADMIN_LICENSES = ['OFL', 'UFL', 'free', 'commercial', 'SIL'];
const ADMIN_STYLES = ['normal', 'italic', 'oblique'];
const ADMIN_FORMATS = ['ttf', 'otf', 'woff', 'woff2'];
const ADMIN_MIMES = ['font/ttf', 'font/otf', 'font/sfnt', 'font/woff', 'font/woff2', 'application/font-sfnt',
    'application/font-woff', 'application/x-font-ttf', 'application/x-font-opentype',
    'application/vnd.ms-opentype', 'application/octet-stream'];
const ADMIN_MAX_FILE = 20971520;

function adminFontSignature(string $content, string $format): bool
{
    $size = strlen($content);
    $magic = substr($content, 0, 4);
    if ($size < 12 || $size > ADMIN_MAX_FILE) {
        return false;
    }
    $u16 = static fn (int $offset): int => unpack('n', substr($content, $offset, 2))[1];
    $u32 = static fn (int $offset): int => unpack('N', substr($content, $offset, 4))[1];
    if (in_array($format, ['ttf', 'otf'], true)) {
        if (($format === 'ttf' && !in_array($magic, ["\x00\x01\x00\x00", 'true'], true))
            || ($format === 'otf' && $magic !== 'OTTO')) {
            return false;
        }
        $tables = $u16(4);
        $directoryEnd = 12 + $tables * 16;
        if ($tables < 1 || $tables > 4096 || $directoryEnd > $size) {
            return false;
        }
        for ($index = 0; $index < $tables; $index++) {
            $entry = 12 + $index * 16;
            $offset = $u32($entry + 8);
            $length = $u32($entry + 12);
            if ($length > 0 && ($offset < $directoryEnd || $offset > $size || $length > $size - $offset)) {
                return false;
            }
        }
        return true;
    }
    if (!in_array($format, ['woff', 'woff2'], true)) {
        return false;
    }
    $headerSize = $format === 'woff' ? 44 : 48;
    if ($size < $headerSize || $magic !== ($format === 'woff' ? 'wOFF' : 'wOF2')
        || !in_array(substr($content, 4, 4), ["\x00\x01\x00\x00", 'OTTO', 'true'], true)
        || $u32(8) !== $size || $u16(12) < 1 || $u16(12) > 4096 || $u16(14) !== 0
        || $u32(16) < 12 + 16 * $u16(12)) {
        return false;
    }
    if ($format === 'woff2') {
        return $u32(20) > 0 && $u32(20) <= $size - $headerSize;
    }
    $directoryEnd = 44 + $u16(12) * 20;
    if ($directoryEnd > $size) {
        return false;
    }
    for ($index = 0; $index < $u16(12); $index++) {
        $entry = 44 + $index * 20;
        $offset = $u32($entry + 4);
        $compressed = $u32($entry + 8);
        $original = $u32($entry + 12);
        if ($offset < $directoryEnd || $offset > $size || $compressed < 1
            || $compressed > $original || $compressed > $size - $offset) {
            return false;
        }
    }
    return true;
}

function adminUploadedFile(array $file, string $field): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new ApiException(413, 'FILE_TOO_LARGE', 'Файл шрифта превышает допустимый размер 20 МБ.');
    }
    if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new ApiException(422, 'VALIDATION_ERROR', 'Не удалось загрузить файл.', [$field => 'Выберите файл повторно.']);
    }
    $name = is_string($file['name'] ?? null) ? basename(str_replace('\\', '/', $file['name'])) : '';
    $format = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > ADMIN_MAX_FILE) {
        throw new ApiException(413, 'FILE_TOO_LARGE', 'Файл должен быть непустым и не больше 20 МБ.');
    }
    if ($name === '' || mb_strlen($name) > 150 || preg_match('/[\x00-\x1f\x7f]/', $name)
        || !in_array($format, ADMIN_FORMATS, true)) {
        throw new ApiException(422, 'VALIDATION_ERROR', 'Проверьте имя и формат файла.', [$field => 'Допустимы TTF, OTF, WOFF и WOFF2; имя — до 150 символов.']);
    }
    $content = file_get_contents($file['tmp_name']);
    if ($content === false || !adminFontSignature($content, $format)) {
        throw new ApiException(422, 'VALIDATION_ERROR', 'Файл не соответствует формату шрифта.', [$field => 'Выберите корректный файл шрифта.']);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);
    if (!in_array($mime, ADMIN_MIMES, true)) {
        throw new ApiException(422, 'VALIDATION_ERROR', 'Тип файла не соответствует шрифту.', [$field => 'Выберите корректный файл шрифта.']);
    }
    return ['name' => $name, 'format' => $format, 'size' => $size, 'content' => $content,
        'mime' => 'font/' . $format, 'sha256' => hash('sha256', $content)];
}

/** Приводим сведения из JSON и формы с файлами к общему виду до начала транзакции. */
function adminValidateFont(array $payload, array $uploads = []): array
{
    $fields = [];
    $font = [];
    $limits = ['name' => 150, 'author' => 250, 'licenseText' => 100000,
        'sourceUrl' => 2000, 'previewText' => 500, 'dateAdded' => 10];
    $allowed = [...array_keys($limits), 'family', 'license', 'supportsCyrillic', 'tags', 'faces'];
    if (array_diff(array_keys($payload), $allowed)) {
        throw new ApiException(400, 'UNKNOWN_FIELDS', 'Запрос содержит неподдерживаемые поля.');
    }
    foreach ($limits as $key => $limit) {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || trim($value) === ''
            || mb_strlen($value) > $limit || str_contains($value, "\0")) {
            $fields[$key] = 'Заполните поле; допустимая длина — до ' . $limit . ' символов.';
        } else {
            $font[$key] = trim($value);
        }
    }
    foreach (['family' => ADMIN_FAMILIES, 'license' => ADMIN_LICENSES] as $key => $values) {
        if (!in_array($payload[$key] ?? null, $values, true)) {
            $fields[$key] = 'Выберите допустимое значение.';
        } else {
            $font[$key] = $payload[$key];
        }
    }
    $font['supportsCyrillic'] = $payload['supportsCyrillic'] ?? false;
    if (!is_bool($font['supportsCyrillic'])) {
        $fields['supportsCyrillic'] = 'Укажите логическое значение.';
    }
    if (isset($font['dateAdded'])) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $font['dateAdded']);
        if (!$date || $date->format('Y-m-d') !== $font['dateAdded']) {
            $fields['dateAdded'] = 'Укажите корректную дату.';
        }
    }
    if (isset($font['sourceUrl']) && (!filter_var($font['sourceUrl'], FILTER_VALIDATE_URL)
        || !in_array(parse_url($font['sourceUrl'], PHP_URL_SCHEME), ['http', 'https'], true))) {
        $fields['sourceUrl'] = 'Укажите ссылку с http:// или https://.';
    }
    $font['tags'] = [];
    $tags = $payload['tags'] ?? [];
    if (!is_array($tags) || !array_is_list($tags) || count($tags) > 32) {
        $fields['tags'] = 'Передайте список не более чем из 32 тегов.';
    } else {
        foreach ($tags as $tag) {
            if (!is_string($tag) || mb_strlen(trim($tag)) > 100 || str_contains($tag, "\0")) {
                $fields['tags'] = 'Каждый тег должен быть строкой длиной до 100 символов.';
            } elseif (trim($tag) !== '') {
                $font['tags'][] = trim($tag);
            }
        }
        $font['tags'] = array_values(array_unique($font['tags']));
    }
    $faces = $payload['faces'] ?? null;
    $font['faces'] = [];
    $descriptors = $faceIds = $fileIds = $uploadKeys = [];
    if (!is_array($faces) || !array_is_list($faces) || count($faces) < 1 || count($faces) > 27) {
        $fields['faces'] = 'Добавьте от 1 до 27 начертаний.';
        $faces = [];
    }
    foreach ($faces as $index => $face) {
        $field = 'face-' . $index;
        if (!is_array($face) || array_diff(array_keys($face), ['id', 'weight', 'style', 'files'])
            || !is_int($face['weight'] ?? null) || $face['weight'] < 100 || $face['weight'] > 900
            || $face['weight'] % 100 !== 0 || !in_array($face['style'] ?? null, ADMIN_STYLES, true)
            || !is_array($face['files'] ?? null) || !array_is_list($face['files'])
            || count($face['files']) < 1 || count($face['files']) > 4) {
            $fields[$field] = 'Проверьте насыщенность, стиль и файлы начертания.';
            continue;
        }
        $descriptor = $face['weight'] . '-' . $face['style'];
        $id = $face['id'] ?? null;
        if (isset($descriptors[$descriptor]) || ($id !== null && (!is_int($id) || $id < 1 || isset($faceIds[$id])))) {
            $fields[$field] = 'Начертания не должны повторяться.';
            continue;
        }
        $descriptors[$descriptor] = true;
        if ($id !== null) {
            $faceIds[$id] = true;
        }
        $normalized = ['id' => $id, 'weight' => $face['weight'], 'style' => $face['style'], 'files' => []];
        foreach ($face['files'] as $file) {
            if (!is_array($file) || count($file) !== 1) {
                $fields[$field] = 'Передайте идентификатор сохранённого файла или ссылку на загрузку.';
                continue;
            }
            if (isset($file['id']) && is_int($file['id']) && $file['id'] > 0 && !isset($fileIds[$file['id']])) {
                $fileIds[$file['id']] = true;
                $normalized['files'][] = ['id' => $file['id']];
            } elseif (isset($file['upload']) && is_string($file['upload']) && preg_match('/^file_[0-9]{1,3}$/D', $file['upload'])
                && !isset($uploadKeys[$file['upload']]) && isset($uploads[$file['upload']]) && is_array($uploads[$file['upload']])) {
                $uploadKeys[$file['upload']] = true;
                $normalized['files'][] = ['upload' => $file['upload']];
            } else {
                $fields[$field] = 'Не найден файл загрузки или файл указан несколько раз.';
            }
        }
        $font['faces'][] = $normalized;
    }
    if (array_diff(array_keys($uploads), array_keys($uploadKeys))) {
        $fields['faces'] = 'Запрос содержит файлы, не привязанные к начертаниям.';
    }
    if ($fields) {
        throw new ApiException(422, 'VALIDATION_ERROR', 'Проверьте заполнение формы.', $fields);
    }
    foreach ($font['faces'] as $index => &$face) {
        foreach ($face['files'] as &$file) {
            if (isset($file['upload'])) {
                $file = adminUploadedFile($uploads[$file['upload']], 'face-' . $index);
            }
        }
        unset($file);
    }
    unset($face);
    return $font;
}
