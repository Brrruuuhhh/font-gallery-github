import { test } from 'node:test'
import assert from 'node:assert/strict'
import { emptyFont } from '../src/domain/font.js'
import { fontRequestBody } from '../src/domain/font-payload.js'

test('JSON carries retained IDs without counters, URLs or presentation metadata', () => {
  const font = {
    ...emptyFont(), id: 6, downloads: 999,
    faces: [{ id: 8, weight: 400, style: 'normal', files: [{ id: 12, name: 'font.woff', format: 'woff', size: 500, previewUrl: 'http://example.test/file' }] }],
  }
  const body = fontRequestBody(font)
  assert.deepEqual(body.faces, [{ id: 8, weight: 400, style: 'normal', files: [{ id: 12 }] }])
  assert.equal(body.downloads, undefined)
  assert.equal(body.id, undefined)
  assert.equal(body.supportsCyrillic, false)
})

test('multipart keeps real binary data and references each new file exactly once', async () => {
  const content = new Uint8Array([0, 1, 127, 255])
  const font = { ...emptyFont(), faces: [
    { weight: 400, style: 'normal', files: [{ name: 'font.woff', blob: new Blob([content]) }] },
    { id: 9, weight: 700, style: 'italic', files: [{ id: 23 }, { name: 'font.ttf', blob: new Blob([content]) }] },
  ] }
  const body = fontRequestBody(font)
  assert.ok(body instanceof FormData)
  const payload = JSON.parse(body.get('data'))
  assert.deepEqual(payload.faces[0].files, [{ upload: 'file_0' }])
  assert.deepEqual(payload.faces[1].files, [{ id: 23 }, { upload: 'file_1' }])
  assert.deepEqual(new Uint8Array(await body.get('file_0').arrayBuffer()), content)
  assert.equal(body.get('file_1').name, 'font.ttf')
  assert.deepEqual([...body.keys()], ['file_0', 'file_1', 'data'])
})
