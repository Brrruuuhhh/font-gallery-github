{HEADER}

<!-- НФТ-12: хлебные крошки на внутренней информационной странице. -->
{BREADCRUMBS}

<!-- Шаблон информационной страницы «О сайте». -->
<main class="page-content">
    <div class="container page-padding narrow-page">
        <h1>О сайте</h1>

        <div class="about-intro card">
            <h2>Галерея Шрифтов</h2>
            <p>
                Веб-сайт "Галерея Шрифтов" — это тематический каталог шрифтов, созданный для дизайнеров,
                разработчиков и всех, кто работает с типографикой. Наша платформа предоставляет удобные
                инструменты для поиска, предпросмотра и сравнения шрифтов.
            </p>
        </div>

        <!-- НФТ-14/15: у всех изображений SVG задан текстовый заменитель alt и запасное изображение placeholder.svg. -->
        <div class="about-sections">
            <div class="about-card">
                <div class="feature-icon feature-icon-sm">
                    <img src="/static/icons/catalog.svg" alt="Каталог шрифтов" data-fallback="/static/icons/placeholder.svg">
                </div>
                <h3>Каталог</h3>
                <p>
                    Шрифты различных семейств с подробной информацией о каждом
                </p>
            </div>

            <div class="about-card">
                <div class="feature-icon feature-icon-sm">
                    <img src="/static/icons/scale.svg" alt="Сравнение шрифтов" data-fallback="/static/icons/placeholder.svg">
                </div>
                <h3>Сравнение</h3>
                <p>
                    Несколько шрифтов на одном тексте для выбора подходящего начертания
                </p>
            </div>

            <div class="about-card">
                <div class="feature-icon feature-icon-sm">
                    <img src="/static/icons/quality.svg" alt="Качество" data-fallback="/static/icons/placeholder.svg">
                </div>
                <h3>Качество</h3>
                <p>
                    Только качественные шрифты с лицензией на использование
                </p>
            </div>
        </div>

        <div class="about-features card">
            <h2>Основные возможности</h2>
            <ul class="features-list">
                <li><span>✓</span>Каталог шрифтов с пагинацией (10 шрифтов на страницу)</li>
                <li><span>✓</span>Интерактивный предпросмотр с настраиваемым текстом и размером (12-96px)</li>
                <li><span>✓</span>Инструмент сравнения до 5 шрифтов одновременно</li>
                <li><span>✓</span>Автоматическая генерация CSS-кода (@font-face)</li>
                <li><span>✓</span>Фильтрация по семейству, тегам, лицензии</li>
                <li><span>✓</span>Сортировка по дате, названию, популярности</li>
                <li><span>✓</span>Поиск по названию, автору и тегам</li>
                <li><span>✓</span>Управление каталогом через защищённую административную панель</li>
            </ul>
        </div>

        <div class="about-guide card">
            <h2>Как выбрать шрифт</h2>
            <div class="about-guide-grid">
                <div class="about-guide-item">
                    <div class="about-guide-title">Начертание</div>
                    <div class="about-guide-description">Сравните шрифты на своём тексте</div>
                </div>
                <div class="about-guide-item">
                    <div class="about-guide-title">Языки</div>
                    <div class="about-guide-description">Проверьте поддержку кириллицы</div>
                </div>
                <div class="about-guide-item">
                    <div class="about-guide-title">Лицензия</div>
                    <div class="about-guide-description">Изучите условия использования</div>
                </div>
                <div class="about-guide-item">
                    <div class="about-guide-title">Подключение</div>
                    <div class="about-guide-description">Скачайте файлы или скопируйте CSS</div>
                </div>
            </div>
        </div>

        <section class="about-intro card" id="privacy">
            <h2>Политика конфиденциальности</h2>
            <p>
                Для просмотра и скачивания шрифтов регистрация не требуется. Параметры поиска и фильтров
                передаются серверу для получения результатов. Выбор шрифтов для сравнения хранится
                в текущей вкладке браузера. Скачивание увеличивает общий счётчик шрифта.
                Управление каталогом доступно только администратору; внесённые им изменения сохраняются на сервере.
            </p>
        </section>
    </div>
</main>

{FOOTER}
