<script setup>
import { computed, onMounted, onScopeDispose, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useFontsStore } from '@/stores/fonts'
import { families, licenses, formatDate, pageSize, previewFile } from '@/domain/font'
import FontSample from '@/components/FontSample.vue'

const store = useFontsStore()
const rangeStart = computed(() => (store.page - 1) * pageSize + 1)
let searchTimer
onMounted(store.loadFonts)
watch(
  () => [store.search, store.family, store.license, store.sort, store.page],
  (values, previous) => {
    if (values.slice(0, 4).some((value, index) => value !== previous[index]) && store.page !== 1) {
      store.page = 1
      return
    }
    clearTimeout(searchTimer)
    searchTimer = setTimeout(store.loadFonts, values[0] !== previous[0] ? 300 : 0)
  },
)
function search() {
  clearTimeout(searchTimer)
  if (store.page !== 1) store.page = 1
  else store.loadFonts()
}
onScopeDispose(() => {
  clearTimeout(searchTimer)
  store.cancelList()
})
</script>

<template>
  <div class="page-heading">
    <div>
      <p class="eyebrow">Управление каталогом</p>
      <h1>Шрифты</h1>
      <p>Добавляйте шрифты и редактируйте сведения о них.</p>
    </div>
    <RouterLink class="btn btn-primary" to="/fonts/create" title="Добавить новый шрифт"
      ><span aria-hidden="true">＋</span> Добавить шрифт</RouterLink
    >
  </div>
  <form class="toolbar" role="search" @submit.prevent="search">
    <div class="search-field">
      <label for="search">Поиск</label
      ><input
        id="search"
        v-model="store.search"
        type="search"
        placeholder="Название, автор или тег"
      />
    </div>
    <div class="filter-field">
      <label for="family-filter">Семейство</label
      ><select id="family-filter" v-model="store.family">
        <option value="">Все семейства</option>
        <option v-for="(label, value) in families" :key="value" :value="value">{{ label }}</option>
      </select>
    </div>
    <div class="filter-field">
      <label for="license-filter">Лицензия</label
      ><select id="license-filter" v-model="store.license">
        <option value="">Все лицензии</option>
        <option v-for="(label, value) in licenses" :key="value" :value="value">{{ label }}</option>
      </select>
    </div>
    <div class="toolbar-actions">
      <button class="btn" type="submit">Найти</button
      ><button
        v-if="store.search || store.family || store.license"
        class="btn"
        type="button"
        @click="store.clearFilters"
      >
        Сбросить
      </button>
    </div>
  </form>
  <div v-if="store.error" class="form-status error-summary" role="alert">
    <p>{{ store.error }}</p>
    <button class="btn" type="button" @click="store.loadFonts">Повторить</button>
  </div>
  <p v-else-if="store.loading" class="loading-state" role="status">Загрузка шрифтов…</p>
  <template v-else-if="store.loaded">
    <section class="table-panel" aria-label="Список шрифтов">
      <div class="table-summary">
        <span aria-live="polite"
          ><strong>{{ store.total }}</strong> из {{ store.catalogTotal }} шрифтов</span
        >
        <label class="sort-label" for="sort"
          >Сортировка
          <select id="sort" v-model="store.sort">
            <option value="name">По названию</option>
            <option value="date">Сначала новые</option>
          </select></label
        >
      </div>
      <div
        v-if="store.fonts.length"
        class="table-scroll"
        tabindex="0"
        role="region"
        aria-label="Таблица шрифтов с горизонтальной прокруткой"
      >
        <table>
          <thead>
            <tr>
              <th scope="col">Шрифт</th>
              <th scope="col">Автор и теги</th>
              <th scope="col">Семейство</th>
              <th scope="col">Лицензия</th>
              <th scope="col">Добавлен</th>
              <th scope="col">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="font in store.fonts" :key="font.id">
              <td>
                <RouterLink
                  class="font-name"
                  :to="`/fonts/${font.id}/edit`"
                  :title="`Редактировать ${font.name}`"
                  >{{ font.name }}</RouterLink
                ><FontSample
                  :file="previewFile(font.faces[0])"
                  :weight="font.faces[0]?.weight"
                  :font-style="font.faces[0]?.style"
                />
              </td>
              <td class="font-author">
                {{ font.author }}
                <div class="tags">
                  <span v-for="tag in font.tags.slice(0, 2)" :key="tag" class="tag">{{ tag }}</span>
                </div>
              </td>
              <td>
                {{ families[font.family]
                }}<span class="file-meta"
                  >{{ font.faces.reduce((sum, face) => sum + face.files.length, 0) }} файла ·
                  {{ font.faces.length }} начерт.</span
                >
              </td>
              <td>
                <span class="tag">{{ font.license }}</span>
              </td>
              <td>{{ formatDate(font.dateAdded) }}</td>
              <td>
                <div class="row-actions">
                  <RouterLink :to="`/fonts/${font.id}/edit`" :title="`Редактировать ${font.name}`"
                    >Изменить</RouterLink
                  ><RouterLink
                    class="danger-link"
                    :to="`/fonts/${font.id}/delete`"
                    :title="`Удалить ${font.name}`"
                    >Удалить</RouterLink
                  >
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="empty-state">
        <div class="empty-symbol" aria-hidden="true">Aa</div>
        <h2>Шрифты не найдены</h2>
        <p class="muted">Измените запрос, сбросьте фильтры или добавьте шрифт.</p>
        <button class="btn" type="button" @click="store.clearFilters">Сбросить фильтры</button>
      </div>
      <div class="pagination">
        <span>{{
          store.total
            ? `${rangeStart}–${Math.min(store.page * pageSize, store.total)} из ${store.total}`
            : '0 результатов'
        }}</span>
        <nav class="page-buttons" aria-label="Страницы списка">
          <button
            v-for="page in store.pages"
            :key="page"
            class="page-button"
            type="button"
            :aria-current="page === store.page ? 'page' : undefined"
            :aria-label="`Страница ${page}`"
            @click="store.page = page"
          >
            {{ page }}
          </button>
        </nav>
      </div>
    </section>
  </template>
</template>
