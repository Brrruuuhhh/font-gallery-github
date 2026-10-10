<script setup>
import { ref } from 'vue'
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useFontsStore } from '@/stores/fonts'
import { useAuthStore } from '@/stores/auth'
import { useFontRecord } from '@/composables/useFontRecord'
import { families } from '@/domain/font'
import EmptyState from '@/components/EmptyState.vue'

const route = useRoute()
const router = useRouter()
const store = useFontsStore()
const auth = useAuthStore()
const {
  record: font,
  loading,
  error: loadError,
  notFound,
  load,
} = useFontRecord(() => route.params.id)
const error = ref('')
const removing = ref(false)
onBeforeRouteLeave(() => !removing.value || !auth.authenticated)

async function remove() {
  if (removing.value) return
  error.value = ''
  removing.value = true
  try {
    await store.removeFont(route.params.id)
    removing.value = false
    await router.push('/fonts')
  } catch (cause) {
    error.value = cause.message
  } finally {
    removing.value = false
  }
}
</script>

<template>
  <p v-if="loading" class="loading-state" role="status">Загрузка шрифта…</p>
  <div v-else-if="loadError" class="form-status error-summary" role="alert">
    <p>{{ loadError }}</p>
    <button class="btn" type="button" @click="load">Повторить</button>
    <RouterLink class="btn" to="/fonts" title="К списку шрифтов">К списку</RouterLink>
  </div>
  <section v-else-if="font" class="delete-card" aria-labelledby="delete-title">
    <div class="delete-symbol" aria-hidden="true">×</div>
    <p class="eyebrow">Управление каталогом</p>
    <h1 id="delete-title">Удалить шрифт?</h1>
    <p class="muted">Удалить этот шрифт из каталога?</p>
    <div class="delete-record">
      <strong>{{ font.name }}</strong
      ><span>{{ font.author }}</span>
      <span class="file-meta"
        >{{ families[font.family] }} · {{ font.license }} · {{ font.faces.length }} начерт.</span
      >
    </div>
    <p v-if="error" class="form-status error-summary" role="alert">{{ error }}</p>
    <div class="delete-actions">
      <RouterLink class="btn" to="/fonts" title="К списку шрифтов">Отмена</RouterLink>
      <button class="btn btn-danger" type="button" :disabled="removing" @click="remove">
        {{ removing ? 'Удаление…' : 'Удалить шрифт' }}
      </button>
    </div>
  </section>
  <EmptyState
    v-else-if="notFound"
    title="Шрифт не найден"
    description="Шрифт не найден. Возможно, он уже удалён."
  >
    <RouterLink class="btn" to="/fonts" title="К списку шрифтов">К списку шрифтов</RouterLink>
  </EmptyState>
</template>
