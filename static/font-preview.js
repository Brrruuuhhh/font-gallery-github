const cyrillic = /\p{Script=Cyrillic}/u;
const fontLoads = new WeakMap();

export function hasCyrillic(text) {
  return cyrillic.test(text);
}

export function updateSizeSlider(slider) {
  const progress = (Number(slider.value) - Number(slider.min)) / (Number(slider.max) - Number(slider.min)) * 100;
  slider.style.setProperty('--range-progress', progress + '%');
}

export function previewStatus(text, supportsCyrillic, loadFailed) {
  if (loadFailed) return 'Не удалось загрузить шрифт. Обновите страницу.';
  return !supportsCyrillic && hasCyrillic(text)
    ? 'Кириллица отсутствует в этом шрифте: эти символы отображаются шрифтом подстановки.' : '';
}

/** Сохраняем результат для каждого элемента предпросмотра отдельно; новые карточки каталога проверяют свои файлы шрифтов. */
export function loadPreviewFont(preview) {
  const style = getComputedStyle(preview);
  const descriptor = style.fontStyle + ' ' + style.fontWeight + ' 24px ' + style.fontFamily;
  if (fontLoads.get(preview)?.descriptor !== descriptor) {
    fontLoads.set(preview, { descriptor, loaded: document.fonts.load(descriptor, 'Aa')
      .then((faces) => faces.length > 0).catch(() => false) });
  }
  return fontLoads.get(preview).loaded;
}
