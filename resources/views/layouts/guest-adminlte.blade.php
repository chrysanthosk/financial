<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - @yield('title', 'Auth')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="hold-transition login-page">

<div class="login-box">
    <div class="login-logo">
        <a href="/"><b>{{ config('app.name') }}</b></a>
    </div>

    <div class="card">
        <div class="card-body login-card-body">
            @yield('content')
        </div>
    </div>

    <div class="text-center mt-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="themeToggleBtn">
            <i class="fas fa-moon"></i> Theme
        </button>
    </div>
</div>

<script>
(function () {
  const btn = document.getElementById('themeToggleBtn');
  if (!btn) return;

  function apply(theme) {
    document.documentElement.dataset.theme = theme;
    if (theme === 'dark') document.body.classList.add('dark-mode');
    else document.body.classList.remove('dark-mode');
  }

  btn.addEventListener('click', function () {
    const current = document.documentElement.dataset.theme || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    apply(next);
    localStorage.setItem('theme', next);
  });

  // boot from localStorage
  try {
    const saved = localStorage.getItem('theme');
    if (saved) apply(saved);
  } catch (e) {}
})();
</script>

</body>
</html>
