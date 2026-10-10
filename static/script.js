const menuToggle = document.querySelector('.menu-toggle');
const navMobile = document.querySelector('.nav-mobile');

function setMenuOpen(open) {
  menuToggle.classList.toggle('active', open);
  navMobile.classList.toggle('active', open);
  menuToggle.setAttribute('aria-expanded', String(open));
  menuToggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
}

if (menuToggle && navMobile) {
  menuToggle.addEventListener('click', () => setMenuOpen(!navMobile.classList.contains('active')));
  navMobile.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenuOpen(false));
  });
}

// Обрабатываем только ссылки навигации; выделение текущей страницы каталога сохраняется таким, каким его выдал сервер.
const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
document.querySelectorAll('.nav-desktop a[href], .nav-mobile a[href]').forEach((link) => {
  const path = link.getAttribute('href').replace(/\/$/, '') || '/';
  const active = path === currentPath;
  link.classList.toggle('active', active);
  if (active) link.setAttribute('aria-current', 'page');
  else link.removeAttribute('aria-current');
});

// Удаляем обработчик перед подстановкой запасного изображения, чтобы его отсутствие не вызывало бесконечные повторы.
document.querySelectorAll('img[data-fallback]').forEach((image) => {
  const fallback = () => {
    image.removeEventListener('error', fallback);
    image.src = image.dataset.fallback;
  };
  image.addEventListener('error', fallback, { once: true });
  if (image.complete && image.naturalWidth === 0) fallback();
});
