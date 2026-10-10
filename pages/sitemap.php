{HEADER}

<!-- НФТ-12: хлебные крошки на внутренней странице карты сайта. -->
{BREADCRUMBS}

<!-- НФТ-27: HTML-карта сайта содержит основные разделы и динамические ссылки на шрифты. -->
<main class="page-content">
    <div class="container page-padding narrow-page">
        <h1>Карта сайта</h1>

        <div class="sitemap-content card">
            <p>
                Полная структура сайта "Галерея Шрифтов" со ссылками на все доступные разделы.
            </p>

            <div class="space-y-8">
                <!-- НФТ-8/9: внутренние ссылки относительные и имеют атрибут title. -->
                <div class="sitemap-section">
                    <h2>Основные разделы</h2>
                    <ul class="sitemap-list">
                        <li><a href="/" title="Главная страница">→ Главная страница</a></li>
                        <li><a href="/catalog" title="Каталог шрифтов">→ Каталог шрифтов</a></li>
                        <li><a href="/comparison" title="Сравнение шрифтов">→ Сравнение шрифтов</a></li>
                        <li><a href="/about" title="О сайте">→ О сайте</a></li>
                    </ul>
                </div>

                <!-- НФТ-13: служебная навигация продублирована обычными текстовыми ссылками. -->
                <div class="sitemap-section">
                    <h2>Информация</h2>
                    <ul class="sitemap-list">
                        <li><a href="/sitemap" title="Карта сайта">→ Карта сайта</a></li>
                        <li><a href="/about#privacy" title="Политика конфиденциальности">→ Политика конфиденциальности</a></li>
                        <li><a href="mailto:info@fontgallery.ru" title="Написать на почту">→ Контакты</a></li>
                    </ul>
                </div>

                <!-- НФТ-27: обработчик страницы формирует список шрифтов из модели данных. -->
                <div class="sitemap-section">
                    <h2>Шрифты каталога</h2>
                    <ul class="sitemap-list">
{FONT_LINKS}
                    </ul>
                </div>
            </div>

            <!-- Сводка функций сайта помогает показать соответствие функциональному ТЗ. -->
            <div class="sitemap-divider">
                <h3>Возможности сайта</h3>
                <ul class="sitemap-features">
                    <li>
                        <span>•</span>
                        <span>Каталог шрифтов различных семейств</span>
                    </li>
                    <li>
                        <span>•</span>
                        <span>Интерактивный предпросмотр с настройкой текста и размера</span>
                    </li>
                    <li>
                        <span>•</span>
                        <span>Инструмент сравнения до 5 шрифтов одновременно</span>
                    </li>
                    <li>
                        <span>•</span>
                        <span>Автоматическая генерация CSS-кода (@font-face)</span>
                    </li>
                    <li>
                        <span>•</span>
                        <span>Фильтрация и поиск по различным критериям</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</main>

{FOOTER}
