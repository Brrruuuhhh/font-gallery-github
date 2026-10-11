{HEADER}

<!-- Шаблон главной страницы с кратким описанием возможностей проекта. -->
<main class="page-content">
    <!-- Главный экран -->
    <div class="hero">
        <div class="container">
            <h1 class="hero-title">Галерея Шрифтов</h1>
            <p class="hero-subtitle">
                Найдите идеальный шрифт для вашего проекта. Предпросмотр, сравнение и скачивание шрифтов в один клик.
            </p>
            <div class="hero-buttons">
                <a href="/catalog" class="btn btn-primary" title="Открыть каталог шрифтов">
                    Открыть каталог
                    <svg class="hero-button-icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                        <path d="M5 12h14M13 6l6 6-6 6"></path>
                    </svg>
                </a>
                <a href="/comparison" class="btn btn-secondary" title="Перейти к сравнению шрифтов">
                    Сравнить шрифты
                    <svg class="hero-button-icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                        <path d="M12 3v18M7 21h10M3 7h2c2 0 3-1 7-1s5 1 7 1h2M2 16h6L5 7l-3 9ZM16 16h6l-3-9-3 9Z"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- НФТ-14/15: у изображений SVG задан текстовый заменитель alt; JavaScript подставляет placeholder.svg при ошибке загрузки. -->
    <!-- Блок также показывает основные функции: поиск, сравнение, готовый CSS. -->
    <!-- Возможности -->
    <div class="container page-padding">
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">
                    <img src="/static/icons/search.svg" alt="Умный поиск" data-fallback="/static/icons/placeholder.svg">
                </div>
                <h2 class="feature-title">Умный поиск</h2>
                <p>
                    Фильтруйте шрифты по семейству, тегам, лицензии и автору. Быстрый поиск по названию.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <img src="/static/icons/scale.svg" alt="Сравнение шрифтов" data-fallback="/static/icons/placeholder.svg">
                </div>
                <h2 class="feature-title">Сравнение</h2>
                <p>
                    Сравнивайте до 5 шрифтов одновременно с синхронизированным текстом предпросмотра.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <img src="/static/icons/css.svg" alt="Готовый CSS-код" data-fallback="/static/icons/placeholder.svg">
                </div>
                <h2 class="feature-title">Готовый CSS</h2>
                <p>
                    Генерация @font-face кода для быстрой интеграции в ваш проект.
                </p>
            </div>
        </div>
        <!-- Статистика -->
        <div class="stats">
            <div class="stats-grid">
                <div class="stat">
                    <div class="stat-value">{TOTAL_FONTS}</div>
                    <div class="stat-label">Шрифтов в каталоге</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{TOTAL_FAMILIES}</div>
                    <div class="stat-label">Типов шрифтов</div>
                </div>
                <div class="stat">
                    <div class="stat-value">100%</div>
                    <div class="stat-label">Бесплатно</div>
                </div>
            </div>
        </div>
    </div>
</main>

{FOOTER}
