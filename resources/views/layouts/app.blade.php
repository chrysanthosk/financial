<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-update-url" content="{{ route('theme.update') }}">

    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <div class="container">

            <!-- Brand -->
            <a href="{{ route('dashboard') }}" class="navbar-brand">
                <span class="brand-text font-weight-light">{{ config('app.name', 'Financial') }}</span>
            </a>

            <!-- Mobile toggle -->
            <button class="navbar-toggler order-1" type="button" data-toggle="collapse" data-target="#topnav-collapse"
                    aria-controls="topnav-collapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Nav links -->
            <div class="collapse navbar-collapse order-3" id="topnav-collapse">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}"
                           class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                        </a>
                    </li>

                   <li class="nav-item">
                       <a href="{{ route('income.index') }}"
                          class="nav-link {{ request()->routeIs('income.*') ? 'active' : '' }} {{ request()->is('income*') ? 'active' : '' }}">
                           <i class="fas fa-coins mr-1"></i> Income
                       </a>
                   </li>

                    <li class="nav-item">
                      <a href="{{ route('expenses.index') }}" class="nav-link {{ request()->is('expenses*') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Expenses
                      </a>
                    </li>

                    <li class="nav-item">
                        <a href="javascript:void(0)" class="nav-link disabled" tabindex="-1" aria-disabled="true">
                            <i class="fas fa-university mr-1"></i> Accounts
                            <span class="badge badge-light text-dark ml-1">Soon</span>
                        </a>
                    </li>

                    @if(auth()->check() && (auth()->user()->role ?? null) === 'admin')
                        <li class="nav-item">
                            <a href="{{ route('admin.users.index') }}"
                               class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                <i class="fas fa-users mr-1"></i> Users
                            </a>
                        </li>
                    @endif
                </ul>
            </div>

            <!-- Right navbar links -->
            <ul class="navbar-nav order-1 order-md-3 navbar-no-expand ml-auto">

                <!-- Theme toggle -->
                <li class="nav-item">
                    <button id="themeToggleBtn" type="button" class="btn btn-sm btn-outline-secondary mr-2" title="Toggle theme">
                        <i id="themeToggleIcon" class="fas fa-moon"></i>
                    </button>
                </li>

                <!-- User dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                        <i class="far fa-user"></i>
                        <span class="ml-1">{{ auth()->user()->name ?? 'User' }}</span>
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

                @if (session('status'))
                    <div class="alert alert-success mt-2">{{ session('status') }}</div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
    <!-- /.content-wrapper -->

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="float-right d-none d-sm-inline">
                v0.1
            </div>
            <strong>&copy; {{ date('Y') }} {{ config('app.name', 'Financial') }}</strong>
        </div>
    </footer>

</div>

</body>
</html>
