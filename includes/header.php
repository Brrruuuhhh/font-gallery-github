<!-- Общий шаблон верхней части сайта. Подключается на всех страницах через renderHeader(). -->
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <!-- НФТ-3: настройка ширины области просмотра обеспечивает правильное отображение на мобильных устройствах. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- НФТ-6: обработчик подставляет для каждой страницы собственные заголовок title и описание description. -->
    <title>{PAGE_TITLE}</title>
    <meta name="description" content="{PAGE_DESCRIPTION}">
    <link rel="canonical" href="{CANONICAL_URL}">
    <!-- НФТ-20: Open Graph для корректного отображения ссылки в социальных сетях. -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Галерея Шрифтов">
    <meta property="og:title" content="{OG_TITLE}">
    <meta property="og:description" content="{OG_DESCRIPTION}">
    <meta property="og:url" content="{OG_URL}">
    <meta property="og:image" content="{OG_IMAGE}">
    <!-- НФТ-18: значок сайта в предпочтительном формате SVG. -->
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="{STYLESHEET_URL}">
    {FONT_STYLESHEET}
    <!-- НФТ-19: базовая микроразметка Schema.org в формате JSON-LD. -->
    <script type="application/ld+json">
{SCHEMA_JSON}
    </script>
</head>
<body class="flex-col min-h-screen">
    <!-- НФТ-7: семантическая шапка и навигация сайта. -->
    <header>
        <div class="header-inner">
            <a href="/" class="logo" title="Галерея Шрифтов">
                <span class="logo-mark" aria-hidden="true">Aa</span>
                <span>Галерея Шрифтов</span>
            </a>

            <!-- НФТ-8/9/13: обычные текстовые ссылки с подсказкой title обеспечивают навигацию без JavaScript. -->
            <nav class="nav-desktop">
                <a href="/" title="Главная страница">Главная</a>
                <a href="/catalog" title="Каталог шрифтов">Каталог</a>
                <a href="/comparison" title="Сравнение шрифтов">Сравнение</a>
                <a href="/about" title="О сайте">О сайте</a>
            </nav>

            <!-- НФТ-3/13: кнопка мобильного меню; ниже расположена его текстовая часть со ссылками. -->
            <button class="menu-toggle" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <!-- НФТ-13: мобильное меню содержит обычные ссылки, доступные и без обработчиков JavaScript. -->
        <nav class="nav-mobile" id="mobile-menu">
            <a href="/" title="Главная страница">Главная</a>
            <a href="/catalog" title="Каталог шрифтов">Каталог</a>
            <a href="/comparison" title="Сравнение шрифтов">Сравнение</a>
            <a href="/about" title="О сайте">О сайте</a>
        </nav>
    </header>
