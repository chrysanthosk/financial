<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ auth()->user()->theme ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - @yield('title', 'Dashboard')</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    {{-- Navbar --}}
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">

            {{-- Theme Toggle --}}
            <li class="nav-item">
                <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="themeToggleBtn">
                    <i class="fas fa-moon"></i> <span class="d-none d-sm-inline">Theme</span>
                </button>
            </li>

            {{-- User dropdown --}}
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-user"></i>
                    <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="fas fa-user-cog mr-2"></i> Profile
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </button>
                    </form>
                </div>
            </li>

        </ul>
    </nav>

    {{-- Sidebar --}}
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-text font-weight-light">{{ config('app.name') }}</span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    {{-- placeholders for next modules --}}
                    <li class="nav-header">FINANCE</li>
                    <li class="nav-item">
                        <a href="#" class="nav-link disabled">
                            <i class="nav-icon fas fa-coins"></i>
                            <p>Income (coming soon)</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link disabled">
                            <i class="nav-icon fas fa-file-invoice-dollar"></i>
                            <p>Expenses (coming soon)</p>
                        </a>
                    </li>

                </ul>
            </nav>
        </div>
    </aside>

    {{-- Content --}}
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0">@yield('page_title', 'Dashboard')</h1>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @yield('content')

            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>&copy; {{ date('Y') }} {{ config('app.name') }}</strong>
    </footer>
</div>

<script>
(function () {
  const btn = document.getElementById('themeToggleBtn');
  if (!btn) return;

  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  function apply(theme) {
    document.documentElement.dataset.theme = theme;
    if (theme === 'dark') document.body.classList.add('dark-mode');
    else document.body.classList.remove('dark-mode');
  }

  btn.addEventListener('click', async function () {
    const current = document.documentElement.dataset.theme || 'light';
    const next = current === 'dark' ? 'light' : 'dark';

    // instant UX
    apply(next);
    localStorage.setItem('theme', next);

    // persist to DB (best-effort)
    try {
      await fetch("{{ route('theme.update') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ theme: next })
      });
    } catch (e) {
      // ignore
    }
  });

  // boot from localStorage if present
  try {
    const saved = localStorage.getItem('theme');
    if (saved) apply(saved);
  } catch (e) {}
})();
</script>

</body>
</html>
