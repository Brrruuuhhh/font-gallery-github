<?php
declare(strict_types=1);

/** Собираем архив на диске, поскольку шрифт может содержать несколько начертаний и много крупных файлов. */
function createFontArchive(array $font, string $license, callable $readFile): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new HttpException(503, 'Скачивание архива временно недоступно. Выберите отдельный файл шрифта.');
    }
    $path = tempnam(sys_get_temp_dir(), 'font-gallery-zip-');
    if ($path === false) {
        throw new RuntimeException('Cannot create a font archive.');
    }
    $zip = new ZipArchive();
    $open = false;
    $complete = false;
    $temporaryFiles = [];
    try {
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open a font archive.');
        }
        $open = true;
        $fileUrls = [];
        foreach ($font['files'] as $file) {
            $content = $readFile($file['id']);
            if (strlen($content) !== $file['size_bytes'] || !hash_equals($file['sha256'], hash('sha256', $content))) {
                throw new RuntimeException('Stored font integrity check failed.');
            }
            $temporary = tempnam(sys_get_temp_dir(), 'font-gallery-file-');
            if ($temporary === false) {
                throw new RuntimeException('Cannot prepare an archive entry.');
            }
            $temporaryFiles[] = $temporary;
            if (file_put_contents($temporary, $content) !== strlen($content)) {
                throw new RuntimeException('Cannot write an archive entry.');
            }
            unset($content);
            $face = preg_replace('/[^a-zA-Z0-9-]/', '_', $file['weight'] . '-' . $file['style']);
            $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['file_name']);
            $entry = 'fonts/' . $face . '/' . $file['id'] . '-' . $name;
            if (!$zip->addFile($temporary, $entry)) {
                throw new RuntimeException('Cannot add a font file to the archive.');
            }
            $fileUrls[$file['id']] = $entry;
        }
        if (!$zip->addFromString('LICENSE.txt', $license)
            || !$zip->addFromString('fonts.css', getFontCssCode($font, false, $fileUrls))) {
            throw new RuntimeException('Cannot add font documentation to the archive.');
        }
        $closed = $zip->close();
        $open = false;
        if (!$closed) {
            throw new RuntimeException('Cannot finish a font archive.');
        }
        $complete = true;
        return $path;
    } finally {
        try {
            if ($open) {
                $zip->close();
            }
        } finally {
            foreach ($temporaryFiles as $temporary) {
                unlink($temporary);
            }
            if (!$complete && is_file($path)) {
                unlink($path);
            }
        }
    }
}

function serveFontArchive(int $id): void
{
    $connection = database();
    $path = null;
    try {
        // Чтение в одной транзакции не позволяет смешать файлы из разных версий записи о шрифте.
        $connection->beginTransaction();
        $connection->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
        $font = findFonts([], null, 0, [$id], $connection)[0] ?? null;
        if ($font === null || !$font['files']) {
            throw new HttpException(404, 'Шрифт или его файлы не найдены.');
        }
        $license = findFontLicense($id);
        if ($license === null) {
            throw new HttpException(404, 'Лицензия шрифта не найдена.');
        }
        $path = createFontArchive($font, $license['license_text'], 'readFontFile');
        $connection->commit();
        $size = filesize($path);
        if ($size === false) {
            throw new RuntimeException('Cannot read the font archive size.');
        }
        $head = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
        if (!$head) {
            recordFontDownload($id);
        }
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $font['slug']) . '.zip';
        header('Content-Disposition: attachment; filename="' . $name . '"');
        prepareResponse('application/zip', null, false, '');
        header('Content-Length: ' . $size);
        if (!$head) {
            readfile($path);
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        if ($path !== null) {
            unlink($path);
        }
    }
}
