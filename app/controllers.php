<?php
declare(strict_types=1);

function pageBreadcrumbs(string $label): array
{
    return [['label' => 'Главная', 'url' => '/'], ['label' => $label]];
}

function renderHome(): string
{
    $statistics = catalogStatistics();
    return renderPage('home', SITE_NAME, [
        '{TOTAL_FONTS}' => $statistics['fonts'], '{TOTAL_FAMILIES}' => $statistics['families'],
    ]);
}

function renderCatalog(): string
{
    $criteria = catalogCriteria();
    $total = countFonts($criteria);
    $totalPages = max(1, (int) ceil($total / 10));
    $page = requestPositiveInt('page_num');
    if (isset($_GET['font_id'])) {
        $page = fontPageForId(requestPositiveInt('font_id'), $criteria, 10);
    }
    $page = min($page, $totalPages);
    $fonts = findFonts($criteria, 10, ($page - 1) * 10);
    $placeholders = [
        '{SIDEBAR_FILTERS}' => renderSidebar($criteria, findAllTags()),
        '{FONTS_CARDS}' => generateFontCards($fonts), '{FONTS_COUNT}' => $total,
        '{EMPTY_CLASS}' => $total ? 'hidden' : '',
        '{PAGINATION}' => $total ? renderPagination($criteria, $page, $totalPages) : '',
    ];
    foreach (array_keys(FONT_SORTS) as $sort) {
        $placeholders['{SORT_' . strtoupper(str_replace('-', '_', $sort)) . '}'] = $criteria['sort'] === $sort ? ' selected' : '';
    }
    return renderPage('catalog', 'Каталог шрифтов - ' . SITE_NAME, $placeholders, pageBreadcrumbs('Каталог'),
        array_column($fonts, 'id'), ['/static/catalog.js', '/static/font-card.js', '/static/comparison.js']);
}

function renderComparison(): string
{
    $fonts = findFonts();
    $data = [];
    foreach ($fonts as $font) {
        $data[$font['id']] = fontClientData($font);
    }
    return renderPage('comparison', 'Сравнение шрифтов - ' . SITE_NAME, [
        '{FONTS_DATA}' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR),
    ], pageBreadcrumbs('Сравнение'), array_column($fonts, 'id'), ['/static/comparison.js']);
}

function renderAbout(): string
{
    return renderPage('about', 'О сайте - ' . SITE_NAME, [], pageBreadcrumbs('О сайте'));
}

function renderSitemap(): string
{
    return renderPage('sitemap', 'Карта сайта - ' . SITE_NAME, [
        '{FONT_LINKS}' => generateSitemapLinks(findFonts()),
    ], pageBreadcrumbs('Карта сайта'));
}

function renderError(int $status, string $message = ''): string
{
    http_response_code($status);
    $titles = [400 => 'Ошибка запроса', 403 => 'Доступ запрещен', 404 => 'Страница не найдена',
        405 => 'Ошибка запроса', 503 => 'Сервис временно недоступен'];
    $title = $titles[$status] ?? 'Сервис временно недоступен';
    $messages = [403 => 'У вас нет прав для просмотра этой страницы.',
        404 => 'К сожалению, запрашиваемая страница не существует'];
    return renderPage('error', $title . ' - ' . SITE_NAME, [
        '{ERROR_CODE}' => $status, '{ERROR_TITLE}' => escapeHtml($title),
        '{ERROR_MESSAGE}' => escapeHtml($messages[$status] ?? ($message ?: 'Попробуйте открыть страницу позднее.')),
    ], pageBreadcrumbs($title));
}

function renderSitemapXml(): string
{
    $modified = gmdate('Y-m-d', getLastModifiedTimestamp());
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach (['/' => '1.0', '/catalog' => '0.9', '/comparison' => '0.7', '/about' => '0.6', '/sitemap' => '0.5'] as $path => $priority) {
        $xml .= '<url><loc>' . escapeHtml(siteOrigin() . $path) . '</loc><lastmod>' . $modified
            . '</lastmod><priority>' . $priority . '</priority></url>' . "\n";
    }
    return $xml . '</urlset>';
}

function renderRobotsTxt(): string
{
    return "User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: " . siteOrigin() . "/sitemap.xml\n";
}

function dispatchRequest(): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD');
        throw new HttpException(405, 'Этот маршрут принимает только GET и HEAD.');
    }
    $path = getRequestPath();
    if (preg_match('~^/font-downloads/([1-9][0-9]{0,8})$~D', $path, $match)) {
        serveFontArchive((int) $match[1]);
        return;
    }
    if (preg_match('~^/font-files/([1-9][0-9]{0,8})(/download)?$~D', $path, $match)) {
        serveFontFile((int) $match[1], isset($match[2]));
        return;
    }
    if (preg_match('~^/font-licenses/([1-9][0-9]{0,8})$~D', $path, $match)) {
        $license = findFontLicense((int) $match[1]);
        if ($license === null) {
            throw new HttpException(404, 'Лицензия не найдена.');
        }
        header('Content-Disposition: attachment; filename="' . $license['slug'] . '-LICENSE.txt"');
        sendResponse($license['license_text'], 'text/plain; charset=UTF-8');
        return;
    }
    if ($path === '/fonts.css') {
        $fonts = findFonts([], null, 0, requestFontIds());
        $css = implode("\n\n", array_map(static fn ($font) => getFontCssCode($font, false), $fonts));
        sendResponse($css, 'text/css; charset=UTF-8');
        return;
    }
    if ($path === '/sitemap.xml') {
        sendResponse(renderSitemapXml(), 'application/xml; charset=UTF-8');
        return;
    }
    if ($path === '/robots.txt') {
        sendResponse(renderRobotsTxt(), 'text/plain; charset=UTF-8');
        return;
    }
    $routes = ['/' => 'renderHome', '/index.php' => 'renderHome', '/catalog' => 'renderCatalog',
        '/comparison' => 'renderComparison', '/about' => 'renderAbout', '/sitemap' => 'renderSitemap'];
    if (isset($_GET['page'])) {
        $legacy = requestString('page', '', 30);
        $target = $legacy === 'home' ? '/' : '/' . $legacy;
        if (!isset($routes[$target])) {
            throw new HttpException(404, 'Страница не найдена.');
        }
        $query = $_GET;
        unset($query['page']);
        header('Location: ' . $target . ($query ? '?' . http_build_query($query) : ''), true, 301);
        return;
    }
    if (in_array($path, ['/403', '/forbidden'], true)) {
        throw new HttpException(403, 'Доступ запрещен.');
    }
    if (!isset($routes[$path])) {
        throw new HttpException(404, 'Страница не найдена.');
    }
    sendResponse($routes[$path]());
}
