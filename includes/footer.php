<!-- Общий шаблон нижней части сайта. Подключается ко всем страницам через renderFooter(). -->
<!-- НФТ-8/9/13: текстовые ссылки с относительными адресами имеют подсказку title; навигация доступна без JavaScript. -->
<footer>
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-section">
                <h3>Галерея Шрифтов</h3>
                <p>
                    Каталог шрифтов с инструментами предпросмотра и сравнения
                </p>
            </div>

            <div class="footer-section">
                <h4>Навигация</h4>
                <ul>
                    <li><a href="/" title="Главная страница">Главная</a></li>
                    <li><a href="/catalog" title="Каталог шрифтов">Каталог</a></li>
                    <li><a href="/comparison" title="Сравнение шрифтов">Сравнение</a></li>
                    <li><a href="/about" title="О сайте">О сайте</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Информация</h4>
                <ul>
                    <li><a href="/sitemap" title="Карта сайта">Карта сайта</a></li>
                    <li><a href="/about#privacy" title="Политика конфиденциальности">Политика конфиденциальности</a></li>
                    <li><a href="mailto:info@fontgallery.ru" title="Написать на почту">Контакты</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© 2026 Галерея Шрифтов. Все права защищены.</p>
        </div>
    </div>
</footer>

<!-- НФТ-10: все файлы JavaScript подключаются по действующим относительным путям из корня сайта. -->
{PAGE_SCRIPTS}
</body>
</html>
