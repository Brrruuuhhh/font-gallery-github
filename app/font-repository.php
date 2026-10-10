<?php
declare(strict_types=1);

const FONT_SORTS = [
    'dateAdded-desc' => 'f.date_added DESC, f.id ASC',
    'dateAdded-asc' => 'f.date_added ASC, f.id ASC',
    'name-asc' => 'f.name ASC, f.id ASC',
    'name-desc' => 'f.name DESC, f.id ASC',
    'downloads-desc' => 'f.downloads DESC, f.id ASC',
    'downloads-asc' => 'f.downloads ASC, f.id ASC',
];

/** Структура SQL-запроса задана в коде; из параметров запроса подставляются только значения. */
function fontFilterSql(array $criteria): array
{
    $conditions = [];
    $parameters = [];
    foreach (['family' => 'f.family', 'license' => 'f.license', 'tags' => 't.name'] as $key => $column) {
        $values = $criteria[$key] ?? [];
        if (!$values) {
            continue;
        }
        $placeholders = [];
        foreach ($values as $index => $value) {
            $name = $key . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $value;
        }
        $condition = $column . ' IN (' . implode(', ', $placeholders) . ')';
        $conditions[] = $key === 'tags'
            ? 'EXISTS (SELECT 1 FROM font_tags ft JOIN tags t ON t.id = ft.tag_id WHERE ft.font_id = f.id AND ' . $condition . ')'
            : $condition;
    }

    $query = $criteria['q'] ?? '';
    if (mb_strlen($query) >= ($criteria['minQueryLength'] ?? 3)) {
        // Экранируем специальные символы LIKE, чтобы поиск воспринимал введённую строку как обычный текст.
        $pattern = '%' . strtr($query, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        $conditions[] = '(f.name ILIKE :query_name OR f.author ILIKE :query_author OR EXISTS '
            . '(SELECT 1 FROM font_tags ft JOIN tags t ON t.id = ft.tag_id '
            . 'WHERE ft.font_id = f.id AND t.name ILIKE :query_tag))';
        $parameters += ['query_name' => $pattern, 'query_author' => $pattern, 'query_tag' => $pattern];
    }

    return [$conditions ? ' WHERE ' . implode(' AND ', $conditions) : '', $parameters];
}

function countFonts(array $criteria = [], ?PDO $connection = null): int
{
    [$where, $parameters] = fontFilterSql($criteria);
    $statement = ($connection ?? database())->prepare('SELECT count(*) FROM fonts f' . $where);
    $statement->execute($parameters);
    return (int) $statement->fetchColumn();
}

function catalogStatistics(): array
{
    $row = database()->query('SELECT count(*) AS fonts, count(DISTINCT family) AS families FROM fonts')->fetch();
    return ['fonts' => (int) $row['fonts'], 'families' => (int) $row['families']];
}

/** При получении списка сведений о шрифтах содержимое файлов и тексты лицензий не загружаются. */
function findFonts(array $criteria = [], ?int $limit = null, int $offset = 0, ?array $ids = null, ?PDO $connection = null): array
{
    if ($ids === []) {
        return [];
    }
    $connection ??= database();
    [$where, $parameters] = fontFilterSql($criteria);
    if ($ids !== null) {
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $name = 'id' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $id;
        }
        $where .= ($where ? ' AND ' : ' WHERE ') . 'f.id IN (' . implode(', ', $placeholders) . ')';
    }
    $order = FONT_SORTS[$criteria['sort'] ?? 'dateAdded-desc'] ?? FONT_SORTS['dateAdded-desc'];
    $sql = "SELECT f.id, f.slug, f.name, f.family, f.author, f.license, f.preview_text,
                   f.date_added, f.downloads, f.supports_cyrillic,
                   COALESCE((SELECT json_agg(t.name ORDER BY t.name) FROM font_tags ft
                       JOIN tags t ON t.id = ft.tag_id WHERE ft.font_id = f.id), '[]') AS tags
            FROM fonts f" . $where . ' ORDER BY ' . $order;
    if ($limit !== null) {
        // Числа получены из проверенных параметров постраничного вывода, а не из введённого пользователем SQL.
        $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    }
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $fonts = [];
    foreach ($statement->fetchAll() as $row) {
        $fonts[(string) $row['id']] = [
            'id' => (string) $row['id'], 'slug' => $row['slug'], 'name' => $row['name'],
            'family' => $row['family'], 'author' => $row['author'], 'license' => $row['license'],
            'previewText' => $row['preview_text'], 'dateAdded' => $row['date_added'],
            'downloads' => (int) $row['downloads'], 'supportsCyrillic' => (bool) $row['supports_cyrillic'],
            'tags' => json_decode($row['tags'], true, 512, JSON_THROW_ON_ERROR), 'files' => [],
        ];
    }
    if ($fonts) {
        $ids = array_keys($fonts);
        $statement = $connection->prepare(
            'SELECT ff.id, ff.face_id, face.font_id, face.weight, face.style, ff.format, ff.file_name, ff.mime_type, '
            . 'ff.size_bytes, ff.sha256 FROM font_files ff JOIN font_faces face ON face.id = ff.face_id '
            . 'WHERE face.font_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ') '
            . "ORDER BY CASE ff.format WHEN 'woff2' THEN 0 WHEN 'woff' THEN 1 WHEN 'ttf' THEN 2 ELSE 3 END, face.weight, ff.id"
        );
        $statement->execute($ids);
        foreach ($statement->fetchAll() as $file) {
            $file['id'] = (int) $file['id'];
            $file['face_id'] = (int) $file['face_id'];
            $file['weight'] = (int) $file['weight'];
            $file['size_bytes'] = (int) $file['size_bytes'];
            $fonts[(string) $file['font_id']]['files'][] = $file;
        }
    }
    return array_values($fonts);
}

function findAllTags(): array
{
    return database()->query('SELECT name FROM tags ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
}

function fontPageForId(int $id, array $criteria, int $perPage): int
{
    [$where, $parameters] = fontFilterSql($criteria);
    $order = FONT_SORTS[$criteria['sort']];
    $statement = database()->prepare('SELECT position FROM (SELECT f.id, row_number() OVER (ORDER BY '
        . $order . ') AS position FROM fonts f' . $where . ') ranked WHERE id = :focus_id');
    $statement->execute($parameters + ['focus_id' => $id]);
    $position = $statement->fetchColumn();
    return $position === false ? 1 : (int) ceil((int) $position / $perPage);
}

function catalogModifiedTimestamp(): int
{
    $connection = database();
    $catalogState = $connection->query("SELECT to_regclass('public.catalog_state') IS NOT NULL")->fetchColumn();
    $sources = 'SELECT max(updated_at) AS modified FROM fonts';
    if ($catalogState) {
        $sources .= ' UNION ALL SELECT changed_at FROM catalog_state WHERE id = 1';
    }
    return (int) $connection->query('SELECT COALESCE(floor(EXTRACT(EPOCH FROM max(modified)))::bigint, 0) FROM ('
        . $sources . ') changes')->fetchColumn();
}

function findFontFile(int $id): ?array
{
    $statement = database()->prepare('SELECT ff.id, ff.file_name, ff.mime_type, ff.size_bytes, ff.sha256, '
        . 'floor(EXTRACT(EPOCH FROM ff.updated_at))::bigint AS modified, face.font_id '
        . 'FROM font_files ff JOIN font_faces face ON face.id = ff.face_id WHERE ff.id = :id');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function readFontFile(int $id): string
{
    $statement = database()->prepare("SELECT encode(content, 'base64') FROM font_files WHERE id = :id");
    $statement->execute(['id' => $id]);
    $encoded = $statement->fetchColumn();
    if ($encoded === false) {
        throw new RuntimeException('Font file was removed during retrieval.');
    }
    $content = base64_decode($encoded, true);
    if ($content === false) {
        throw new RuntimeException('Invalid binary file response.');
    }
    return $content;
}

function recordFontDownload(int $fontId): void
{
    $statement = database()->prepare('UPDATE fonts SET downloads = downloads + 1 WHERE id = :id');
    $statement->execute(['id' => $fontId]);
}

function findFontLicense(int $id): ?array
{
    $statement = database()->prepare('SELECT slug, license_text FROM fonts WHERE id = :id');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}
