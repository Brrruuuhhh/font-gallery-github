<?php
declare(strict_types=1);

function adminQuery(PDO $connection, string $sql, array $parameters = []): PDOStatement
{
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    return $statement;
}

function adminFindFont(int $id, PDO $connection): ?array
{
    $font = findFonts([], null, 0, [$id], $connection)[0] ?? null;
    if ($font === null) {
        return null;
    }
    $details = adminQuery($connection, 'SELECT license_text, source_url FROM fonts WHERE id = ?', [$id])->fetch();
    if (!$details) {
        return null;
    }
    return $font + ['licenseText' => $details['license_text'], 'sourceUrl' => $details['source_url']];
}

function adminStoredFaces(int $id, PDO $connection): array
{
    return adminQuery($connection, 'SELECT id, weight, style FROM font_faces WHERE font_id = ? ORDER BY id', [$id])->fetchAll(PDO::FETCH_UNIQUE);
}

function adminStoredFiles(int $id, PDO $connection): array
{
    return adminQuery($connection,
        'SELECT ff.id, ff.face_id, ff.format, ff.file_name, ff.sha256, ff.source_url FROM font_files ff '
        . 'JOIN font_faces face ON face.id = ff.face_id WHERE face.font_id = ? ORDER BY ff.id', [$id]
    )->fetchAll(PDO::FETCH_UNIQUE);
}

function adminFontParameters(array $font): array
{
    return [
        $font['name'], $font['family'], $font['author'], $font['license'], $font['licenseText'],
        $font['sourceUrl'], $font['previewText'], $font['dateAdded'], $font['supportsCyrillic'] ? 'true' : 'false',
    ];
}

function adminInsertFont(array $font, string $slugBase, PDO $connection): int
{
    $id = (int) $connection->query("SELECT nextval(pg_get_serial_sequence('fonts', 'id'))")->fetchColumn();
    adminQuery($connection, 'INSERT INTO fonts '
        . '(id, slug, name, family, author, license, license_text, source_url, preview_text, date_added, supports_cyrillic) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$id, $slugBase . '-' . $id, ...adminFontParameters($font)]);
    return $id;
}

function adminUpdateFont(int $id, array $font, PDO $connection): void
{
    $parameters = adminFontParameters($font);
    adminQuery($connection, 'UPDATE fonts SET name = ?, family = ?, author = ?, license = ?, license_text = ?, '
        . 'source_url = ?, preview_text = ?, date_added = ?, supports_cyrillic = ? WHERE id = ? '
        . 'AND (name, family, author, license, license_text, source_url, preview_text, date_added, supports_cyrillic) '
        . 'IS DISTINCT FROM (?, ?, ?, ?, ?, ?, ?, ?::date, ?::boolean)', [...$parameters, $id, ...$parameters]);
}

/** Сохраняем идентификаторы. Уникальность проверяется при завершении транзакции, чтобы разрешить обмен значениями. */
function adminPersistFaces(int $fontId, array $font, array $storedFiles, PDO $connection): void
{
    $faceIds = $fileIds = $retained = [];
    foreach ($font['faces'] as $face) {
        foreach ($face['files'] as $file) {
            if (isset($file['id'])) {
                $retained[$file['id']] = true;
            }
        }
    }
    foreach ($font['faces'] as $face) {
        $faceId = $face['id'];
        if ($faceId === null) {
            $faceId = (int) adminQuery($connection,
                'INSERT INTO font_faces (font_id, weight, style) VALUES (?, ?, ?) RETURNING id',
                [$fontId, $face['weight'], $face['style']])->fetchColumn();
        } else {
            adminQuery($connection, 'UPDATE font_faces SET weight = ?, style = ? WHERE id = ? '
                . 'AND (weight, style) IS DISTINCT FROM (?::smallint, ?::varchar)',
                [$face['weight'], $face['style'], $faceId, $face['weight'], $face['style']]);
        }
        $faceIds[] = $faceId;
        foreach ($face['files'] as $file) {
            if (isset($file['id'])) {
                $fileId = $file['id'];
                adminQuery($connection, 'UPDATE font_files SET face_id = ? WHERE id = ? AND face_id <> ?', [$faceId, $fileId, $faceId]);
            } else {
                $candidate = null;
                foreach ($storedFiles as $storedId => $stored) {
                    if ((int) $stored['face_id'] === $faceId && $stored['format'] === $file['format']
                        && !isset($retained[$storedId]) && !in_array((int) $storedId, $fileIds, true)) {
                        $candidate = (int) $storedId;
                        break;
                    }
                }
                if ($candidate !== null) {
                    $fileId = $candidate;
                    $old = $storedFiles[$fileId];
                    if ($old['sha256'] !== $file['sha256'] || $old['file_name'] !== $file['name'] || $old['source_url'] !== $font['sourceUrl']) {
                        adminQuery($connection, 'UPDATE font_files SET file_name = ?, mime_type = ?, size_bytes = ?, '
                            . "sha256 = ?, source_url = ?, content = decode(?, 'base64') WHERE id = ?",
                            [$file['name'], $file['mime'], $file['size'], $file['sha256'], $font['sourceUrl'], base64_encode($file['content']), $fileId]);
                    }
                } else {
                    $fileId = (int) adminQuery($connection, 'INSERT INTO font_files '
                        . '(face_id, format, file_name, mime_type, size_bytes, sha256, source_url, content) '
                        . "VALUES (?, ?, ?, ?, ?, ?, ?, decode(?, 'base64')) RETURNING id",
                        [$faceId, $file['format'], $file['name'], $file['mime'], $file['size'], $file['sha256'], $font['sourceUrl'], base64_encode($file['content'])])->fetchColumn();
                }
            }
            $fileIds[] = $fileId;
        }
    }
    adminQuery($connection, 'DELETE FROM font_files WHERE face_id IN (SELECT id FROM font_faces WHERE font_id = ?) '
        . 'AND id NOT IN (' . implode(',', array_fill(0, count($fileIds), '?')) . ')', [$fontId, ...$fileIds]);
    adminQuery($connection, 'DELETE FROM font_faces WHERE font_id = ? AND id NOT IN ('
        . implode(',', array_fill(0, count($faceIds), '?')) . ')', [$fontId, ...$faceIds]);
}

function adminPersistTags(int $fontId, array $tags, PDO $connection): void
{
    $current = adminQuery($connection, 'SELECT t.name FROM tags t JOIN font_tags ft ON ft.tag_id = t.id WHERE ft.font_id = ? ORDER BY t.name', [$fontId])->fetchAll(PDO::FETCH_COLUMN);
    sort($current);
    sort($tags);
    if ($current === $tags) {
        return;
    }
    adminQuery($connection, 'DELETE FROM font_tags WHERE font_id = ?', [$fontId]);
    foreach ($tags as $name) {
        adminQuery($connection, 'INSERT INTO tags (name) VALUES (?) ON CONFLICT (name) DO NOTHING', [$name]);
        adminQuery($connection, 'INSERT INTO font_tags (font_id, tag_id) SELECT ?, id FROM tags WHERE name = ?', [$fontId, $name]);
    }
}
