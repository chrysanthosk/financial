import './bootstrap';

// Bootstrap JS (dropdown/collapse). Must be loaded before AdminLTE.
//import 'bootstrap';

// Bootstrap JS (dropdown/collapse). Must be loaded before AdminLTE.
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// Chart.js is loaded on demand (only on pages that render charts) so it stays
// out of the main bundle. Usage: const Chart = await window.loadChart();
window.loadChart = () => (window.__chartPromise ??= import('chart.js/auto').then((m) => m.default));

// AdminLTE
import 'admin-lte/dist/js/adminlte.js';

// FontAwesome icons
import '@fortawesome/fontawesome-free/js/all.min.js';

/**
 * Submit/loading state: on form submit, disable the submit button (deferred so
 * the submission still goes through) to prevent accidental double-submits and
 * give visual feedback. Opt out with data-no-loading on the <form>.
 */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.noLoading !== undefined) {
        return;
    }

    const btn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (!btn || btn.disabled) {
        return;
    }

    setTimeout(() => {
        btn.disabled = true;
        btn.classList.add('disabled');
        if (btn.tagName === 'BUTTON' && !btn.dataset.busyDone) {
            btn.dataset.busyDone = '1';
            btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>');
        }
    }, 0);
});

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

  document.addEventListener('DOMContentLoaded', () => {
    const initial = getSavedTheme();
    applyTheme(initial);

    const btn = document.getElementById('themeToggleBtn');
    if (btn) {
      btn.addEventListener('click', async () => {
        const next = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
        saveTheme(next);
        applyTheme(next);

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
