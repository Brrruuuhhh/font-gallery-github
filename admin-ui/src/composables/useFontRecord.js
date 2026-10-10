import { onScopeDispose, ref, watch } from 'vue'
import { getFont } from '@/services/font-service'

export function useFontRecord(idSource) {
  const record = ref(null)
  const loading = ref(false)
  const error = ref('')
  const notFound = ref(false)
  let controller
  let request = 0

  async function load() {
    controller?.abort()
    controller = new AbortController()
    const current = ++request
    record.value = null
    error.value = ''
    notFound.value = false
    loading.value = false
    const id = idSource()
    if (id === null) return
    if (!/^[1-9]\d{0,14}$/.test(String(id))) {
      notFound.value = true
      return
    }
    loading.value = true
    try {
      const font = await getFont(id, controller.signal)
      if (current === request) record.value = font
    } catch (cause) {
      if (current !== request || cause.code === 'ERR_CANCELED') return
      if (cause.status === 404) notFound.value = true
      else error.value = cause.message
    } finally {
      if (current === request) loading.value = false
    }
  }

  watch(idSource, load, { immediate: true })
  onScopeDispose(() => {
    request++
    controller?.abort()
  })
  return { record, loading, error, notFound, load }
}
