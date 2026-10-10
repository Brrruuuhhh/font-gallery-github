<script setup>
import { markRaw } from 'vue'
import { styles, validateFile, formatSize } from '@/domain/font'

const faces = defineModel({ type: Array, required: true })
defineProps({ errors: { type: Object, default: () => ({}) } })
const emit = defineEmits(['file-errors'])

function addFace() {
  const used = new Set(faces.value.map((face) => `${face.weight}-${face.style}`))
  for (const style of Object.keys(styles)) {
    for (let weight = 100; weight <= 900; weight += 100) {
      if (!used.has(`${weight}-${style}`)) {
        faces.value.push({ weight, style, files: [] })
        return
      }
    }
  }
}
function addFiles(event, face) {
  const errors = []
  for (const file of event.target.files) {
    const format = file.name.split('.').pop().toLowerCase()
    const error = validateFile(file)
    if (error) errors.push(error)
    else if (face.files.some((item) => item.format === format))
      errors.push(`Файл ${format.toUpperCase()} уже есть в начертании. Уберите его перед заменой.`)
    else face.files.push({ name: file.name, format, size: file.size, blob: markRaw(file) })
  }
  event.target.value = ''
  emit('file-errors', errors)
}
</script>

<template>
  <section class="section-card" aria-labelledby="files-title">
    <div class="section-title">
      <span class="step-number" aria-hidden="true">3</span>
      <h2 id="files-title">Начертания и файлы</h2>
    </div>
    <section
      v-for="(face, index) in faces"
      :key="index"
      class="face-card"
      :aria-label="`Начертание ${index + 1}`"
    >
      <div class="face-header">
        <h3>Начертание {{ index + 1 }}</h3>
        <button
          v-if="faces.length > 1"
          class="btn-text danger-link"
          type="button"
          :aria-label="`Убрать начертание ${index + 1}`"
          @click="faces.splice(index, 1)"
        >
          Убрать
        </button>
      </div>
      <div class="face-controls">
        <div>
          <label :for="`weight-${index}`">Насыщенность</label
          ><select
            :id="`weight-${index}`"
            v-model.number="face.weight"
            :aria-invalid="Boolean(errors[`face-${index}`])"
          >
            <option v-for="step in 9" :key="step" :value="step * 100">
              {{ step * 100 }}{{ step === 4 ? ' · Обычная' : step === 7 ? ' · Жирная' : '' }}
            </option>
          </select>
        </div>
        <div>
          <label :for="`style-${index}`">Стиль</label
          ><select
            :id="`style-${index}`"
            v-model="face.style"
            :aria-invalid="Boolean(errors[`face-${index}`])"
          >
            <option v-for="(label, value) in styles" :key="value" :value="value">
              {{ label }}
            </option>
          </select>
        </div>
      </div>
      <ul class="file-list">
        <li
          v-for="(file, fileIndex) in face.files"
          :key="`${file.format}-${file.name}`"
          class="file-item"
        >
          <span class="file-name"
            >{{ file.name
            }}<span class="file-meta"
              >{{ file.format.toUpperCase() }} · {{ formatSize(file.size) }}</span
            ></span
          >
          <button
            class="btn-text danger-link"
            type="button"
            :aria-label="`Убрать файл ${file.name}`"
            @click="face.files.splice(fileIndex, 1)"
          >
            Убрать
          </button>
        </li>
      </ul>
      <label class="upload-label" :for="`files-${index}`"
        >Выбрать файлы
        <input
          :id="`files-${index}`"
          type="file"
          multiple
          accept=".ttf,.otf,.woff,.woff2"
          @change="addFiles($event, face)"
        />
        <span class="field-hint">TTF, OTF, WOFF, WOFF2 · до 20 МБ на файл.</span>
      </label>
      <p v-if="errors[`face-${index}`]" class="danger-link">{{ errors[`face-${index}`] }}</p>
    </section>
    <button class="btn btn-small" type="button" :disabled="faces.length >= 27" @click="addFace">
      ＋ Добавить начертание
    </button>
    <p class="field-hint">
      Каждое начертание задаётся насыщенностью и стилем. Можно выбрать по одному файлу каждого
      формата.
    </p>
  </section>
</template>
