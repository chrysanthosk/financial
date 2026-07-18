@php
  // Render the saved theme server-side so the page does not paint light first
  // and then flip. data-bs-theme is what Bootstrap 5.3 / AdminLTE 4 read;
  // body.dark-mode is this app's own hook used throughout app.css.
  $theme = auth()->user()->theme ?? 'light';
  $theme = in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ $theme }}" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-update-url" content="{{ route('theme.update') }}">

    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
  $system = \App\Models\SystemSetting::safeCurrent();
  $brandHeader = $system?->header_name ?: config('app.name', 'Financial');
  $brandFooter = $system?->footer_name ?: config('app.name', 'Financial');

  $isAdmin = auth()->check() && ((auth()->user()->role ?? null) === 'admin');

  // reports dropdown active when any reports.* route is active
  $reportsActive = request()->routeIs('reports.*');

  // Employee Income report active specifically
  $employeeIncomeReportActive = request()->routeIs('reports.employee_income') || request()->routeIs('reports.employee_income*');
@endphp

<body class="hold-transition layout-top-nav @if($theme === 'dark') dark-mode @endif">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand-xl navbar-white navbar-light">
        <div class="container">

            <!-- Brand -->
            <a href="{{ route('dashboard') }}" class="navbar-brand">
                <span class="brand-text fw-light">{{ $brandHeader }}</span>
            </a>

            <!-- Mobile toggle -->
            <button class="navbar-toggler" type="button"
                    data-bs-toggle="collapse" data-bs-target="#topnav-collapse"
                    aria-controls="topnav-collapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Everything collapses together (left + right) -->
            <div class="collapse navbar-collapse" id="topnav-collapse">

                <!-- LEFT menu -->
                <ul class="navbar-nav me-auto">

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

                    {{-- Emp. Income is a REPORT (admin only) --}}
                    @if($isAdmin && \Illuminate\Support\Facades\Route::has('admin.emp_income.index'))
                        <li class="nav-item">
                            <a href="{{ route('admin.emp_income.index') }}"
                               class="nav-link {{ request()->routeIs('admin.emp_income.*') ? 'active' : '' }}">
                                <i class="fas fa-user-tie me-1"></i> Emp. Income
                            </a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <a href="{{ route('expenses.index') }}"
                           class="nav-link {{ request()->routeIs('expenses.index') || request()->routeIs('expenses.create') || request()->routeIs('expenses.edit') ? 'active' : '' }}">
                            <i class="fas fa-file-invoice-dollar me-1"></i> Expenses
                        </a>
                    </li>

                    @if(\Illuminate\Support\Facades\Route::has('expenses.recurring.index'))
                        <li class="nav-item">
                            <a href="{{ route('expenses.recurring.index') }}"
                               class="nav-link {{ request()->routeIs('expenses.recurring.*') ? 'active' : '' }}">
                                <i class="fas fa-sync-alt me-1"></i> Recurring
                            </a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <a href="{{ route('bonus.index') }}"
                           class="nav-link {{ request()->routeIs('bonus.*') ? 'active' : '' }}">
                            <i class="fas fa-percentage me-1"></i> Bonus
                        </a>
                    </li>

                    {{-- Reports dropdown --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $reportsActive ? 'active' : '' }}"
                           href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chart-pie me-1"></i> Reports
                        </a>

                        <ul class="dropdown-menu">
                            @if(\Illuminate\Support\Facades\Route::has('reports.index'))
                                <li>
                                    <a href="{{ route('reports.index') }}" class="dropdown-item">
                                        <i class="fas fa-th-large me-2"></i> All Reports
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.ytd_income'))
                                <li>
                                    <a href="{{ route('reports.ytd_income') }}" class="dropdown-item">
                                        <i class="fas fa-coins me-2"></i> Year-to-Date Income
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.ytd_expenses'))
                                <li>
                                    <a href="{{ route('reports.ytd_expenses') }}" class="dropdown-item">
                                        <i class="fas fa-receipt me-2"></i> Year-to-Date Expenses
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.monthly_profit'))
                                <li>
                                    <a href="{{ route('reports.monthly_profit') }}" class="dropdown-item">
                                        <i class="fas fa-chart-line me-2"></i> Monthly Profit
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.revenue_by_year'))
                                <li>
                                    <a href="{{ route('reports.revenue_by_year') }}" class="dropdown-item">
                                        <i class="fas fa-building me-2"></i> Company Revenue by Year
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.profit_margin_by_year'))
                                <li>
                                    <a href="{{ route('reports.profit_margin_by_year') }}" class="dropdown-item">
                                        <i class="fas fa-percentage me-2"></i> Profit Margin by Year
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.income_source_by_year'))
                                <li>
                                    <a href="{{ route('reports.income_source_by_year') }}" class="dropdown-item">
                                        <i class="fas fa-stream me-2"></i> Income Source by Year
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.cash_flow'))
                                <li>
                                    <a href="{{ route('reports.cash_flow') }}" class="dropdown-item">
                                        <i class="fas fa-wallet me-2"></i> Cash Flow / Net Position
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.quarterly_summary'))
                                <li>
                                    <a href="{{ route('reports.quarterly_summary') }}" class="dropdown-item">
                                        <i class="fas fa-calendar-alt me-2"></i> Quarterly Summary
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.prev_year_comparison'))
                                <li>
                                    <a href="{{ route('reports.prev_year_comparison') }}" class="dropdown-item">
                                        <i class="fas fa-exchange-alt me-2"></i> Previous Year Comparison
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.prev_year_monthly_income'))
                                <li>
                                    <a href="{{ route('reports.prev_year_monthly_income') }}" class="dropdown-item">
                                        <i class="fas fa-chart-bar me-2"></i> Prev Year Monthly Income
                                    </a>
                                </li>
                            @endif

                            <li><hr class="dropdown-divider"></li>

                            @if(\Illuminate\Support\Facades\Route::has('reports.top_vendors'))
                                <li>
                                    <a href="{{ route('reports.top_vendors') }}" class="dropdown-item">
                                        <i class="fas fa-store me-2"></i> Top Vendors
                                    </a>
                                </li>
                            @endif

                            {{-- Employee Income report (admin only) --}}
                            @if($isAdmin && \Illuminate\Support\Facades\Route::has('reports.employee_income'))
                                <li>
                                    <a href="{{ route('reports.employee_income') }}" class="dropdown-item">
                                        <i class="fas fa-user-tie me-2"></i> Employee Income
                                    </a>
                                </li>
                            @endif

                            {{-- Employee Revenue by Year report (admin only) --}}
                            @if($isAdmin && \Illuminate\Support\Facades\Route::has('reports.employee_revenue_by_year'))
                                <li>
                                    <a href="{{ route('reports.employee_revenue_by_year') }}" class="dropdown-item">
                                        <i class="fas fa-users me-2"></i> Employee Revenue by Year
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.expense_category_breakdown'))
                                <li>
                                    <a href="{{ route('reports.expense_category_breakdown') }}" class="dropdown-item">
                                        <i class="fas fa-chart-pie me-2"></i> Category Breakdown
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.income_method_trend'))
                                <li>
                                    <a href="{{ route('reports.income_method_trend') }}" class="dropdown-item">
                                        <i class="fas fa-percentage me-2"></i> Income Method Trend
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.recurring_expenses'))
                                <li>
                                    <a href="{{ route('reports.recurring_expenses') }}" class="dropdown-item">
                                        <i class="fas fa-redo-alt me-2"></i> Recurring Expenses Detector
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.largest_transactions'))
                                <li>
                                    <a href="{{ route('reports.largest_transactions') }}" class="dropdown-item">
                                        <i class="fas fa-sort-amount-down-alt me-2"></i> Largest Transactions
                                    </a>
                                </li>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('reports.category_trend'))
                                <li>
                                    <a href="{{ route('reports.category_trend') }}" class="dropdown-item">
                                        <i class="fas fa-layer-group me-2"></i> Category Trend Over Time
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>

                    @if($isAdmin)
                        <li class="nav-item">
                            <a href="{{ route('admin.users.index') }}"
                               class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                <i class="fas fa-users me-1"></i> Users
                            </a>
                        </li>

                        {{-- Tools dropdown --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('tools.*') ? 'active' : '' }}"
                               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-toolbox me-1"></i> Tools
                            </a>

                            <ul class="dropdown-menu">
                                @if(\Illuminate\Support\Facades\Route::has('tools.import.index'))
                                    <li>
                                        <a href="{{ route('tools.import.index') }}" class="dropdown-item">
                                            <i class="fas fa-file-import me-2"></i> Import
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>

                        {{-- Settings dropdown --}}
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

                                @if(\Illuminate\Support\Facades\Route::has('admin.settings.logs.index'))
                                    <li>
                                        <a href="{{ route('admin.settings.logs.index') }}" class="dropdown-item">
                                            <i class="fas fa-file-lines me-2"></i> Application Log
                                        </a>
                                    </li>
                                @endif

                                @if(\Illuminate\Support\Facades\Route::has('admin.settings.maintenance.index'))
                                    <li>
                                        <a href="{{ route('admin.settings.maintenance.index') }}" class="dropdown-item">
                                            <i class="fas fa-screwdriver-wrench me-2"></i> Data Maintenance
                                        </a>
                                    </li>
                                @endif

                                <li><hr class="dropdown-divider"></li>
                                <li><span class="dropdown-item-text text-muted small">More settings coming soon</span></li>
                            </ul>
                        </li>
                    @endif

                </ul>

                <!-- RIGHT menu -->
                <ul class="navbar-nav ms-auto navbar-no-expand">

                    <li class="nav-item">
                        <button id="themeToggleBtn" type="button" class="btn btn-sm btn-outline-secondary me-2" title="Toggle theme">
                            <i id="themeToggleIcon" class="fas fa-moon"></i>
                        </button>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#"
                           data-bs-toggle="dropdown"
                           data-bs-display="dynamic"
                           role="button" aria-expanded="false">
                            <i class="far fa-user"></i>
                            <span class="ms-1 d-none d-sm-inline">{{ auth()->user()->name ?? 'User' }}</span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end mt-2 shadow">
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
        </div>
    </nav>
    <!-- /.navbar -->

    <!-- Content Wrapper -->
    <div class="content-wrapper">
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

        <div class="content">
            <div class="container pb-4">
                @if (session('status'))
                    <div class="alert alert-success mt-2 mb-3">{{ session('status') }}</div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="float-end d-none d-sm-inline">v0.1</div>
            <strong>&copy; {{ date('Y') }} {{ $brandFooter }}</strong>
        </div>
    </footer>

</div>
</body>
</html>
