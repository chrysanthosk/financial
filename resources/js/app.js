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
    window.__registerChartTheme?.(m.default);
    return m.default;
  }));

/**
 * Chart theming.
 *
 * Chart.js draws to a canvas, so CSS cannot reach it. Two separate problems:
 *
 * 1. Text and gridlines. Its defaults (#666 text on rgba(0,0,0,.1) grid) are
 *    invisible on a dark card.
 * 2. Series colours. No chart view in this app sets backgroundColor or
 *    borderColor, so every series fell back to Chart.js's translucent black --
 *    barely visible in light mode and effectively invisible in dark. The
 *    palette plugin below assigns colours by series index so each view stays
 *    free of colour code.
 *
 * NB: do NOT set Chart.defaults.borderColor for the grid. Chart.js uses that
 * one value for gridlines *and* for any dataset that has no borderColor of its
 * own, so a faint grid colour there silently repaints the data itself.
 */
const CHART_THEME = {
  light: {
    // Categorical slots, fixed order. Validated for CVD separation and
    // contrast against the chart surface in this mode -- do not reorder or
    // substitute without re-validating.
    series: ['#2a78d6', '#008300', '#e87ba4', '#eda100', '#1baf7a', '#eb6834', '#4a3aa7', '#e34948'],
    neutral: '#8a8a8a',
    surface: '#ffffff',
    grid: 'rgba(0,0,0,.1)',
    text: '#666',
  },
  dark: {
    // The same eight hues re-stepped for the dark surface, not a flip.
    series: ['#3987e5', '#008300', '#d55181', '#c98500', '#199e70', '#d95926', '#9085e9', '#e66767'],
    neutral: '#9a9a9a',
    surface: '#343a40',
    grid: 'rgba(255,255,255,.12)',
    text: '#c2c7d0',
  },
};

const chartTheme = (theme) => CHART_THEME[theme === 'dark' ? 'dark' : 'light'];

// Datasets that arrived with their own colours are left alone.
const authoredColors = new WeakMap();

const palettePlugin = {
  id: 'appPalette',

  beforeInit(chart) {
    authoredColors.set(
      chart,
      chart.data.datasets.map(
        (ds) => ds.backgroundColor !== undefined || ds.borderColor !== undefined
      )
    );
  },

  beforeUpdate(chart) {
    const t = chartTheme(document.documentElement.getAttribute('data-bs-theme'));
    const authored = authoredColors.get(chart) ?? [];
    const isArc = ['doughnut', 'pie', 'polarArea'].includes(chart.config.type);

    chart.data.datasets.forEach((ds, i) => {
      if (authored[i]) return;

      if (isArc) {
        // One colour per slice, plus a surface-coloured gap between segments
        // so adjacent slices stay separable.
        ds.backgroundColor = (chart.data.labels ?? []).map(
          (_, j) => t.series[j] ?? t.neutral
        );
        ds.borderColor = t.surface;
        ds.borderWidth = 2;
        return;
      }

      const color = t.series[i] ?? t.neutral;
      ds.borderColor = color;
      ds.backgroundColor = color;
      if (ds.borderWidth === undefined) ds.borderWidth = 2;
    });
  },
};

window.applyChartTheme = (theme) => {
  const Chart = window.__chart;
  if (!Chart) return;

  const t = chartTheme(theme);
  Chart.defaults.color = t.text;
  Chart.defaults.scale.grid.color = t.grid;
  Chart.defaults.scale.ticks.color = t.text;
  Chart.defaults.scale.border = { ...(Chart.defaults.scale.border ?? {}), color: t.grid };

  // Live charts resolved the old values when they were built.
  Object.values(Chart.instances ?? {}).forEach((chart) => chart.update('none'));
};

window.__registerChartTheme = (Chart) => {
  Chart.register(palettePlugin);
  window.applyChartTheme(document.documentElement.getAttribute('data-bs-theme'));
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
