<!-- Шаблон карточки шрифта. Используется для всех шрифтов из модели данных. -->
<article class="font-card" id="font-{FONT_ID}" data-font-id="{FONT_ID}" data-cyrillic="{CYRILLIC_SUPPORTED}">
    <div class="font-card-header">
        <div>
            <h2 class="font-card-title">{FONT_NAME}</h2>
            <p class="font-card-meta">Автор: {AUTHOR}</p>
        </div>
        <!-- ФТ-3: добавление шрифта в сравнение. Идентификатор сохраняется в хранилище текущей вкладки браузера. -->
        <button class="btn-compare btn-icon" title="Добавить в сравнение" data-compare-id="{FONT_ID}" aria-pressed="false">
            <span class="icon-plus">＋</span>
        </button>
    </div>

    <div class="font-card-section">
        <div class="font-card-badges">
            <span class="badge-family">{FAMILY_DISPLAY}</span>
            <span class="badge-license">{LICENSE}</span>
        </div>
        <div class="font-tags">
            {TAGS_DISPLAY}
        </div>
    </div>

    <!-- ФТ-1: пользователь может менять текст предпросмотра. -->
    <div class="font-card-section">
        <label class="form-label" for="preview-text-{FONT_ID}">Текст для предпросмотра:</label>
        <input type="text" id="preview-text-{FONT_ID}" class="form-control preview-text-input font-{FONT_ID}" maxlength="500" value="{PREVIEW_TEXT}">
    </div>

    <!-- ФТ-1: размер предпросмотра можно менять ползунком и кнопками с готовыми значениями. -->
    <div class="font-card-section">
        <label class="form-label size-label" for="preview-size-{FONT_ID}">Размер: 24px</label>
        <input type="range" id="preview-size-{FONT_ID}" class="font-size-slider preview-size-slider" min="12" max="96" value="24">
        <div class="size-buttons font-card-size-buttons">
            <button class="size-btn">12px</button>
            <button class="size-btn">16px</button>
            <button class="size-btn active">24px</button>
            <button class="size-btn">36px</button>
            <button class="size-btn">48px</button>
            <button class="size-btn">72px</button>
            <button class="size-btn">96px</button>
        </div>
    </div>

    <!-- ФТ-1: блок, в котором отображается текущий текст предпросмотра. -->
    <p class="font-language-support text-sm">{LANGUAGE_SUPPORT}</p>
    <p class="font-preview-status text-sm" role="status" aria-live="polite"></p>
    <div class="font-preview font-{FONT_ID}">
        {PREVIEW_TEXT}
    </div>

    <!-- ФТ-4: готовый CSS-код @font-face для подключения шрифта. -->
    <div class="font-card-section">
        <div class="font-card-css-header">
            <span class="form-label label-inline">CSS код:</span>
            <button class="btn-copy" type="button" data-copy-css>Копировать</button>
        </div>
        <pre class="font-card-css"><code>{CSS_CODE}</code></pre>
    </div>

    <!-- Реальные файлы выдаются PHP из PostgreSQL. -->
    <div class="font-card-section font-card-files">
        <span class="form-label">Скачать отдельный файл:</span>
        <div class="font-file-links">
            {FONT_FILES}
        </div>
    </div>

    <a href="{LICENSE_URL}" class="text-sm" download title="Скачать лицензию шрифта {FONT_NAME}">Текст лицензии</a>
    <div class="font-card-info">
        <span>Добавлен: {DATE_ADDED_FORMATTED}</span>
        <span>Скачиваний: {DOWNLOADS_FORMATTED}</span>
    </div>

    <!-- Предпросмотр и скачивание используют разные маршруты учета. -->
    <a class="font-card-download" href="{DOWNLOAD_URL}" download title="Скачать все файлы, начертания, CSS и лицензию шрифта {FONT_NAME}">Скачать шрифт · ZIP</a>
</article>
