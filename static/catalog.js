function initializeCatalog() {
  const form = document.getElementById('catalog-form');
  if (!form) return;

  let pending = null;
  let debounce = null;
  let requestNumber = 0;
  const status = document.getElementById('catalog-status');
  const retry = document.getElementById('catalog-retry');
  const reset = document.getElementById('catalog-reset');
  const actions = document.getElementById('catalog-actions');
  const search = form.querySelector('[name="q"]');

  function refreshFilterControls() {
    const hasFilters = search.value !== '' || form.querySelector('input[type="checkbox"]:checked') !== null;
    reset.classList.toggle('hidden', !hasFilters);
    actions.classList.toggle('is-empty', !hasFilters && status.textContent === '' && retry.classList.contains('hidden'));
  }

  function filterUrl() {
    const parameters = new URLSearchParams();
    new FormData(form).forEach((value, key) => {
      if (String(value).trim() !== '') parameters.append(key, String(value));
    });
    return '/catalog?' + parameters.toString();
  }

  async function updateCatalog(url) {
    pending?.abort();
    pending = new AbortController();
    const controller = pending;
    const currentRequest = ++requestNumber;
    form.setAttribute('aria-busy', 'true');
    retry.classList.add('hidden');
    status.textContent = 'Обновление каталога…';
    refreshFilterControls();

    try {
      const response = await fetch(url, { signal: controller.signal });
      if (!response.ok) throw new Error('Catalog request failed');
      const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
      const results = parsed.getElementById('catalog-results');
      if (!results) throw new Error('Catalog response is incomplete');
      if (currentRequest !== requestNumber) return;
      document.getElementById('catalog-results').replaceWith(results);

      const incomingStylesheet = parsed.getElementById('font-stylesheet');
      let stylesheet = document.getElementById('font-stylesheet');
      if (incomingStylesheet && (!stylesheet || stylesheet.getAttribute('href') !== incomingStylesheet.getAttribute('href'))) {
        if (!stylesheet) {
          stylesheet = document.createElement('link');
          stylesheet.id = 'font-stylesheet';
          stylesheet.rel = 'stylesheet';
          document.head.appendChild(stylesheet);
        }
        await new Promise((resolve, reject) => {
          stylesheet.addEventListener('load', resolve, { once: true });
          stylesheet.addEventListener('error', reject, { once: true });
          stylesheet.href = incomingStylesheet.getAttribute('href');
        });
      }
      if (currentRequest !== requestNumber) return;
      history.pushState(null, '', url);
      document.dispatchEvent(new CustomEvent('catalog:updated'));
      status.textContent = '';
    } catch (error) {
      if (error.name !== 'AbortError' && currentRequest === requestNumber) {
        status.textContent = 'Не удалось обновить каталог. Повторите загрузку.';
        retry.href = url;
        retry.classList.remove('hidden');
      }
    } finally {
      if (currentRequest === requestNumber) {
        form.removeAttribute('aria-busy');
        refreshFilterControls();
      }
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    clearTimeout(debounce);
    updateCatalog(filterUrl());
  });

  form.addEventListener('change', (event) => {
    if (event.target.matches('input[type="checkbox"]')) {
      clearTimeout(debounce);
      updateCatalog(filterUrl());
    }
  });

  search.addEventListener('input', () => {
    refreshFilterControls();
    clearTimeout(debounce);
    debounce = setTimeout(() => updateCatalog(filterUrl()), 350);
  });

  document.addEventListener('change', (event) => {
    if (event.target.id === 'catalog-sort') {
      clearTimeout(debounce);
      updateCatalog(filterUrl());
    }
  });

  document.addEventListener('click', (event) => {
    const link = event.target.closest('#catalog-pagination a');
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    clearTimeout(debounce);
    updateCatalog(link.getAttribute('href'));
    document.querySelector('.sort-bar').scrollIntoView({ block: 'start' });
  });

  // Полный переход заново загружает с сервера и элементы управления, и результаты поиска.
  window.addEventListener('popstate', () => window.location.reload());
  form.classList.add('catalog-enhanced');
  refreshFilterControls();
}

initializeCatalog();
