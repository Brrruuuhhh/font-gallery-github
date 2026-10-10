<script setup>
import { onScopeDispose, ref, watch } from 'vue'

const props = defineProps({
  file: { type: Object, default: null },
  weight: { type: Number, default: 400 },
  fontStyle: { type: String, default: 'normal' },
  text: { type: String, default: 'Аа Bb 123' },
  size: { type: Number, default: 23 },
  showStatus: { type: Boolean, default: false },
})
const fontFamily = ref('inherit')
const status = ref('')
let activeFace = null
let version = 0
const familyName = `GalleryPreview-${crypto.randomUUID()}`

function releaseFace() {
  if (activeFace) document.fonts.delete(activeFace)
  activeFace = null
  fontFamily.value = 'inherit'
}

watch(
  () => [props.file, props.weight, props.fontStyle],
  async () => {
    const current = ++version
    releaseFace()
    if (!props.file || (!props.file.blob && !props.file.previewUrl)) {
      status.value = 'Выберите файл для предпросмотра.'
      return
    }
    status.value = 'Загрузка образца…'
    try {
      const source = props.file.blob
        ? await props.file.blob.arrayBuffer()
        : `url(${JSON.stringify(props.file.previewUrl)})`
      const face = await new FontFace(familyName, source, {
        weight: String(props.weight),
        style: props.fontStyle,
      }).load()
      if (current !== version) return
      document.fonts.add(face)
      activeFace = face
      fontFamily.value = `"${familyName}"`
      status.value = ''
    } catch {
      if (current === version) status.value = 'Не удалось загрузить шрифт. Выберите другой файл.'
    }
  },
  { immediate: true },
)
onScopeDispose(() => {
  version++
  releaseFace()
})
</script>

<template>
  <span
    class="font-sample"
    :style="{ fontFamily, fontSize: `${size}px`, fontWeight: weight, fontStyle }"
    >{{ text }}</span
  >
  <span v-if="showStatus && status" class="preview-status" role="status">{{ status }}</span>
</template>
