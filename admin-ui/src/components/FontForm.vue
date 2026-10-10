<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { cloneFont, families, licenses, parseTags, validateFont } from '@/domain/font'
import FontFacesEditor from './FontFacesEditor.vue'
import FontPreview from './FontPreview.vue'

const props = defineProps({
  font: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  saveError: { type: String, default: '' },
  fieldErrors: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['save'])
// Редактируемая копия отделена от записи в общем хранилище состояния: отмена не меняет каталог.
const draft = ref(cloneFont(props.font))
const tagsText = ref(draft.value.tags.join(', '))
const validationErrors = ref({})
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const fileErrors = ref([])
const summary = ref(null)

async function focusErrors() {
  await nextTick()
  summary.value?.focus()
  summary.value?.scrollIntoView({ block: 'start', behavior: 'smooth' })
}

function receiveFileErrors(messages) {
  fileErrors.value = messages
  if (messages.length) focusErrors()
}

function submit() {
  if (props.disabled) return
  const record = cloneFont(draft.value)
  for (const field of ['name', 'author', 'licenseText', 'sourceUrl', 'previewText'])
    record[field] = record[field].trim()
  record.tags = parseTags(tagsText.value)
  validationErrors.value = validateFont(record)
  fileErrors.value = []
  if (Object.keys(validationErrors.value).length) {
    focusErrors()
    return
  }
  emit('save', record)
}
watch(
  () => props.saveError,
  (value) => {
    if (value) focusErrors()
  },
)
</script>

<template>
  <form id="font-form" novalidate @submit.prevent="submit">
    <div
      v-if="Object.keys(errors).length || fileErrors.length || saveError"
      ref="summary"
      class="form-status error-summary"
      role="alert"
      tabindex="-1"
    >
      <p>Проверьте заполнение формы</p>
      <ul>
        <li v-for="(message, key) in errors" :key="key">{{ message }}</li>
        <li v-for="message in fileErrors" :key="message">{{ message }}</li>
        <li v-if="saveError">{{ saveError }}</li>
      </ul>
    </div>
    <fieldset class="form-fields" :disabled="disabled">
      <div class="editor-layout">
        <div class="editor-sections">
          <section class="section-card" aria-labelledby="basic-title">
            <div class="section-title">
              <span class="step-number" aria-hidden="true">1</span>
              <h2 id="basic-title">Основные сведения</h2>
            </div>
            <div class="form-grid">
              <div>
                <label for="name">Название *</label
                ><input
                  id="name"
                  v-model="draft.name"
                  required
                  maxlength="150"
                  autocomplete="off"
                  placeholder="Например, Lora"
                  :aria-invalid="Boolean(errors.name)"
                />
              </div>
              <div>
                <label for="author">Автор или студия *</label
                ><input
                  id="author"
                  v-model="draft.author"
                  required
                  maxlength="250"
                  placeholder="Имя автора или студии"
                  :aria-invalid="Boolean(errors.author)"
                />
              </div>
              <div>
                <label for="family">Семейство *</label
                ><select id="family" v-model="draft.family" :aria-invalid="Boolean(errors.family)">
                  <option v-for="(label, value) in families" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
              </div>
              <div>
                <label for="dateAdded">Дата добавления *</label
                ><input
                  id="dateAdded"
                  v-model="draft.dateAdded"
                  type="date"
                  required
                  :aria-invalid="Boolean(errors.dateAdded)"
                />
              </div>
              <div class="full-width">
                <label for="tags">Теги</label
                ><input
                  id="tags"
                  v-model="tagsText"
                  placeholder="с засечками, читабельный"
                  :aria-invalid="Boolean(errors.tags)"
                /><span class="field-hint"
                  >Разделяйте теги запятыми. Повторы и пустые элементы будут убраны.</span
                >
              </div>
              <label class="checkbox-field full-width" for="supportsCyrillic">
                <input id="supportsCyrillic" v-model="draft.supportsCyrillic" type="checkbox" />
                Поддерживает кириллицу
              </label>
            </div>
          </section>
          <section class="section-card" aria-labelledby="license-title">
            <div class="section-title">
              <span class="step-number" aria-hidden="true">2</span>
              <h2 id="license-title">Лицензия и источник</h2>
            </div>
            <div class="form-grid">
              <div>
                <label for="license">Лицензия *</label
                ><select
                  id="license"
                  v-model="draft.license"
                  :aria-invalid="Boolean(errors.license)"
                >
                  <option v-for="(label, value) in licenses" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
              </div>
              <div>
                <label for="sourceUrl">Ссылка на источник *</label
                ><input
                  id="sourceUrl"
                  v-model="draft.sourceUrl"
                  type="url"
                  maxlength="2000"
                  required
                  placeholder="https://…"
                  :aria-invalid="Boolean(errors.sourceUrl)"
                />
              </div>
              <div class="full-width">
                <label for="licenseText">Текст лицензии *</label
                ><textarea
                  id="licenseText"
                  v-model="draft.licenseText"
                  rows="3"
                  maxlength="100000"
                  required
                  :aria-invalid="Boolean(errors.licenseText)"
                />
              </div>
            </div>
          </section>
          <FontFacesEditor
            v-model="draft.faces"
            :errors="errors"
            @file-errors="receiveFileErrors"
          />
        </div>
        <FontPreview v-model:text="draft.previewText" :font="draft" />
      </div>
      <div class="form-actions">
        <span class="muted">* Обязательные поля</span>
        <div>
          <RouterLink class="btn" to="/fonts" title="К списку шрифтов">Отмена</RouterLink
          ><button class="btn btn-primary" type="submit" :disabled="disabled">
            {{
              disabled ? 'Сохранение…' : font.id === null ? 'Добавить шрифт' : 'Сохранить изменения'
            }}
          </button>
        </div>
      </div>
    </fieldset>
  </form>
</template>
