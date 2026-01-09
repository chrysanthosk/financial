@extends('layouts.guest-adminlte')

@section('title', 'Login')

@section('content')
    <p class="login-box-msg">Sign in to start your session</p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="input-group mb-3">
            <input
                type="email"
                name="email"
                class="form-control"
                placeholder="Email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-envelope"></span>
                </div>
            </div>
        </div>
        <x-input-error :messages="$errors->get('email')" class="mb-2" />

        <div class="input-group mb-3">
            <input
                type="password"
                name="password"
                class="form-control"
                placeholder="Password"
                required
                autocomplete="current-password"
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
        </div>
        <x-input-error :messages="$errors->get('password')" class="mb-2" />

        <div class="row mb-3">
            <div class="col-8">
                <div class="icheck-primary">
                    <input id="remember_me" type="checkbox" name="remember">
                    <label for="remember_me">Remember Me</label>
                </div>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </div>
        </div>

        <p class="mb-1">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">I forgot my password</a>
            @endif
        </p>
    </form>
@endsection
