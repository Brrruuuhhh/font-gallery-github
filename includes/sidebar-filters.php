<!-- Шаблон боковой панели фильтров каталога. -->
<!-- ФТ-2: фильтрация по семейству, лицензии и тегам. -->
<!-- НФТ-7: дополнительная информация в боковой панели размещена в предназначенном для неё элементе aside. -->
<aside class="sidebar">
    <form method="get" action="/catalog" id="catalog-form">
        <div class="sidebar-fields">
            <h2>Фильтры</h2>

            <!-- Дополнительная функция каталога: поиск по названию, автору и тегам. -->
            <div class="search-box">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="text" class="form-control" id="catalog-search" name="q" value="{SEARCH_VALUE}" maxlength="200" placeholder="Минимум 3 символа" aria-label="Поиск шрифтов">
            </div>

            <!-- ФТ-2: фильтр по семейству шрифта. -->
            <div class="filter-group">
                <h3>Семейство</h3>
                <div class="filter-item">
                    <input type="checkbox" id="serif" value="serif" name="family[]"{FAMILY_SERIF_CHECKED}>
                    <label for="serif">С засечками</label>
                </div>
                <div class="filter-item">
                    <input type="checkbox" id="sans-serif" value="sans-serif" name="family[]"{FAMILY_SANS_SERIF_CHECKED}>
                    <label for="sans-serif">Гротеск</label>
                </div>
                <div class="filter-item">
                    <input type="checkbox" id="monospace" value="monospace" name="family[]"{FAMILY_MONOSPACE_CHECKED}>
                    <label for="monospace">Моноширинный</label>
                </div>
                <div class="filter-item">
                    <input type="checkbox" id="display" value="display" name="family[]"{FAMILY_DISPLAY_CHECKED}>
                    <label for="display">Декоративный</label>
                </div>
                <div class="filter-item">
                    <input type="checkbox" id="handwriting" value="handwriting" name="family[]"{FAMILY_HANDWRITING_CHECKED}>
                    <label for="handwriting">Рукописный</label>
                </div>
            </div>

            <!-- ФТ-2: фильтр по лицензии. -->
            <div class="filter-group">
                <h3>Лицензия</h3>
                <div class="filter-item">
                    <input type="checkbox" id="free" value="free" name="license[]"{LICENSE_FREE_CHECKED}>
                    <label for="free">Бесплатная</label>
                </div>
                <div class="filter-item">
                    <input type="checkbox" id="commercial" value="commercial" name="license[]"{LICENSE_COMMERCIAL_CHECKED}>
                    <label for="commercial">Коммерческая</label>
                </div>
                <div class="filter-item">
                    <input type="checkbox" id="ofl" value="OFL" name="license[]"{LICENSE_OFL_CHECKED}>
                    <label for="ofl">Open Font License</label>
                </div>
                <div class="filter-item">
                    <input type="checkbox" id="sil" value="SIL" name="license[]"{LICENSE_SIL_CHECKED}>
                    <label for="sil">SIL</label>
                </div>
                <div class="filter-item">
                    <input type="checkbox" id="ufl" name="license[]" value="UFL"{LICENSE_UFL_CHECKED}>
                    <label for="ufl">Ubuntu Font Licence</label>
                </div>
            </div>
            <!-- ФТ-2: фильтр по тегам генерируется на сервере из модели. -->
            <div class="filter-group">
                <h3>Теги</h3>
                <div class="filter-tags">
                    {TAGS_FILTERS}
                </div>
            </div>
        </div>

        <div class="sidebar-actions{ACTIONS_EMPTY_CLASS}" id="catalog-actions">
            <noscript>
                <button type="submit" class="btn btn-primary sidebar-reset">Применить фильтры</button>
            </noscript>
            <a href="/catalog" class="btn btn-secondary sidebar-reset{RESET_CLASS}" id="catalog-reset" title="Сбросить фильтры">Сбросить фильтры</a>
            <p id="catalog-status" class="text-sm" role="status" aria-live="polite"></p>
            <a class="btn btn-secondary sidebar-reset hidden" id="catalog-retry" href="/catalog" title="Повторить загрузку каталога">Повторить загрузку</a>
        </div>
    </form>
</aside>
