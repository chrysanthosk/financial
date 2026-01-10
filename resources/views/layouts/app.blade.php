<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-update-url" content="{{ route('theme.update') }}">

    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Small UI fixes + dark-mode safe "Soon" badge --}}
    <style>
        .badge-soon{
            display:inline-block;
            font-size:.75rem;
            font-weight:600;
            padding:.25em .45em;
            border-radius:.35rem;
            border:1px solid rgba(0,0,0,.15);
            background: rgba(0,0,0,.06);
            color: rgba(0,0,0,.75);
            vertical-align: middle;
        }
        body.dark-mode .badge-soon{
            border-color: rgba(255,255,255,.18);
            background: rgba(255,255,255,.12);
            color: rgba(255,255,255,.85);
        }

        body.dark-mode .dropdown-menu{
            background-color: #2b2b2b;
            border-color: rgba(255,255,255,.10);
        }
        body.dark-mode .dropdown-item,
        body.dark-mode .dropdown-item-text{
            color: rgba(255,255,255,.85);
        }
        body.dark-mode .dropdown-item:hover,
        body.dark-mode .dropdown-item:focus{
            background-color: rgba(255,255,255,.08);
            color: #fff;
        }
        body.dark-mode .dropdown-divider{
            border-top-color: rgba(255,255,255,.12);
        }
    </style>
</head>

@php
  // Safe DB-backed branding (falls back to config/app.name if table not ready)
  $system = \App\Models\SystemSetting::safeCurrent();
  $brandHeader = $system?->header_name ?: config('app.name', 'Financial');
  $brandFooter = $system?->footer_name ?: config('app.name', 'Financial');
@endphp

<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <div class="container">

            <!-- Brand -->
            <a href="{{ route('dashboard') }}" class="navbar-brand">
                <span class="brand-text fw-light">{{ $brandHeader }}</span>
            </a>

            <!-- Mobile toggle -->
            <button class="navbar-toggler order-1" type="button"
                    data-bs-toggle="collapse" data-bs-target="#topnav-collapse"
                    aria-controls="topnav-collapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Nav links -->
            <div class="collapse navbar-collapse order-3" id="topnav-collapse">
                <ul class="navbar-nav">

                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}"
                           class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('income.index') }}"
                           class="nav-link {{ request()->routeIs('income.*') ? 'active' : '' }} {{ request()->is('income*') ? 'active' : '' }}">
                            <i class="fas fa-coins me-1"></i> Income
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('expenses.index') }}"
                           class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                            <i class="fas fa-file-invoice-dollar me-1"></i> Expenses
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="javascript:void(0)" class="nav-link disabled" tabindex="-1" aria-disabled="true">
                            <i class="fas fa-university me-1"></i> Accounts
                            <span class="badge-soon ms-1">Soon</span>
                        </a>
                    </li>

                    @if(auth()->check() && (auth()->user()->role ?? null) === 'admin')
                        <li class="nav-item">
                            <a href="{{ route('admin.users.index') }}"
                               class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                <i class="fas fa-users me-1"></i> Users
                            </a>
                        </li>

                        {{-- Settings dropdown (Admin only) --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.settings.*') || request()->routeIs('admin.audit.*') ? 'active' : '' }}"
                               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cogs me-1"></i> Settings
                            </a>

                            <ul class="dropdown-menu">
                                @if(\Illuminate\Support\Facades\Route::has('admin.settings.smtp.edit'))
                                    <li>
                                        <a href="{{ route('admin.settings.smtp.edit') }}" class="dropdown-item">
                                            <i class="fas fa-envelope me-2"></i> SMTP
                                        </a>
                                    </li>
                                @endif

                                @if(\Illuminate\Support\Facades\Route::has('admin.settings.config.index'))
                                    <li>
                                        <a href="{{ route('admin.settings.config.index') }}" class="dropdown-item">
                                            <i class="fas fa-sliders-h me-2"></i> Configuration
                                        </a>
                                    </li>
                                @endif

                                @if(\Illuminate\Support\Facades\Route::has('admin.audit.index'))
                                    <li>
                                        <a href="{{ route('admin.audit.index') }}" class="dropdown-item">
                                            <i class="fas fa-clipboard-list me-2"></i> Audit Log
                                        </a>
                                    </li>
                                @endif

                                <li><hr class="dropdown-divider"></li>

                                <li>
                                    <span class="dropdown-item-text text-muted small">
                                        More settings coming soon
                                    </span>
                                </li>
                            </ul>
                        </li>
                    @endif

                </ul>
            </div>

            <!-- Right navbar links -->
            <ul class="navbar-nav order-1 order-md-3 navbar-no-expand ms-auto">

                <!-- Theme toggle -->
                <li class="nav-item">
                    <button id="themeToggleBtn" type="button" class="btn btn-sm btn-outline-secondary me-2" title="Toggle theme">
                        <i id="themeToggleIcon" class="fas fa-moon"></i>
                    </button>
                </li>

                <!-- User dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#"
                       data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <i class="far fa-user"></i>
                        <span class="ms-1">{{ auth()->user()->name ?? 'User' }}</span>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a href="{{ route('profile.edit') }}" class="dropdown-item">
                                <i class="fas fa-user-cog me-2"></i> Profile
                            </a>
                        </li>

                        <li><hr class="dropdown-divider"></li>

                        <li>
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>

        </div>
    </nav>
    <!-- /.navbar -->

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container">
                @hasSection('content-header')
                    @yield('content-header')
                @else
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="m-0">@yield('page-title', '')</h1>
                    </div>
                @endif
            </div>
        </div>

        <!-- Main content -->
        <div class="content">
            <div class="container pb-4">

                {{-- Flash status (keep it here ONLY to avoid duplicates) --}}
                @if (session('status'))
                    <div class="alert alert-success mt-2 mb-3">{{ session('status') }}</div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
    <!-- /.content-wrapper -->

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="float-end d-none d-sm-inline">
                v0.1
            </div>
            <strong>&copy; {{ date('Y') }} {{ $brandFooter }}</strong>
        </div>
    </footer>

</div>
</body>
</html>
