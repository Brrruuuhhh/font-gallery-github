import { hasCyrillic, loadPreviewFont, previewStatus, updateSizeSlider } from './font-preview.js';

const storageKey = 'comparisonFonts';
const dataElement = document.getElementById('fonts-data');
const fonts = dataElement ? JSON.parse(dataElement.textContent) : null;
let previousSelection = null;

function readSelection() {
  try {
    const stored = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
    if (!Array.isArray(stored)) return [];
    return [...new Set(stored.filter((id) => typeof id === 'string' && /^[1-9][0-9]{0,8}$/.test(id)))].slice(0, 5);
  } catch {
    return [];
  }
}

function saveSelection(ids) {
  try {
    sessionStorage.setItem(storageKey, JSON.stringify(ids));
    return true;
  } catch {
    alert('Браузер не разрешает сохранить выбор шрифтов в этой вкладке.');
    return false;
  }
}

function refreshButtons() {
  const selected = readSelection();
  document.querySelectorAll('[data-compare-id]').forEach((button) => {
    const active = selected.includes(button.dataset.compareId);
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', String(active));
    button.title = active ? 'Удалить из сравнения' : 'Добавить в сравнение';
    button.textContent = active ? '✓' : '＋';
  });
}

function element(tag, className, text = '') {
  const node = document.createElement(tag);
  node.className = className;
  node.textContent = text;
  return node;
}

function updateComparisonText(selected) {
  const input = document.getElementById('comparison-text');
  const selectedFonts = selected.map((id) => fonts[id]);
  const latinOnly = selectedFonts.filter((font) => !font.supportsCyrillic);
  if (latinOnly.length && hasCyrillic(input.value)) {
    const source = [...latinOnly, ...selectedFonts].find((font) =>
      /[A-Za-z]/.test(font.previewText) && !hasCyrillic(font.previewText));
    input.value = source ? source.previewText : 'The quick brown fox jumps over the lazy dog';
  }
}

function renderComparison() {
  if (!fonts) return;
  const selected = readSelection().filter((id) => Object.hasOwn(fonts, id));
  const selectionKey = selected.join(',');
  const container = document.getElementById('comparison-cards');
  if (selectionKey !== previousSelection) {
    updateComparisonText(selected);
    container.replaceChildren(...selected.map(createComparisonCard));
    container.querySelectorAll('.comparison-card').forEach((card) => {
      loadPreviewFont(card.querySelector('.comparison-preview')).then((loaded) => {
        card.dataset.loadFailed = String(!loaded);
        updateComparisonPreview(card);
      });
    });
    previousSelection = selectionKey;
  }
  const slider = document.getElementById('comparison-size');
  const size = slider.value;
  updateSizeSlider(slider);
  document.getElementById('comparison-count').textContent = selected.length + '/5';
  document.getElementById('comparison-clear').disabled = selected.length === 0;
  document.getElementById('empty-state').classList.toggle('hidden', selected.length !== 0);
  document.getElementById('comparison-wrapper').classList.toggle('hidden', selected.length === 0);
  document.getElementById('comparison-language-note').textContent = selected.some((id) => !fonts[id].supportsCyrillic)
    ? 'В наборе есть шрифты без кириллицы. Для сравнения используйте текст на латинице.' : '';
  document.getElementById('size-label').textContent = size;
  document.querySelectorAll('[data-comparison-size]').forEach((button) => {
    button.classList.toggle('active', button.dataset.comparisonSize === size);
  });
  container.querySelectorAll('.comparison-card').forEach(updateComparisonPreview);
}

function updateComparisonPreview(card) {
  const font = fonts[card.dataset.fontId];
  const text = document.getElementById('comparison-text').value;
  const preview = card.querySelector('.comparison-preview');
  preview.textContent = text;
  preview.style.fontSize = document.getElementById('comparison-size').value + 'px';
  card.querySelector('.comparison-preview-status').textContent = previewStatus(text, font.supportsCyrillic, card.dataset.loadFailed === 'true');
}

function createComparisonCard(id) {
  const font = fonts[id];
  const card = element('article', 'comparison-card');
  card.dataset.fontId = id;
  const header = element('div', 'comparison-card-header');
  const description = element('div', '');
  description.append(element('h2', 'comparison-font-name', font.name), element('p', 'comparison-author', font.author));
  const remove = element('button', 'comparison-remove', '×');
  remove.type = 'button';
  remove.dataset.removeCompareId = id;
  remove.title = 'Удалить из сравнения';
  remove.setAttribute('aria-label', 'Удалить ' + font.name + ' из сравнения');
  header.append(description, remove);
  const preview = element('div', 'comparison-preview font-' + id);
  preview.style.fontWeight = font.weight;
  preview.style.fontStyle = font.style;
  const details = element('div', 'comparison-card-details');
  const tags = element('div', 'font-tags');
  font.tags.forEach((tag) => tags.append(element('span', 'tag', tag)));
  const metadata = element('p', 'comparison-metadata', font.license + ' · ' +
    (font.supportsCyrillic ? 'Латиница и кириллица' : 'Латиница'));
  details.append(tags, metadata);
  const status = element('p', 'comparison-preview-status');
  status.setAttribute('role', 'status');
  card.append(header, preview, details, status);
  return card;
}

document.addEventListener('click', (event) => {
  const compare = event.target.closest('[data-compare-id], [data-remove-compare-id]');
  if (compare) {
    const id = compare.dataset.compareId || compare.dataset.removeCompareId;
    let selected = readSelection();
    if (selected.includes(id)) {
      selected = selected.filter((value) => value !== id);
    } else {
      if (selected.length >= 5) {
        alert('Максимум 5 шрифтов для сравнения');
        return;
      }
      selected.push(id);
    }
    if (saveSelection(selected)) {
      refreshButtons();
      renderComparison();
    }
  }

  if (event.target.closest('#comparison-clear') && confirm('Очистить список сравнения?')) {
    if (saveSelection([])) renderComparison();
  }
  const sizeButton = event.target.closest('[data-comparison-size]');
  if (sizeButton) {
    document.getElementById('comparison-size').value = sizeButton.dataset.comparisonSize;
    renderComparison();
  }
});

if (fonts) {
  document.getElementById('comparison-text').addEventListener('input', renderComparison);
  document.getElementById('comparison-size').addEventListener('input', renderComparison);
  renderComparison();
}
document.addEventListener('catalog:updated', refreshButtons);
refreshButtons();
