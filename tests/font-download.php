<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/http.php';
require dirname(__DIR__) . '/app/font-service.php';
require dirname(__DIR__) . '/app/font-download.php';
require dirname(__DIR__) . '/app/view.php';

/** Подмена хранилища данными в памяти: проверки служебной логики не подключаются к БД проекта. */
class ArchiveTestConnection
{
    private bool $active = false;
    public function beginTransaction(): void
    {
        $this->active = true;
    }
    public function exec(string $sql): void
    {
    }
    public function commit(): void
    {
        $this->active = false;
    }
    public function inTransaction(): bool
    {
        return $this->active;
    }
    public function rollBack(): void
    {
        $this->active = false;
    }
}
function database(): ArchiveTestConnection
{
    static $connection = new ArchiveTestConnection();
    return $connection;
}
function findFonts(array $criteria, ?int $limit, int $offset, array $ids, ArchiveTestConnection $connection): array
{
    return $ids === [6] ? [$GLOBALS['archiveFont']] : [];
}
function findFontLicense(int $id): array
{
    return ['license_text' => 'License'];
}
function readFontFile(int $id): string
{
    return $GLOBALS['archiveContent'][$id];
}
function recordFontDownload(int $id): void
{
    checkArchive(!database()->inTransaction(), 'Download counter updated after read-only snapshot');
    $GLOBALS['archiveDownloads']++;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$checks = 0;
$path = null;
$reader = new ZipArchive();
$readerOpen = false;
$exitCode = 0;
function checkArchive(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $GLOBALS['checks']++;
}

try {
    $content = [1 => "woff\0regular", 2 => "ttf\0regular", 3 => "woff\0bold-italic", 4 => "ttf\0bold-italic"];
    $font = ['id' => '6', 'family' => 'serif', 'files' => []];
    foreach ($content as $id => $bytes) {
        $font['files'][] = [
            'id' => $id, 'format' => $id % 2 ? 'woff' : 'ttf',
            'weight' => $id <= 2 ? 400 : 700, 'style' => $id <= 2 ? 'normal' : 'italic',
            'file_name' => $id === 4 ? '../../same.ttf' : 'same.' . ($id % 2 ? 'woff' : 'ttf'),
            'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
        ];
    }
    $before = glob(sys_get_temp_dir() . '/font-gallery-*') ?: [];
    $license = 'Лицензия: разрешено использование.';
    $path = createFontArchive($font, $license, static fn ($id) => $content[$id]);
    checkArchive($reader->open($path, ZipArchive::CHECKCONS) === true, 'Valid ZIP');
    $readerOpen = true;
    checkArchive($reader->numFiles === 6, 'Every face and format plus CSS and license');
    checkArchive($reader->getFromName('LICENSE.txt') === $license, 'License preserved');
    $css = $reader->getFromName('fonts.css');
    checkArchive(is_string($css) && !str_contains($css, '/font-files/') && !str_contains($css, 'http'), 'CSS uses local archive files');
    checkArchive(str_contains($css, 'font-weight: 700;') && str_contains($css, 'font-style: italic;'), 'CSS includes all faces');
    foreach ($font['files'] as $file) {
        $entry = null;
        for ($index = 0; $index < $reader->numFiles; $index++) {
            $name = $reader->getNameIndex($index);
            if (str_starts_with(basename($name), $file['id'] . '-')) {
                $entry = $name;
            }
            checkArchive(!in_array('..', explode('/', $name), true) && !str_contains($name, '\\') && !str_starts_with($name, '/'), 'Safe archive path');
        }
        checkArchive($entry !== null && $reader->getFromName($entry) === $content[$file['id']], 'Original binary file preserved');
        checkArchive(str_contains($css, "url('" . $entry . "')"), 'CSS points to an existing archive entry');
    }
    $reader->close();
    $readerOpen = false;
    unlink($path);
    $path = null;
    checkArchive((glob(sys_get_temp_dir() . '/font-gallery-*') ?: []) === $before, 'Temporary files cleaned after success');

    foreach ([1, 3] as $brokenId) {
        try {
            $path = createFontArchive($font, $license, static fn ($id) => $id === $brokenId ? 'broken' : $content[$id]);
            throw new LogicException('Corrupt file accepted');
        } catch (RuntimeException $error) {
            checkArchive(str_contains($error->getMessage(), 'integrity'), 'Corrupt file rejected');
        }
        checkArchive((glob(sys_get_temp_dir() . '/font-gallery-*') ?: []) === $before, 'Temporary files cleaned after failure');
    }
    $links = generateFontFileLinks($font['files']);
    checkArchive(substr_count($links, 'class="font-file-link"') === 4, 'Repeated formats from different faces retained');
    checkArchive(str_contains($links, '400 · обычное') && str_contains($links, '700 · курсив'), 'Face labels distinguish individual downloads');
    checkArchive(substr_count(generateFontFileLinks(array_slice($font['files'], 0, 2)), ' · ') === 0, 'Single face keeps compact format labels');
    $_SERVER['HTTP_HOST'] = 'example.test';
    checkArchive(str_contains(getFontCssCode($font), "url('http://example.test/font-files/1')"), 'Public CSS export unchanged');
    checkArchive(str_contains(getFontCssCode($font, false), "url('/font-files/1')"), 'Preview CSS unchanged');
    $boldOnly = $font;
    $boldOnly['files'] = array_slice($font['files'], 2);
    checkArchive(str_ends_with(getFontCssCode($boldOnly, false), "font-weight: 700;\n  font-style: italic;\n}"),
        'Default preview class uses the available bold italic face instead of a missing regular face');
    $reordered = $font;
    $reordered['files'] = array_reverse($font['files']);
    checkArchive(str_ends_with(getFontCssCode($reordered, false), "font-weight: 400;\n  font-style: normal;\n}"),
        'Regular face remains the default regardless of file order');

    $GLOBALS['archiveFont'] = $font + ['slug' => 'family'];
    $GLOBALS['archiveContent'] = $content;
    $GLOBALS['archiveDownloads'] = 0;
    $_SERVER['REQUEST_METHOD'] = 'HEAD';
    ob_start();
    serveFontArchive(6);
    $head = ob_get_clean();
    checkArchive($head === '' && $GLOBALS['archiveDownloads'] === 0, 'HEAD has no body and is not counted');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_IF_NONE_MATCH'] = '*';
    ob_start();
    serveFontArchive(6);
    $body = ob_get_clean();
    checkArchive(str_starts_with($body, "PK\x03\x04") && $GLOBALS['archiveDownloads'] === 1, 'GET sends ZIP and counts one download for every file together');
    checkArchive(!database()->inTransaction() && (glob(sys_get_temp_dir() . '/font-gallery-*') ?: []) === $before, 'GET closes snapshot and removes temporary files');
    try {
        serveFontArchive(999);
        throw new LogicException('Missing font accepted');
    } catch (HttpException $error) {
        checkArchive($error->status === 404 && !database()->inTransaction(), 'Missing font returns 404 and closes snapshot');
    }
    $GLOBALS['archiveContent'][1] = 'broken';
    try {
        serveFontArchive(6);
        throw new LogicException('Corrupt download accepted');
    } catch (RuntimeException $error) {
        checkArchive(str_contains($error->getMessage(), 'integrity') && $GLOBALS['archiveDownloads'] === 1, 'Failed archive is not counted');
    }
    checkArchive(!database()->inTransaction() && (glob(sys_get_temp_dir() . '/font-gallery-*') ?: []) === $before, 'Failed download rolls back snapshot and cleans files');
    echo "All $checks font archive checks passed. No project database access or real download counting.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if ($readerOpen) {
        $reader->close();
    }
    if ($path !== null && is_file($path)) {
        unlink($path);
    }
}
exit($exitCode);
