<?php
declare(strict_types=1);

const FONT_CSS_FORMATS = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];

function fontFamilyName(array $font): string
{
    return 'GalleryFont' . $font['id'];
}

function fontPreviewFile(array $font): ?array
{
    foreach ($font['files'] as $file) {
        if ($file['weight'] === 400 && $file['style'] === 'normal') {
            return $file;
        }
    }
    return $font['files'][0] ?? null;
}

function fontFileUrl(array $file, bool $download = false): string
{
    return '/font-files/' . $file['id'] . ($download ? '/download' : '');
}

/** Предпросмотр и выдаваемый пользователю CSS используют одни и те же определения начертаний. */
function getFontCssCode(array $font, bool $absoluteUrls = true, array $fileUrls = []): string
{
    $faces = [];
    foreach ($font['files'] as $file) {
        $key = $file['weight'] . '-' . $file['style'];
        $faces[$key]['weight'] = $file['weight'];
        $faces[$key]['style'] = $file['style'];
        $url = $fileUrls[$file['id']] ?? (($absoluteUrls ? siteOrigin() : '') . fontFileUrl($file));
        $faces[$key]['sources'][] = "url('" . $url . "') format('" . FONT_CSS_FORMATS[$file['format']] . "')";
    }
    $css = [];
    foreach ($faces as $face) {
        $css[] = "@font-face {\n  font-family: '" . fontFamilyName($font) . "';\n  src: "
            . implode(",\n       ", $face['sources']) . ";\n  font-weight: " . $face['weight']
            . ";\n  font-style: " . $face['style'] . ";\n  font-display: swap;\n}";
    }
    $preview = fontPreviewFile($font);
    $css[] = ".font-" . $font['id'] . " {\n  font-family: '" . fontFamilyName($font) . "', "
        . ($font['family'] === 'monospace' ? 'monospace' : ($font['family'] === 'serif' ? 'serif' : 'sans-serif'))
        . ";\n  font-weight: " . ($preview['weight'] ?? 400) . ";\n  font-style: " . ($preview['style'] ?? 'normal') . ";\n}";
    return implode("\n\n", $css);
}

function fontClientData(array $font): array
{
    $file = fontPreviewFile($font);
    return [
        'name' => $font['name'], 'author' => $font['author'],
        'tags' => $font['tags'], 'license' => $font['license'],
        'supportsCyrillic' => $font['supportsCyrillic'],
        'previewText' => $font['previewText'], 'weight' => $file['weight'] ?? 400,
        'style' => $file['style'] ?? 'normal',
    ];
}

function serveFontFile(int $id, bool $download): void
{
    $file = findFontFile($id);
    if ($file === null) {
        throw new HttpException(404, 'Файл шрифта не найден.');
    }
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['file_name']);
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
    // Выдаваемый CSS может загружать эти общедоступные файлы шрифтов с другого сайта.
    if (!$download) {
        header('Access-Control-Allow-Origin: *');
    }
    if (!prepareResponse($file['mime_type'], (int) $file['modified'], !$download, '"' . $file['sha256'] . '"')) {
        return;
    }
    header('Content-Length: ' . $file['size_bytes']);
    // HEAD возвращает тот же размер, что и GET, без чтения содержимого файла и увеличения счётчика скачиваний.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        return;
    }
    $content = readFontFile($id);
    if (!hash_equals($file['sha256'], hash('sha256', $content))) {
        throw new RuntimeException('Stored font integrity check failed.');
    }
    if ($download) {
        recordFontDownload((int) $file['font_id']);
    }
    echo $content;
}
