<script setup>
import { computed, ref, watch } from 'vue'
import { families, styles, previewFile } from '@/domain/font'
import FontSample from './FontSample.vue'

const props = defineProps({ font: { type: Object, required: true } })
const text = defineModel('text', { type: String, required: true })
const size = ref(32)
const selectedFace = ref(0)
const face = computed(() => props.font.faces[selectedFace.value] ?? props.font.faces[0])
watch(
  () => props.font.faces.length,
  () => {
    selectedFace.value = 0
  },
)
</script>

<template>
  <aside class="preview-panel" aria-labelledby="preview-title">
    <h2 id="preview-title">Предпросмотр</h2>
    <p class="preview-label">Проверьте, как выглядит шрифт в тексте.</p>
    <label for="previewText">Пример текста *</label>
    <textarea id="previewText" v-model="text" rows="3" maxlength="500" required />
    <label for="preview-face">Начертание</label>
    <select id="preview-face" v-model="selectedFace">
      <option v-for="(item, index) in font.faces" :key="index" :value="index">
        {{ item.weight }} · {{ styles[item.style] }}
      </option>
    </select>
    <div class="preview-canvas">
      <FontSample
        :file="previewFile(face)"
        :weight="face?.weight"
        :font-style="face?.style"
        :text="text || 'Аа Bb 123'"
        :size="size"
        show-status
      />
    </div>
    <label for="preview-size">Размер: {{ size }} px</label>
    <input id="preview-size" v-model.number="size" type="range" min="16" max="72" />
    <div class="preview-meta">
      <span>{{ families[font.family] }}</span
      ><span>{{ font.license }}</span>
    </div>
    <p v-if="font.id !== null" class="preview-note">
      Скачиваний: {{ font.downloads.toLocaleString('ru') }}.
    </p>
  </aside>
</template>
