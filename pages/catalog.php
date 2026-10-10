{HEADER}

<!-- НФТ-12: хлебные крошки на внутренней странице. -->
{BREADCRUMBS}

<!-- Шаблон страницы каталога. Обработчик страницы передаёт данные карточек в предусмотренное место шаблона. -->
<main class="page-content">
    <div class="container page-padding">
        <h1>Каталог шрифтов</h1>

        <div class="catalog-grid">
            <!-- ФТ-2: фильтры по семейству, тегам и лицензии вынесены в шаблон боковой панели. -->
            {SIDEBAR_FILTERS}

            <!-- ФТ-1/4: карточки содержат предпросмотр, размер, CSS-код и ссылки на файлы. -->
            <div id="catalog-results">
                <!-- Дополнительная логика каталога: счетчик результатов и сортировка. -->
                <div class="sort-bar">
                    <div class="sort-bar-count">
                        Найдено шрифтов: <strong>{FONTS_COUNT}</strong>
                    </div>
                    <div class="sort-bar-controls">
                        <label for="catalog-sort">Сортировка:</label>
                        <select id="catalog-sort" name="sort" form="catalog-form" class="form-control sort-select">
                            <option value="dateAdded-desc"{SORT_DATEADDED_DESC}>Новые</option>
                            <option value="dateAdded-asc"{SORT_DATEADDED_ASC}>Старые</option>
                            <option value="name-asc"{SORT_NAME_ASC}>А-Я</option>
                            <option value="name-desc"{SORT_NAME_DESC}>Я-А</option>
                            <option value="downloads-desc"{SORT_DOWNLOADS_DESC}>Популярные</option>
                            <option value="downloads-asc"{SORT_DOWNLOADS_ASC}>Менее популярные</option>
                        </select>
                    </div>
                </div>

                <div class="fonts-grid" id="fonts-grid">
                    {FONTS_CARDS}
                </div>

                <div class="card text-center {EMPTY_CLASS}" id="catalog-empty">
                    <p class="text-muted">Шрифты не найдены. Измените параметры фильтрации.</p>
                </div>

                <!-- НФТ-11: постраничный вывод каталога. Кнопками управляет static/catalog.js. -->
                <nav class="pagination" id="catalog-pagination" aria-label="Страницы каталога">
                    {PAGINATION}
                </nav>
            </div>
        </div>
    </div>
</main>

{FOOTER}
