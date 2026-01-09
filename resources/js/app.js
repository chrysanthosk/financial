import './bootstrap';

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// AdminLTE
import 'admin-lte/dist/js/adminlte.js';

// FontAwesome icons
import '@fortawesome/fontawesome-free/js/all.min.js';

/**
 * Theme handling:
 * - AdminLTE uses: body.dark-mode
 * - We store: localStorage key "theme" => "dark" | "light"
 * - We update icon on the navbar button
 * - We POST to /theme (best-effort) to persist server-side
 */
(function themeInit() {
  const THEME_KEY = 'theme';

  function setIcon(theme) {
    const icon = document.getElementById('themeToggleIcon');
    if (!icon) return;

    if (theme === 'dark') {
      icon.classList.remove('fa-moon');
      icon.classList.add('fa-sun');
    } else {
      icon.classList.remove('fa-sun');
      icon.classList.add('fa-moon');
    }
  }

 function applyTheme(theme) {
   const navbar = document.querySelector('.main-header.navbar');

   if (theme === 'dark') {
     document.body.classList.add('dark-mode');

     if (navbar) {
       navbar.classList.remove('navbar-white', 'navbar-light');
       navbar.classList.add('navbar-dark');
     }
   } else {
     document.body.classList.remove('dark-mode');

     if (navbar) {
       navbar.classList.remove('navbar-dark');
       navbar.classList.add('navbar-white', 'navbar-light');
     }
   }

   setIcon(theme);
 }
  function getSavedTheme() {
    return localStorage.getItem(THEME_KEY) || 'light';
  }

  function saveTheme(theme) {
    localStorage.setItem(THEME_KEY, theme);
  }

  // Run after DOM is ready so the toggle button exists
  document.addEventListener('DOMContentLoaded', () => {
    // Initial apply
    const initial = getSavedTheme();
    applyTheme(initial);

    // Bind toggle button
    const btn = document.getElementById('themeToggleBtn');
    if (btn) {
      btn.addEventListener('click', async () => {
        const next = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
        saveTheme(next);
        applyTheme(next);

        // Persist server-side (best-effort)
        const themeUrl = document.querySelector('meta[name="theme-update-url"]')?.getAttribute('content');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (themeUrl && csrf) {
          try {
            await fetch(themeUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
              },
              body: JSON.stringify({ theme: next }),
            });
          } catch (e) {
            // ignore
          }
        }
      });
    }
  });
})();
