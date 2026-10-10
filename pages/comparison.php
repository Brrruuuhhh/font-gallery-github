{HEADER}

<!-- НФТ-12: хлебные крошки на внутренней странице сравнения. -->
{BREADCRUMBS}

<!-- Шаблон страницы сравнения шрифтов. Основное требование — ФТ-3. -->
<main class="page-content">
    <div class="container page-padding">
        <div class="comparison-header">
            <div>
                <h1>Сравнение шрифтов <span id="comparison-count">0/5</span></h1>
                <p class="comparison-subtitle">Сравните начертания на одном тексте и размере.</p>
            </div>
            <button class="btn comparison-clear" type="button" id="comparison-clear" disabled>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"></path>
                </svg>
                Очистить всё
            </button>
        </div>

        <!-- ФТ-3: состояние, когда пользователь еще не выбрал шрифты в каталоге. -->
        <div class="empty-state" id="empty-state">
            <div class="empty-icon" aria-hidden="true">
                <img src="/static/icons/scale.svg" alt="" width="64" height="64" data-fallback="/static/icons/placeholder.svg">
            </div>
            <h2 class="not-found-title">Нет шрифтов для сравнения</h2>
            <p>
                Выберите до 5 шрифтов в каталоге для сравнения
            </p>
            <a href="/catalog" class="btn btn-primary" title="Перейти в каталог шрифтов">Перейти в каталог</a>
        </div>

        <!-- ФТ-1/3: общие настройки текста и размера для всех сравниваемых шрифтов. -->
        <div id="comparison-wrapper" class="hidden">
            <div class="comparison-controls">
                <div class="control-group">
                    <label class="control-label" for="comparison-text">Общий текст для предпросмотра:</label>
                    <input type="text" class="form-control" id="comparison-text" maxlength="500" placeholder="Введите текст для всех шрифтов..."
                        value="Быстрая коричневая лиса прыгает через ленивую собаку" aria-describedby="comparison-language-note">
                    <p class="comparison-language-note" id="comparison-language-note" role="status" aria-live="polite"></p>
                </div>

                <div class="control-group">
                    <label class="control-label" for="comparison-size">Размер: <span id="size-label">32</span> px</label>
                    <input type="range" class="size-slider preview-size-slider" id="comparison-size" min="12" max="96" value="32">
                    <div class="size-buttons">
                        <button class="size-btn" type="button" data-comparison-size="12">12px</button>
                        <button class="size-btn" type="button" data-comparison-size="16">16px</button>
                        <button class="size-btn" type="button" data-comparison-size="24">24px</button>
                        <button class="size-btn" type="button" data-comparison-size="36">36px</button>
                        <button class="size-btn" type="button" data-comparison-size="48">48px</button>
                        <button class="size-btn" type="button" data-comparison-size="72">72px</button>
                        <button class="size-btn" type="button" data-comparison-size="96">96px</button>
                    </div>
                </div>
            </div>

            <!-- ФТ-3: сюда JavaScript добавляет карточки выбранных шрифтов. -->
            <div class="comparison-cards" id="comparison-cards">
                <!-- JavaScript формирует карточки сравнения по идентификаторам выбранных шрифтов. -->
            </div>
        </div>
    </div>
</main>

<script type="application/json" id="fonts-data">{FONTS_DATA}</script>

{FOOTER}
