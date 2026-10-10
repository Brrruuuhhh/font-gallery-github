export const families = {
  serif: 'С засечками',
  'sans-serif': 'Гротеск',
  monospace: 'Моноширинный',
  display: 'Декоративный',
  handwriting: 'Рукописный',
}
export const licenses = {
  OFL: 'Open Font License',
  UFL: 'Ubuntu Font Licence',
  free: 'Бесплатная',
  commercial: 'Коммерческая',
  SIL: 'SIL',
}
export const styles = { normal: 'Обычный', italic: 'Курсив', oblique: 'Наклонный' }
export const formats = ['ttf', 'otf', 'woff', 'woff2']
export const maxFileSize = 20 * 1024 * 1024
export const pageSize = 5

export function cloneFont(font) {
  return {
    ...font,
    tags: [...font.tags],
    faces: font.faces.map((face) => ({
      ...face,
      files: face.files.map((file) => ({ ...file })),
    })),
  }
}

export function emptyFont() {
  const now = new Date()
  const dateAdded = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
  return {
    id: null,
    name: '',
    author: '',
    family: 'serif',
    license: 'OFL',
    licenseText: '',
    sourceUrl: '',
    tags: [],
    previewText: 'Съешь ещё этих мягких французских булок',
    dateAdded,
    downloads: 0,
    supportsCyrillic: false,
    faces: [{ weight: 400, style: 'normal', files: [] }],
  }
}

export function parseTags(text) {
  return [
    ...new Set(
      text
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean),
    ),
  ]
}

export function validateFile(file) {
  const format = file.name.split('.').pop().toLowerCase()
  if (!formats.includes(format)) return `Формат файла «${file.name}» не поддерживается.`
  if (!Number.isFinite(file.size) || file.size <= 0 || file.size > maxFileSize)
    return `Файл «${file.name}» должен быть непустым и не больше 20 МБ.`
  if (file.name.length > 150) return 'Название файла должно быть не длиннее 150 символов.'
  return ''
}

export function validateFont(font) {
  const errors = {}
  const required = {
    name: ['Название', 150],
    author: ['Автор или студия', 250],
    licenseText: ['Текст лицензии', 100000],
    previewText: ['Пример текста', 500],
  }
  for (const [field, [label, limit]] of Object.entries(required)) {
    if (!font[field].trim()) errors[field] = `Заполните поле «${label}».`
    else if (limit && font[field].length > limit)
      errors[field] = `Поле «${label}»: не больше ${limit} символов.`
  }
  if (!Object.hasOwn(families, font.family)) errors.family = 'Выберите семейство шрифта.'
  if (!Object.hasOwn(licenses, font.license)) errors.license = 'Выберите лицензию.'
  const date = new Date(`${font.dateAdded}T00:00:00Z`)
  if (
    !/^\d{4}-\d{2}-\d{2}$/.test(font.dateAdded) ||
    !Number.isFinite(date.getTime()) ||
    date.toISOString().slice(0, 10) !== font.dateAdded
  ) {
    errors.dateAdded = 'Укажите корректную дату добавления.'
  }
  try {
    if (!['http:', 'https:'].includes(new URL(font.sourceUrl).protocol)) throw new Error()
  } catch {
    errors.sourceUrl = 'Укажите ссылку на источник с http:// или https://.'
  }
  if (font.sourceUrl.length > 2000)
    errors.sourceUrl = 'Ссылка должна быть не длиннее 2000 символов.'
  if (font.tags.length > 32 || font.tags.some((tag) => [...tag].length > 100))
    errors.tags = 'Не больше 32 тегов длиной до 100 символов каждый.'
  if (!font.faces.length || font.faces.length > 27)
    errors.faces = 'Добавьте от одного до 27 начертаний.'
  const descriptors = new Set()
  font.faces.forEach((face, index) => {
    const key = `face-${index}`
    const descriptor = `${face.weight}-${face.style}`
    if (
      !Number.isInteger(face.weight) ||
      face.weight < 100 ||
      face.weight > 900 ||
      face.weight % 100 ||
      !Object.hasOwn(styles, face.style)
    ) {
      errors[key] = `Начертание ${index + 1}: выберите насыщенность и стиль.`
    } else if (descriptors.has(descriptor)) {
      errors[key] = 'Насыщенность и стиль у разных начертаний не должны совпадать.'
    } else if (!face.files.length) {
      errors[key] = `Выберите хотя бы один файл для начертания ${index + 1}.`
    } else {
      const fileError = face.files.map(validateFile).find(Boolean)
      if (fileError) errors[key] = fileError
      else if (new Set(face.files.map((file) => file.format)).size !== face.files.length) {
        errors[key] = `Начертание ${index + 1}: можно выбрать только один файл каждого формата.`
      }
    }
    descriptors.add(descriptor)
  })
  return errors
}

export function previewFile(face) {
  return face?.files.find((file) => file.blob || file.previewUrl) ?? null
}
export function formatSize(size) {
  return `${Math.max(1, Math.round(size / 1024))} КБ`
}
export function formatDate(date) {
  return date.split('-').reverse().join('.')
}
