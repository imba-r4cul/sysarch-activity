<?php
/**
 * Renders a dark mode toggle button for the admin navbar.
 * Place this inside the .nav-links div, before the logout link.
 */
function renderDarkModeToggle() {
    ?>
    <button type="button" class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode" aria-label="Toggle dark mode">
        <span class="material-symbols-outlined" id="darkModeIcon">dark_mode</span>
    </button>
    <?php
}

/**
 * Renders the dark mode initialization JavaScript.
 * Place this before </body>.
 */
function renderDarkModeScript() {
    ?>
    <script>
        (function() {
            var toggle = document.getElementById('darkModeToggle');
            var icon = document.getElementById('darkModeIcon');
            if (!toggle || !icon) return;

            function applyTheme(dark) {
                if (dark) {
                    document.body.classList.add('dark-theme');
                    icon.textContent = 'light_mode';
                } else {
                    document.body.classList.remove('dark-theme');
                    icon.textContent = 'dark_mode';
                }
            }

            var savedTheme = localStorage.getItem('theme');
            applyTheme(savedTheme === 'dark');

            toggle.addEventListener('click', function() {
                var isDark = document.body.classList.contains('dark-theme');
                localStorage.setItem('theme', isDark ? 'light' : 'dark');
                applyTheme(!isDark);
            });
        })();
    </script>
    <?php
}
