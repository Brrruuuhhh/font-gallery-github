import { ref } from 'vue'
import { defineStore } from 'pinia'
import { getFonts, saveFont as persistFont, deleteFont } from '@/services/font-service'
import { pageSize } from '@/domain/font'

export const useFontsStore = defineStore('fonts', () => {
  const fonts = ref([])
  const search = ref('')
  const family = ref('')
  const license = ref('')
  const sort = ref('name')
  const page = ref(1)
  const total = ref(0)
  const catalogTotal = ref(0)
  const pages = ref(1)
  const loading = ref(false)
  const loaded = ref(false)
  const error = ref('')
  const notice = ref('')
  let controller
  let request = 0
  let session = 0

  async function loadFonts() {
    controller?.abort()
    controller = new AbortController()
    const current = ++request
    loading.value = true
    error.value = ''
    try {
      const result = await getFonts(
        {
          q: search.value.trim(),
          family: family.value,
          license: license.value,
          sort: sort.value === 'date' ? 'dateAdded-desc' : 'name-asc',
          page: page.value,
          perPage: pageSize,
        },
        controller.signal,
      )
      if (current !== request) return
      fonts.value = result.data
      total.value = result.pagination.total
      catalogTotal.value = result.pagination.catalogTotal
      pages.value = result.pagination.pages
      page.value = result.pagination.page
      loaded.value = true
    } catch (cause) {
      if (current === request && cause.code !== 'ERR_CANCELED') error.value = cause.message
    } finally {
      if (current === request) loading.value = false
    }
  }

  function cancelList() {
    request++
    controller?.abort()
    loading.value = false
  }

  async function saveFont(record) {
    const currentSession = session
    const saved = await persistFont(record)
    if (currentSession === session)
      notice.value = record.id === null ? 'Шрифт добавлен.' : 'Изменения сохранены.'
    return saved
  }

  async function removeFont(id) {
    const currentSession = session
    await deleteFont(id)
    if (currentSession === session) notice.value = 'Шрифт удалён.'
  }

  function clearFilters() {
    search.value = family.value = license.value = ''
    sort.value = 'name'
    page.value = 1
  }

  function reset() {
    session++
    cancelList()
    fonts.value = []
    total.value = catalogTotal.value = 0
    pages.value = 1
    loaded.value = false
    error.value = notice.value = ''
    clearFilters()
  }

  return {
    fonts,
    search,
    family,
    license,
    sort,
    page,
    total,
    catalogTotal,
    pages,
    loading,
    loaded,
    error,
    notice,
    loadFonts,
    cancelList,
    saveFont,
    removeFont,
    clearFilters,
    reset,
  }
})
