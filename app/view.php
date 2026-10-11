<?php
declare(strict_types=1);

function escapeHtml(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderTemplate(string $template, array $placeholders = []): string
{
    return strtr($template, $placeholders);
}

function renderPartial(string $name, array $placeholders = []): string
{
    return renderTemplate(file_get_contents(INCLUDES_DIR . '/' . $name . '.php'), $placeholders);
}

function getPageDescription(string $title): string
{
    $descriptions = [
        'Каталог шрифтов' => 'Каталог шрифтов с фильтрацией по семейству, тегам и лицензии, предпросмотром и CSS-кодом.',
        'Сравнение шрифтов' => 'Сравнение выбранных шрифтов с общим текстом и размером предпросмотра.',
        'О сайте' => 'Информация о проекте Галерея Шрифтов и его возможностях.',
        'Карта сайта' => 'Карта сайта Галерея Шрифтов со ссылками на разделы и шрифты.',
        'Доступ запрещен' => 'Страница ошибки 403 сайта Галерея Шрифтов.',
        'Страница не найдена' => 'Страница ошибки 404 сайта Галерея Шрифтов.',
        'Ошибка запроса' => 'Сообщение о некорректном запросе к Галерее Шрифтов.',
        'Сервис временно недоступен' => 'Сообщение о временной недоступности Галереи Шрифтов.',
    ];
    return $descriptions[str_replace(' - ' . SITE_NAME, '', $title)] ?? SITE_DESCRIPTION;
}

function getSchemaJson(string $title, string $description): string
{
    return json_encode([
        '@context' => 'https://schema.org',
        '@type' => str_contains($title, 'Каталог') ? 'CollectionPage' : 'WebSite',
        'name' => SITE_NAME, 'description' => $description, 'url' => getCanonicalUrl(),
        'potentialAction' => [
            '@type' => 'SearchAction', 'target' => siteOrigin() . '/catalog?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}

function renderHeader(string $title, array $fontIds = []): string
{
    $description = getPageDescription($title);
    return renderPartial('header', [
        '{PAGE_TITLE}' => escapeHtml($title), '{PAGE_DESCRIPTION}' => escapeHtml($description),
        '{CANONICAL_URL}' => escapeHtml(getCanonicalUrl()), '{OG_TITLE}' => escapeHtml($title),
        '{OG_DESCRIPTION}' => escapeHtml($description), '{OG_URL}' => escapeHtml(getCanonicalUrl()),
        '{OG_IMAGE}' => escapeHtml(siteOrigin() . '/static/icons/og-preview.svg'),
        '{STYLESHEET_URL}' => escapeHtml(assetUrl('/static/styles.css')),
        '{SCHEMA_JSON}' => getSchemaJson($title, $description),
        '{FONT_STYLESHEET}' => $fontIds ? '<link id="font-stylesheet" rel="stylesheet" href="/fonts.css?ids='
            . escapeHtml(implode(',', $fontIds)) . '">' : '',
    ]);
}

function assetUrl(string $path): string
{
    return $path . '?v=' . filemtime(BASE_DIR . $path);
}

function renderFooter(array $scripts = []): string
{
    $html = '';
    foreach (array_unique(array_merge(['/static/script.js'], $scripts)) as $script) {
        $html .= '<script type="module" src="' . escapeHtml(assetUrl($script)) . '"></script>' . "\n";
    }
    return renderPartial('footer', ['{PAGE_SCRIPTS}' => $html]);
}

function renderBreadcrumbs(array $items = []): string
{
    if (!$items) {
        return '';
    }
    $html = '';
    foreach ($items as $index => $item) {
        if ($index > 0) {
            $html .= '<span class="breadcrumb-separator" aria-hidden="true">›</span>';
        }
        $label = escapeHtml($item['label']);
        $html .= isset($item['url'])
            ? '<span class="breadcrumb-item"><a href="' . escapeHtml($item['url']) . '" title="' . $label . '">' . $label . '</a></span>'
            : '<span class="breadcrumb-item" aria-current="page">' . $label . '</span>';
    }
    return renderPartial('breadcrumbs', ['{BREADCRUMBS_CONTENT}' => $html]);
}

function renderPage(string $template, string $title, array $placeholders = [], array $breadcrumbs = [], array $fontIds = [], array $scripts = []): string
{
    return renderTemplate(file_get_contents(PAGES_DIR . '/' . $template . '.php'), array_merge([
        '{HEADER}' => renderHeader($title, $fontIds), '{FOOTER}' => renderFooter($scripts),
        '{BREADCRUMBS}' => renderBreadcrumbs($breadcrumbs),
    ], $placeholders));
}

function getFamilyDisplay(string $family): string
{
    return [
        'serif' => 'С засечками', 'sans-serif' => 'Гротеск', 'monospace' => 'Моноширинный',
        'display' => 'Декоративный', 'handwriting' => 'Рукописный',
    ][$family] ?? $family;
}

function formatDateDisplay(string $date): string
{
    $parts = explode('-', $date);
    return count($parts) === 3 ? $parts[2] . '.' . $parts[1] . '.' . $parts[0] : $date;
}

function generateTagFilters(array $tags, array $selected = []): string
{
    $html = '';
    $extra = '';
    $extraSelected = false;
    foreach ($tags as $index => $tag) {
        $label = escapeHtml($tag);
        $checked = in_array($tag, $selected, true);
        $item = '<div class="filter-item"><input type="checkbox" id="tag-' . $index
            . '" name="tags[]" value="' . $label . '"'
            . ($checked ? ' checked' : '') . '>'
            . '<label for="tag-' . $index . '">' . $label . '</label></div>';
        if ($index < 5) {
            $html .= $item;
        } else {
            $extra .= $item;
            $extraSelected = $extraSelected || $checked;
        }
    }
    if ($extra !== '') {
        $html .= '<details class="filter-tags-more"' . ($extraSelected ? ' open' : '') . '><summary>'
            . '<span class="filter-tags-show">Ещё ' . (count($tags) - 5) . '</span>'
            . '<span class="filter-tags-hide">Свернуть</span>'
            . '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">'
            . '<path d="m6 9 6 6 6-6"></path></svg></summary>'
            . '<div class="filter-tags-extra">' . $extra . '</div></details>';
    }
    return $html;
}

function renderSidebar(array $criteria, array $tags): string
{
    $hasFilters = $criteria['q'] !== '' || $criteria['family'] !== [] || $criteria['license'] !== [] || $criteria['tags'] !== [];
    $placeholders = [
        '{TAGS_FILTERS}' => generateTagFilters($tags, $criteria['tags']),
        '{SEARCH_VALUE}' => escapeHtml($criteria['q']),
        '{RESET_CLASS}' => $hasFilters ? '' : ' hidden',
        '{ACTIONS_EMPTY_CLASS}' => $hasFilters ? '' : ' is-empty',
    ];
    foreach (['family' => ['serif', 'sans-serif', 'monospace', 'display', 'handwriting'],
        'license' => ['free', 'commercial', 'OFL', 'UFL', 'SIL']] as $key => $values) {
        foreach ($values as $value) {
            $placeholders['{' . strtoupper($key . '_' . str_replace('-', '_', $value)) . '_CHECKED}']
                = in_array($value, $criteria[$key], true) ? ' checked' : '';
        }
    }
    return renderPartial('sidebar-filters', $placeholders);
}

function generateFontFileLinks(array $files): string
{
    $html = '';
    $faces = array_unique(array_map(static fn ($file) => $file['weight'] . '-' . $file['style'], $files));
    foreach ($files as $file) {
        $label = strtoupper($file['format']);
        if (count($faces) > 1) {
            $styles = ['normal' => 'обычное', 'italic' => 'курсив', 'oblique' => 'наклонное'];
            $label .= ' · ' . $file['weight'] . ' · ' . $styles[$file['style']];
        }
        $html .= '<a class="font-file-link" href="' . escapeHtml(fontFileUrl($file, true)) . '" download title="Скачать файл '
            . escapeHtml($label) . '">' . escapeHtml($label) . '</a>';
    }
    return $html;
}

function generateFontCards(array $fonts): string
{
    $html = '';
    foreach ($fonts as $font) {
        $tags = '';
        foreach ($font['tags'] as $tag) {
            $tags .= '<span class="tag">' . escapeHtml($tag) . '</span>';
        }
        $html .= renderPartial('font-card', [
            '{FONT_ID}' => escapeHtml($font['id']), '{FONT_NAME}' => escapeHtml($font['name']),
            '{AUTHOR}' => escapeHtml($font['author']), '{FAMILY_DISPLAY}' => escapeHtml(getFamilyDisplay($font['family'])),
            '{LICENSE}' => escapeHtml($font['license']), '{TAGS_DISPLAY}' => $tags,
            '{PREVIEW_TEXT}' => escapeHtml($font['previewText']), '{CSS_CODE}' => escapeHtml(getFontCssCode($font)),
            '{FONT_FILES}' => generateFontFileLinks($font['files']),
            '{DOWNLOAD_URL}' => '/font-downloads/' . $font['id'],
            '{LICENSE_URL}' => '/font-licenses/' . $font['id'],
            '{LANGUAGE_SUPPORT}' => $font['supportsCyrillic'] ? 'Латиница и кириллица' : 'Латиница; кириллица не поддерживается',
            '{CYRILLIC_SUPPORTED}' => $font['supportsCyrillic'] ? 'true' : 'false',
            '{DATE_ADDED_FORMATTED}' => escapeHtml(formatDateDisplay($font['dateAdded'])),
            '{DOWNLOADS_FORMATTED}' => number_format($font['downloads'], 0, '', ' '),
        ]);
    }
    return $html;
}

function catalogPageUrl(array $criteria, int $page): string
{
    $values = array_filter($criteria, static fn ($value) => $value !== '' && $value !== []);
    $values['page_num'] = $page;
    return '/catalog?' . http_build_query($values);
}

function renderPagination(array $criteria, int $page, int $totalPages): string
{
    $html = renderPaginationDirection($criteria, $page - 1, $totalPages, 'prev');
    $html .= '<div class="pagination-pages">';
    $visiblePages = array_unique(array_merge([1], range(max(1, $page - 2), min($totalPages, $page + 2)), [$totalPages]));
    sort($visiblePages);
    $previous = 0;
    foreach ($visiblePages as $number) {
        if ($previous && $number > $previous + 1) {
            $html .= '<span class="pagination-gap" aria-hidden="true">…</span>';
        }
        $html .= '<a href="' . escapeHtml(catalogPageUrl($criteria, $number)) . '" title="Страница ' . $number . '"'
            . ($number === $page ? ' class="active" aria-current="page"' : '') . '>' . $number . '</a>';
        $previous = $number;
    }
    return $html . '</div>' . renderPaginationDirection($criteria, $page + 1, $totalPages, 'next');
}

function renderPaginationDirection(array $criteria, int $target, int $totalPages, string $direction): string
{
    $label = $direction === 'prev' ? 'Предыдущая' : 'Следующая';
    $arrow = '<span aria-hidden="true">' . ($direction === 'prev' ? '←' : '→') . '</span>';
    $text = '<span class="pagination-label">' . $label . '</span>';
    $content = $direction === 'prev' ? $arrow . $text : $text . $arrow;
    if ($target < 1 || $target > $totalPages) {
        return '<button type="button" class="pagination-direction pagination-disabled" disabled aria-label="'
            . $label . '">' . $content . '</button>';
    }
    return '<a class="pagination-direction" href="' . escapeHtml(catalogPageUrl($criteria, $target))
        . '" rel="' . $direction . '" title="' . $label . ' страница каталога" aria-label="' . $label . '">' . $content . '</a>';
}

function generateSitemapLinks(array $fonts): string
{
    $html = '';
    foreach ($fonts as $font) {
        $id = escapeHtml($font['id']);
        $name = escapeHtml($font['name']);
        $html .= '<li><a href="/catalog?font_id=' . $id . '#font-' . $id . '" title="' . $name . '">→ ' . $name . '</a></li>';
    }
    return $html;
}
