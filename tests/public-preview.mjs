import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { hasCyrillic, loadPreviewFont, previewStatus } from '../static/font-preview.js';

after(() => {
  delete globalThis.document;
  delete globalThis.getComputedStyle;
});

test('предупреждение учитывает расширенную кириллицу и сохраняет приоритет ошибки загрузки', () => {
  assert.equal(hasCyrillic('Latin 123'), false);
  assert.equal(hasCyrillic('Қазақша'), true);
  assert.equal(previewStatus('Пример', true, false), '');
  assert.equal(previewStatus('Latin', false, false), '');
  assert.match(previewStatus('Ї', false, false), /Кириллица отсутствует/);
  assert.match(previewStatus('Ї', false, true), /Не удалось загрузить/);
});

test('текст и размер не запускают повторную загрузку; новое начертание или карточка проверяются заново', async () => {
  const calls = [];
  globalThis.getComputedStyle = (preview) => preview.style;
  globalThis.document = { fonts: { load: async (descriptor) => {
    calls.push(descriptor);
    return [{}];
  } } };
  const preview = { style: { fontStyle: 'italic', fontWeight: '700', fontFamily: '"GalleryFont6", serif' } };
  assert.equal(await loadPreviewFont(preview), true);
  preview.textContent = 'New text';
  preview.style.fontSize = '96px';
  assert.equal(await loadPreviewFont(preview), true);
  assert.deepEqual(calls, ['italic 700 24px "GalleryFont6", serif']);
  preview.style.fontWeight = '400';
  assert.equal(await loadPreviewFont(preview), true);
  assert.equal(await loadPreviewFont({ style: { ...preview.style } }), true);
  assert.equal(calls.length, 3);
});

test('отказ загрузки и отсутствие начертаний возвращают ошибку без необработанного исключения', async () => {
  globalThis.getComputedStyle = (preview) => preview.style;
  const preview = () => ({ style: { fontStyle: 'normal', fontWeight: '400', fontFamily: '"Missing"' } });
  globalThis.document = { fonts: { load: async () => [] } };
  assert.equal(await loadPreviewFont(preview()), false);
  globalThis.document.fonts.load = async () => { throw new Error('Network failed'); };
  assert.equal(await loadPreviewFont(preview()), false);
});
