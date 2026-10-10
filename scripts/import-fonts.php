<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_DIR', dirname(__DIR__));

try {
    $fixtureRoot = BASE_DIR . '/database/fixtures';
    $manifest = json_decode(file_get_contents($fixtureRoot . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $host = getenv('PGHOST') ?: '127.0.0.1';
    $port = getenv('PGPORT') ?: '5432';
    if (!preg_match('/^[a-zA-Z0-9.\/\-]+$/', $host) || !ctype_digit($port)) {
        throw new RuntimeException('Invalid PostgreSQL connection settings.');
    }
    $connection = new PDO('pgsql:host=' . $host . ';port=' . $port . ';dbname=font_gallery',
        getenv('PGUSER') ?: getenv('USER'), getenv('PGPASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    $connection->beginTransaction();
    $fontStatement = $connection->prepare('INSERT INTO fonts '
        . '(id, slug, name, family, author, license, license_text, source_url, preview_text, date_added, downloads, supports_cyrillic) '
        . 'VALUES (:id, :slug, :name, :family, :author, :license, :license_text, :source_url, :preview_text, :date_added, :downloads, :cyrillic) '
        . 'ON CONFLICT (id) DO NOTHING RETURNING id');
    $tagStatement = $connection->prepare('INSERT INTO tags (name) VALUES (:name) ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name RETURNING id');
    $linkStatement = $connection->prepare('INSERT INTO font_tags (font_id, tag_id) VALUES (:font_id, :tag_id)');
    $faceStatement = $connection->prepare('INSERT INTO font_faces (font_id, weight, style) VALUES (:font_id, :weight, :style) RETURNING id');
    $fileStatement = $connection->prepare('INSERT INTO font_files '
        . '(face_id, format, file_name, mime_type, size_bytes, sha256, source_url, content) '
        . "VALUES (:face_id, :format, :file_name, :mime_type, :size_bytes, :sha256, :source_url, decode(:content, 'base64'))");
    $added = 0;
    $fileCount = 0;
    foreach ($manifest as $font) {
        $fontStatement->execute([
            'id' => $font['id'], 'slug' => $font['slug'], 'name' => $font['name'], 'family' => $font['family'],
            'author' => $font['author'], 'license' => $font['license'],
            'license_text' => file_get_contents($fixtureRoot . '/' . $font['licenseFile']),
            'source_url' => $font['sourceUrl'], 'preview_text' => $font['previewText'],
            'date_added' => $font['dateAdded'], 'downloads' => $font['downloads'],
            'cyrillic' => $font['supportsCyrillic'] ? 'true' : 'false',
        ]);
        if ($fontStatement->fetchColumn() === false) {
            continue; // Повторный импорт не перезаписывает существующие записи каталога и их счётчики.
        }
        foreach ($font['tags'] as $tag) {
            $tagStatement->execute(['name' => $tag]);
            $linkStatement->execute(['font_id' => $font['id'], 'tag_id' => $tagStatement->fetchColumn()]);
        }
        $faceStatement->execute(['font_id' => $font['id'], 'weight' => $font['weight'], 'style' => $font['style']]);
        $faceId = $faceStatement->fetchColumn();
        foreach ($font['files'] as $file) {
            $content = file_get_contents($fixtureRoot . '/' . $file['path']);
            if ($content === false || !hash_equals($file['sha256'], hash('sha256', $content))) {
                throw new RuntimeException('Fixture integrity check failed: ' . $file['name']);
            }
            $fileStatement->execute([
                'face_id' => $faceId, 'format' => $file['format'], 'file_name' => $file['name'],
                'mime_type' => 'font/' . $file['format'], 'size_bytes' => strlen($content),
                'sha256' => $file['sha256'], 'source_url' => $file['sourceUrl'],
                'content' => base64_encode($content),
            ]);
            $fileCount++;
        }
        $added++;
    }
    foreach (['fonts', 'tags', 'font_faces', 'font_files'] as $table) {
        $connection->exec("SELECT setval(pg_get_serial_sequence('$table', 'id'), COALESCE((SELECT max(id) FROM $table), 1), EXISTS (SELECT 1 FROM $table))");
    }
    $connection->commit();
    echo "Imported $added fonts and $fileCount binary files. Existing fonts were preserved.\n";
} catch (Throwable $error) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'Import failed: ' . $error->getMessage() . "\n");
    exit(1);
}
