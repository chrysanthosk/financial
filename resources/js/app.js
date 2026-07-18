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
window.loadChart = () =>
  (window.__chartPromise ??= import('chart.js/auto').then((m) => {
    window.__chart = m.default;
    window.applyChartTheme?.(document.documentElement.getAttribute('data-bs-theme'));
    return m.default;
  }));

/**
 * Chart.js draws to a canvas, so CSS cannot reach its labels or gridlines --
 * its defaults (#666 text, rgba(0,0,0,.1) grid) are invisible on a dark card.
 * Set them from the active theme, and re-apply to live charts on toggle.
 */
window.applyChartTheme = (theme) => {
  const Chart = window.__chart;
  if (!Chart) return;

  const dark = theme === 'dark';
  Chart.defaults.color = dark ? '#c2c7d0' : '#666';
  Chart.defaults.borderColor = dark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.1)';

  // Existing instances captured the old defaults at construction time.
  Object.values(Chart.instances ?? {}).forEach((chart) => {
    Object.values(chart.options.scales ?? {}).forEach((scale) => {
      if (scale.ticks) scale.ticks.color = Chart.defaults.color;
      if (scale.grid) scale.grid.color = Chart.defaults.borderColor;
    });
    if (chart.options.plugins?.legend?.labels) {
      chart.options.plugins.legend.labels.color = Chart.defaults.color;
    }
    chart.update('none');
  });
};

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

    // Bootstrap 5.3 / AdminLTE 4 theme every built-in component off this
    // attribute. Without it only the hand-written rules in app.css are dark
    // and everything else (pagination, form-select, progress, ...) stays white.
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.documentElement.setAttribute('data-theme', theme);

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
    window.applyChartTheme?.(theme);
  }

  function getSavedTheme() {
    // Fall back to what the server rendered, not to 'light' — otherwise a
    // browser with no localStorage entry flips a dark-themed user to light.
    return (
      localStorage.getItem(THEME_KEY) ||
      document.documentElement.getAttribute('data-bs-theme') ||
      'light'
    );
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
