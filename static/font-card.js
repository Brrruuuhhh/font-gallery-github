import { loadPreviewFont, previewStatus, updateSizeSlider } from './font-preview.js';

function updatePreview(card) {
  const input = card.querySelector('.preview-text-input');
  const slider = card.querySelector('.font-size-slider');
  const preview = card.querySelector('.font-preview');
  updateSizeSlider(slider);
  preview.textContent = input.value;
  preview.style.fontSize = slider.value + 'px';
  card.querySelector('.size-label').textContent = 'Размер: ' + slider.value + 'px';
  card.querySelectorAll('.size-btn').forEach((button) => {
    button.classList.toggle('active', parseInt(button.textContent, 10) === Number(slider.value));
  });
  const status = card.querySelector('.font-preview-status');
  status.textContent = previewStatus(input.value, card.dataset.cyrillic === 'true', card.dataset.loadFailed === 'true');
}

function initializeCards() {
  document.querySelectorAll('.font-card').forEach((card) => {
    updatePreview(card);
    loadPreviewFont(card.querySelector('.font-preview')).then((loaded) => {
      card.dataset.loadFailed = String(!loaded);
      updatePreview(card);
    });
  });
}

document.addEventListener('input', (event) => {
  if (event.target.matches('.preview-text-input, .font-size-slider')) {
    updatePreview(event.target.closest('.font-card'));
  }
});
document.addEventListener('click', (event) => {
  const button = event.target.closest('.font-card .size-btn');
  if (!button) return;
  const card = button.closest('.font-card');
  card.querySelector('.font-size-slider').value = parseInt(button.textContent, 10);
  updatePreview(card);
});
document.addEventListener('catalog:updated', initializeCards);
document.addEventListener('click', async (event) => {
  const copy = event.target.closest('[data-copy-css]');
  if (!copy) return;
  const text = copy.closest('.font-card-section').querySelector('code').textContent;
  try {
    await navigator.clipboard.writeText(text);
    copy.textContent = 'Скопировано!';
  } catch {
    copy.textContent = 'Выделите и скопируйте код';
  }
  setTimeout(() => { copy.textContent = 'Копировать'; }, 2000);
});
initializeCards();
