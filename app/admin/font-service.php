<?php
declare(strict_types=1);

function adminFontData(array $font): array
{
    $faces = [];
    $origin = rtrim(adminConfig()['public_origin'], '/');
    foreach ($font['files'] as $file) {
        $faceId = (int) $file['face_id'];
        $faces[$faceId] ??= ['id' => $faceId, 'weight' => $file['weight'], 'style' => $file['style'], 'files' => []];
        $faces[$faceId]['files'][] = [
            'id' => $file['id'], 'name' => $file['file_name'], 'format' => $file['format'],
            'size' => $file['size_bytes'], 'previewUrl' => $origin . '/font-files/' . $file['id'] . '?v=' . $file['sha256'],
        ];
    }
    $result = [
        'id' => (int) $font['id'], 'name' => $font['name'], 'author' => $font['author'],
        'family' => $font['family'], 'license' => $font['license'], 'tags' => $font['tags'],
        'dateAdded' => $font['dateAdded'], 'previewText' => $font['previewText'],
        'downloads' => $font['downloads'], 'supportsCyrillic' => $font['supportsCyrillic'],
        'faces' => array_values($faces),
    ];
    if (isset($font['licenseText'], $font['sourceUrl'])) {
        $result += ['licenseText' => $font['licenseText'], 'sourceUrl' => $font['sourceUrl']];
    }
    return $result;
}

function adminFontList(array $query): array
{
    if (array_diff(array_keys($query), ['q', 'family', 'license', 'sort', 'page', 'perPage'])) {
        throw new ApiException(400, 'INVALID_PARAMETER', 'Запрос содержит неподдерживаемые параметры.');
    }
    foreach (['q', 'family', 'license', 'sort'] as $key) {
        if (isset($query[$key]) && (!is_string($query[$key]) || !mb_check_encoding($query[$key], 'UTF-8')
            || mb_strlen($query[$key]) > 200 || preg_match('/[\x00-\x1f\x7f]/', $query[$key]))) {
            throw new ApiException(400, 'INVALID_PARAMETER', 'Некорректный параметр: ' . $key . '.');
        }
    }
    $criteria = ['q' => trim($query['q'] ?? ''), 'minQueryLength' => 1, 'sort' => $query['sort'] ?? 'name-asc'];
    foreach (['family' => ADMIN_FAMILIES, 'license' => ADMIN_LICENSES] as $key => $allowed) {
        $value = $query[$key] ?? '';
        if ($value !== '' && !in_array($value, $allowed, true)) {
            throw new ApiException(400, 'INVALID_PARAMETER', 'Некорректный параметр: ' . $key . '.');
        }
        $criteria[$key] = $value === '' ? [] : [$value];
    }
    if (!isset(FONT_SORTS[$criteria['sort']])) {
        throw new ApiException(400, 'INVALID_PARAMETER', 'Неизвестный порядок сортировки.');
    }
    $page = adminPositiveInt($query['page'] ?? 1, 'page', 1000000);
    $perPage = adminPositiveInt($query['perPage'] ?? 5, 'perPage', 100);
    $connection = adminDatabase();
    $total = countFonts($criteria, $connection);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    return [
        'data' => array_map('adminFontData', findFonts($criteria, $perPage, ($page - 1) * $perPage, null, $connection)),
        'pagination' => ['page' => $page, 'perPage' => $perPage, 'pages' => $pages, 'total' => $total,
            'catalogTotal' => countFonts([], $connection)],
    ];
}

function adminGetFont(int $id): array
{
    $font = adminFindFont($id, adminDatabase());
    if ($font === null) {
        throw new ApiException(404, 'FONT_NOT_FOUND', 'Шрифт не найден. Возможно, он был удалён.');
    }
    return adminFontData($font);
}

function adminCheckOwnership(array &$font, array $faces, array $files): void
{
    $claimed = [];
    foreach ($font['faces'] as $index => $face) {
        if ($face['id'] !== null) {
            if (!isset($faces[$face['id']])) {
                throw new ApiException(422, 'VALIDATION_ERROR', 'Проверьте начертания.', ['face-' . $index => 'Начертание не принадлежит этому шрифту.']);
            }
            $claimed[$face['id']] = true;
        }
    }
    foreach ($font['faces'] as $index => &$face) {
        if ($face['id'] === null) {
            foreach ($faces as $id => $stored) {
                if (!isset($claimed[$id]) && (int) $stored['weight'] === $face['weight'] && $stored['style'] === $face['style']) {
                    $face['id'] = (int) $id;
                    $claimed[$id] = true;
                    break;
                }
            }
        }
        $formats = [];
        foreach ($face['files'] as $file) {
            if (isset($file['id']) && !isset($files[$file['id']])) {
                throw new ApiException(422, 'VALIDATION_ERROR', 'Проверьте файлы.', ['face-' . $index => 'Файл не принадлежит этому шрифту.']);
            }
            $format = isset($file['id']) ? $files[$file['id']]['format'] : $file['format'];
            if (isset($formats[$format])) {
                throw new ApiException(422, 'VALIDATION_ERROR', 'Проверьте файлы.', ['face-' . $index => 'Можно выбрать только один файл каждого формата.']);
            }
            $formats[$format] = true;
        }
    }
    unset($face);
}

function adminSaveFont(?int $id, array $payload, array $uploads): array
{
    $font = adminValidateFont($payload, $uploads);
    $connection = adminDatabase();
    $connection->beginTransaction();
    try {
        if ($id !== null && !adminQuery($connection, 'SELECT id FROM fonts WHERE id = ? FOR UPDATE', [$id])->fetchColumn()) {
            throw new ApiException(404, 'FONT_NOT_FOUND', 'Шрифт не найден. Возможно, он был удалён.');
        }
        $faces = $id === null ? [] : adminStoredFaces($id, $connection);
        $files = $id === null ? [] : adminStoredFiles($id, $connection);
        adminCheckOwnership($font, $faces, $files);
        if ($id === null) {
            $slugBase = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $font['name']) ?: 'font')), '-');
            $id = adminInsertFont($font, substr($slugBase ?: 'font', 0, 75), $connection);
        } else {
            adminUpdateFont($id, $font, $connection);
        }
        adminPersistFaces($id, $font, $files, $connection);
        adminPersistTags($id, $font['tags'], $connection);
        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        if ($error instanceof PDOException && in_array($error->getCode(), ['23505', '23503', '40001', '40P01'], true)) {
            throw new ApiException(409, 'DATA_CONFLICT', 'Данные изменились или конфликтуют с другой записью. Обновите страницу и повторите действие.');
        }
        throw $error;
    }
    return adminGetFont($id);
}

function adminDeleteFont(int $id): void
{
    $connection = adminDatabase();
    $connection->beginTransaction();
    try {
        if (!adminQuery($connection, 'DELETE FROM fonts WHERE id = ? RETURNING id', [$id])->fetchColumn()) {
            throw new ApiException(404, 'FONT_NOT_FOUND', 'Шрифт не найден. Возможно, он уже удалён.');
        }
        $connection->exec('UPDATE catalog_state SET changed_at = clock_timestamp() WHERE id = 1');
        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }
}
