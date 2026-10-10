import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  cloneFont,
  emptyFont,
  parseTags,
  validateFile,
  validateFont,
  maxFileSize,
} from '../src/domain/font.js'

function validFont() {
  return {
    ...emptyFont(),
    name: 'Тестовый шрифт',
    author: 'Автор',
    licenseText: 'Текст лицензии',
    sourceUrl: 'https://example.com/font',
    faces: [
      { weight: 400, style: 'normal', files: [{ name: 'font.woff', format: 'woff', size: 100 }] },
    ],
  }
}

test('форма принимает заполненную запись и удаляет пустые и повторные теги', () => {
  const font = validFont()
  font.tags = parseTags(' текст, , код, текст, ')
  assert.deepEqual(font.tags, ['текст', 'код'])
  assert.deepEqual(validateFont(font), {})
})

test('черновик не изменяет исходные теги и вложенные файлы; Blob сохраняется для предпросмотра', () => {
  const font = validFont()
  font.tags = ['исходный']
  const blob = new Blob(['bytes'])
  font.faces[0].files[0].blob = blob
  const draft = cloneFont(font)
  draft.tags.push('новый')
  draft.faces[0].files[0].name = 'other.woff'
  assert.deepEqual(font.tags, ['исходный'])
  assert.equal(font.faces[0].files[0].name, 'font.woff')
  assert.equal(draft.faces[0].files[0].blob, blob)
})

test('проверяются обязательные поля, длины, дата и протокол ссылки', () => {
  const font = validFont()
  font.name = ' '
  font.author = 'а'.repeat(251)
  font.previewText = 'а'.repeat(501)
  font.licenseText = ''
  font.dateAdded = '2026-02-30'
  font.sourceUrl = 'javascript:alert(1)'
  font.tags = ['а'.repeat(101)]
  assert.deepEqual(
    Object.keys(validateFont(font)).sort(),
    ['name', 'author', 'previewText', 'licenseText', 'dateAdded', 'sourceUrl', 'tags'].sort(),
  )
  font.dateAdded = '2024-02-29'
  assert.equal(validateFont(font).dateAdded, undefined)
})

test('обязательны начертание и файл; повторяющиеся начертания и форматы запрещены', () => {
  const font = validFont()
  font.faces.push({ ...font.faces[0], files: [...font.faces[0].files] })
  assert.match(validateFont(font)['face-1'], /не должны совпадать/)
  font.faces.pop()
  font.faces[0].files.push({ name: 'another.woff', format: 'woff', size: 200 })
  assert.match(validateFont(font)['face-0'], /один файл каждого формата/)
  font.faces[0].files = []
  assert.match(validateFont(font)['face-0'], /хотя бы один файл/)
  font.faces = []
  assert.match(validateFont(font).faces, /от одного до 27 начертаний/)
})

test('разрешены только поддерживаемые файлы с корректным размером и длиной имени', () => {
  assert.equal(validateFile({ name: 'FONT.WOFF2', size: maxFileSize }), '')
  assert.match(validateFile({ name: 'font.woff', size: 0 }), /непустым/)
  assert.match(validateFile({ name: 'font.ttf', size: maxFileSize + 1 }), /20 МБ/)
  assert.match(validateFile({ name: 'font.svg', size: 100 }), /не поддерживается/)
  assert.match(validateFile({ name: 'а'.repeat(150) + '.woff', size: 100 }), /150 символов/)
})
