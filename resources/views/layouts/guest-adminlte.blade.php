<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light" data-bs-theme="light">
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

{{-- Theme toggling is handled by themeInit() in resources/js/app.js, which is
     loaded here too. A second inline handler on the same button used to bind
     alongside it, so every click toggled twice and appeared to do nothing. --}}

</body>
</html>
