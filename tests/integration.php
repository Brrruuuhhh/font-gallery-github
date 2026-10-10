<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$readOnly = in_array('--read-only', $argv, true);

function httpRequest(string $path, array $headers = [], string $method = 'GET'): array
{
    $responseHeaders = [];
    $connection = curl_init('http://127.0.0.1:8000' . $path);
    curl_setopt_array($connection, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($connection);
    if ($body === false) {
        throw new RuntimeException(curl_error($connection));
    }
    $status = curl_getinfo($connection, CURLINFO_RESPONSE_CODE);
    return ['status' => $status, 'headers' => $responseHeaders, 'body' => $body];
}

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . "\n";
}

function cardIds(string $body): array
{
    preg_match_all('/<article class="font-card" id="font-([0-9]+)"/', $body, $matches);
    return $matches[1];
}

try {
    foreach (['/', '/catalog', '/comparison', '/about', '/sitemap', '/sitemap.xml', '/robots.txt'] as $path) {
        $response = httpRequest($path);
        check($response['status'] === 200, $path . ' returns 200');
        check(!preg_match('/\{[A-Z_]+\}|Fatal error:|Warning:|Deprecated:/', $response['body']), $path . ' has no unresolved template or PHP errors');
        check(isset($response['headers']['last-modified']), $path . ' sends Last-Modified');
    }
    $first = httpRequest('/catalog');
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $first['body'], $schemaMatch);
    $schema = json_decode($schemaMatch[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
    check($schema['@type'] === 'CollectionPage'
        && str_ends_with($schema['potentialAction']['target'], '/catalog?q={search_term_string}'),
        'Structured data is valid JSON with a working search route');
    preg_match_all('~(?:src|href)="(/static/[^" ]+\?v=[0-9]+)"~', $first['body'], $assetMatches);
    check(count($assetMatches[1]) === 5, 'Catalog loads versioned CSS and its four scripts');
    foreach ($assetMatches[1] as $asset) {
        check(httpRequest(html_entity_decode($asset))['status'] === 200, 'Versioned asset loads: ' . strtok($asset, '?'));
    }
    check(preg_match_all('~<script type="module" src="/static/~', $first['body']) === 4,
        'Public scripts load as native JavaScript modules');
    $previewModule = httpRequest('/static/font-preview.js');
    check($previewModule['status'] === 200 && str_contains($previewModule['headers']['content-type'], 'javascript'),
        'Shared preview import loads with JavaScript MIME');
    check(str_contains($first['body'], '<h2 class="font-card-title">')
        && !str_contains($first['body'], '<label class="form-label label-inline">'),
        'Catalog cards have semantic headings and CSS captions');
    $second = httpRequest('/catalog?page_num=2');
    $firstIds = cardIds($first['body']);
    $secondIds = cardIds($second['body']);
    check(count($firstIds) === 10 && count($secondIds) === 5 && !array_intersect($firstIds, $secondIds), 'Pagination selects disjoint 10/5 records on the server');
    check(cardIds(httpRequest('/catalog?page_num=999')['body']) === $secondIds, 'Out-of-range page clamps to the last page');
    check(count(cardIds(httpRequest('/catalog?family[]=serif')['body'])) === 3, 'Serif filter');
    check(count(cardIds(httpRequest('/catalog?family[]=serif&q=ab')['body'])) === 3, 'Short search keeps family filter');
    check(cardIds(httpRequest('/catalog?license[]=UFL')['body']) === ['15'], 'Real Ubuntu license filter');
    check(count(cardIds(httpRequest('/catalog?tags[]=' . rawurlencode('код'))['body'])) === 2, 'Tag filter');
    check(cardIds(httpRequest('/catalog?q=Nikita')['body']) === ['5'], 'Author search');
    check(cardIds(httpRequest('/catalog?q=Roboto')['body']) === ['1'], 'Name search');
    check(cardIds(httpRequest('/catalog?q=%25%25%25')['body']) === [], 'LIKE wildcard input is treated literally');
    check(cardIds(httpRequest('/catalog?sort=name-asc')['body'])[0] === '10', 'Name sorting');
    check(cardIds(httpRequest('/catalog?sort=dateAdded-asc')['body'])[0] === '12', 'Date sorting');
    foreach (range(1, 15) as $id) {
        check(in_array((string) $id, cardIds(httpRequest('/catalog?font_id=' . $id)['body']), true), 'Sitemap focus reveals font ' . $id);
    }
    foreach (['/catalog?q[]=x', '/catalog?sort=evil', '/catalog?page_num=-1', '/catalog?family=serif',
        '/catalog?family[]=evil', '/fonts.css?ids[]=1', '/fonts.css?ids=1,evil', '/catalog?page[]=catalog'] as $path) {
        check(httpRequest($path)['status'] === 400, 'Invalid parameter rejected: ' . $path);
    }
    foreach (['/.private/database.php', '/database/fixtures/manifest.json', '/scripts/setup.php',
        '/app/database.php', '/includes/header.php', '/tests/integration.php', '/%2eprivate/database.php'] as $path) {
        $response = httpRequest($path);
        check($response['status'] === 403 && !str_contains($response['body'], "'password'"), 'Protected file blocked: ' . $path);
    }
    check(httpRequest('/catalog', [], 'POST')['status'] === 405, 'Write requests are unavailable in the read-only stage');
    $legacy = httpRequest('/index.php?page=catalog&q=Roboto');
    check($legacy['status'] === 301 && $legacy['headers']['location'] === '/catalog?q=Roboto', 'Legacy URL redirects and preserves search');
    $xss = httpRequest('/catalog?q=' . rawurlencode('<script>alert(1)</script>'));
    check($xss['status'] === 200 && !str_contains($xss['body'], '<script>alert(1)</script>')
        && str_contains($xss['body'], '&lt;script&gt;'), 'Search input is HTML-escaped');
    $notFound = httpRequest('/does-not-exist', ['If-Modified-Since: Wed, 01 Jan 2031 00:00:00 GMT']);
    check($notFound['status'] === 404, 'Conditional header does not turn a 404 into 304');
    check(str_contains($notFound['body'], '<h1 class="not-found-title">Страница не найдена</h1>'),
        'Shared error template preserves the styled 404 page');
    $forbidden = httpRequest('/403');
    check($forbidden['status'] === 403
        && str_contains($forbidden['body'], '<h1 class="not-found-title">Доступ запрещен</h1>'),
        'Shared error template preserves the styled 403 page');
    $etag = $first['headers']['etag'];
    check(httpRequest('/catalog', ['If-None-Match: ' . $etag])['status'] === 304, 'Matching page ETag returns 304');
    check(httpRequest('/catalog?q=Roboto', ['If-None-Match: ' . $etag])['status'] === 200, 'Different query does not reuse another page ETag');
    $css = httpRequest('/fonts.css?ids=1,9');
    check($css['status'] === 200 && str_contains($css['body'], 'GalleryFont1') && str_contains($css['body'], "format('opentype')"), 'CSS contains real file routes and correct format descriptors');

    $files = database()->query('SELECT id, sha256, mime_type, size_bytes FROM font_files ORDER BY id')->fetchAll();
    check(count($files) === 31, '31 binary files stored');
    foreach ($files as $file) {
        $response = httpRequest('/font-files/' . $file['id']);
        check($response['status'] === 200 && hash('sha256', $response['body']) === $file['sha256']
            && strlen($response['body']) === (int) $file['size_bytes'] && $response['headers']['content-type'] === $file['mime_type'],
            'Binary file ' . $file['id'] . ' matches stored hash, size and MIME');
    }
    $file = $files[0];
    $response = httpRequest('/font-files/' . $file['id']);
    $cached = httpRequest('/font-files/' . $file['id'], ['If-None-Match: W/' . $response['headers']['etag']]);
    check($cached['status'] === 304 && $cached['body'] === '', 'Font caching handles weak conditional ETag');
    check($response['headers']['access-control-allow-origin'] === '*', 'Public fonts support exported CSS on another origin');
    check(httpRequest('/font-files/' . $file['id'], ['If-None-Match: ' . $response['headers']['etag']], 'HEAD')['status'] === 304,
        'Conditional HEAD uses the same cache rules as GET');
    check(httpRequest('/font-files/' . $file['id'], ['If-Modified-Since: ' . $response['headers']['last-modified']])['status'] === 304,
        'Font Last-Modified supports conditional requests');
    check(httpRequest('/font-files/' . $file['id'], ['If-None-Match: "different"', 'If-Modified-Since: Wed, 01 Jan 2031 00:00:00 GMT'])['status'] === 200,
        'ETag takes precedence over Last-Modified');
    $head = httpRequest('/font-files/' . $file['id'], [], 'HEAD');
    check($head['status'] === 200 && $head['body'] === '' && (int) $head['headers']['content-length'] === (int) $file['size_bytes'], 'HEAD reports file size without body');
    $font = findFonts([], null, 0, [1])[0];
    $downloadId = fontPreviewFile($font)['id'];
    $before = $font['downloads'];
    httpRequest('/font-files/' . $downloadId);
    httpRequest('/font-files/' . $downloadId . '/download', [], 'HEAD');
    check(findFonts([], null, 0, [1])[0]['downloads'] === $before, 'Preview and HEAD do not count as downloads');
    if (!$readOnly) {
        $download = httpRequest('/font-files/' . $downloadId . '/download', ['If-None-Match: *']);
        check($download['status'] === 200 && str_starts_with($download['headers']['content-disposition'], 'attachment')
            && findFonts([], null, 0, [1])[0]['downloads'] === $before + 1, 'Explicit download returns content and increments counter once');
        check(httpRequest('/catalog', ['If-None-Match: ' . $etag])['status'] === 200,
            'Changing a database download counter invalidates the catalog ETag');
    }
    $license = httpRequest('/font-licenses/1');
    check($license['status'] === 200 && str_contains($license['body'], 'SIL OPEN FONT LICENSE'), 'License is downloadable');
    check(httpRequest('/font-files/999999')['status'] === 404, 'Missing font file returns 404');
    if ($readOnly) {
        check(!database()->query("SELECT has_table_privilege(current_user, 'tags', 'INSERT')")->fetchColumn(),
            'Application role has no catalog insert privilege');
    } else {
        try {
            database()->exec("INSERT INTO tags (name) VALUES ('Unauthorized test')");
            throw new RuntimeException('Application role unexpectedly has write access');
        } catch (PDOException $error) {
            check($error->getCode() === '42501', 'Application role cannot perform catalog CRUD');
        }
    }
    echo $readOnly ? "All read-only integration checks passed. No records or counters changed.\n"
        : "All integration checks passed. One explicit test download was counted.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
