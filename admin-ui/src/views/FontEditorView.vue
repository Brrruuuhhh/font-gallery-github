<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useFontsStore } from '@/stores/fonts'
import { useAuthStore } from '@/stores/auth'
import { useFontRecord } from '@/composables/useFontRecord'
import { emptyFont } from '@/domain/font'
import FontForm from '@/components/FontForm.vue'
import EmptyState from '@/components/EmptyState.vue'

const route = useRoute()
const router = useRouter()
const store = useFontsStore()
const auth = useAuthStore()
const editing = computed(() => route.name === 'font-edit')
const { record, loading, error, notFound, load } = useFontRecord(() =>
  editing.value ? route.params.id : null,
)
const font = computed(() => (editing.value ? record.value : emptyFont()))
const saveError = ref('')
const fieldErrors = ref({})
const saving = ref(false)
watch(
  () => route.fullPath,
  () => {
    saveError.value = ''
    fieldErrors.value = {}
  },
)
onBeforeRouteLeave(() => !saving.value || !auth.authenticated)

async function save(value) {
  if (saving.value) return
  saveError.value = ''
  fieldErrors.value = {}
  saving.value = true
  try {
    await store.saveFont(value)
    saving.value = false
    await router.push('/fonts')
  } catch (cause) {
    saveError.value = cause.message
    fieldErrors.value = cause.fields ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <p v-if="loading" class="loading-state" role="status">Загрузка шрифта…</p>
  <div v-else-if="error" class="form-status error-summary" role="alert">
    <p>{{ error }}</p>
    <button class="btn" type="button" @click="load">Повторить</button>
    <RouterLink class="btn" to="/fonts" title="К списку шрифтов">К списку</RouterLink>
  </div>
  <template v-else-if="font">
    <div class="page-heading">
      <div>
        <p class="eyebrow">Управление каталогом</p>
        <h1>{{ editing ? `Редактирование: ${font.name}` : 'Добавить шрифт' }}</h1>
        <p>
          {{
            editing
              ? 'Измените сведения, начертания и файлы шрифта.'
              : 'Заполните сведения и выберите файлы для нового шрифта.'
          }}
        </p>
      </div>
      <RouterLink class="btn" to="/fonts" title="К списку шрифтов">← К списку</RouterLink>
    </div>
    <FontForm
      :key="route.fullPath"
      :font="font"
      :disabled="saving"
      :save-error="saveError"
      :field-errors="fieldErrors"
      @save="save"
    />
  </template>
  <EmptyState
    v-else-if="notFound"
    title="Шрифт не найден"
    description="Шрифт не найден. Возможно, он был удалён."
  >
    <RouterLink class="btn" to="/fonts" title="К списку шрифтов">К списку шрифтов</RouterLink>
  </EmptyState>
</template>
