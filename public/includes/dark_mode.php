<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['admin_name']) && $_SESSION['admin_name'] === 'CCS Admin') {
    $_SESSION['admin_name'] = 'Admin';
}

/**
 * Renders a dark mode toggle button for the admin navbar.
 * Place this inside the .nav-links div, before the logout link.
 */
function renderDarkModeToggle() {
    ?>
    <button type="button" class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode" aria-label="Toggle dark mode">
        <span class="switch-knob">
            <span class="material-symbols-outlined sun-icon">sunny</span>
            <span class="material-symbols-outlined moon-icon">nightlight_round</span>
        </span>
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
            // Dark Mode theme toggling logic
            var toggle = document.getElementById('darkModeToggle');
            var icon = document.getElementById('darkModeIcon');
            
            if (toggle) {
                function applyTheme(dark) {
                    if (dark) {
                        document.body.classList.add('dark-theme');
                        if (icon) icon.textContent = 'light_mode';
                    } else {
                        document.body.classList.remove('dark-theme');
                        if (icon) icon.textContent = 'dark_mode';
                    }
                }

                var savedTheme = localStorage.getItem('theme');
                applyTheme(savedTheme === 'dark');

                toggle.addEventListener('click', function() {
                    var isDark = document.body.classList.contains('dark-theme');
                    localStorage.setItem('theme', isDark ? 'light' : 'dark');
                    applyTheme(!isDark);
                });
            }

            // Profile dropdown menu toggling and click-outside dismissal
            var profileBtn = document.getElementById('profileDropdownBtn');
            var profileMenu = document.getElementById('profileDropdownMenu');
            
            if (profileBtn && profileMenu) {
                var profileDropdown = profileBtn.closest('.nav-profile-dropdown');
                
                profileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isExpanded = profileBtn.getAttribute('aria-expanded') === 'true';
                    profileBtn.setAttribute('aria-expanded', !isExpanded);
                    profileMenu.classList.toggle('show');
                    if (profileDropdown) profileDropdown.classList.toggle('active');
                });

                document.addEventListener('click', function(e) {
                    if (profileDropdown && !profileDropdown.contains(e.target)) {
                        profileBtn.setAttribute('aria-expanded', 'false');
                        profileMenu.classList.remove('show');
                        profileDropdown.classList.remove('active');
                    }
                });
            }
        })();
    </script>
    <?php
}
