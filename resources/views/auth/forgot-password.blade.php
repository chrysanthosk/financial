@extends('layouts.guest-adminlte')

@section('title', 'Forgot Password')

@section('content')
    <p class="login-box-msg">
        Forgot your password? No problem. Enter your email and we’ll send you a reset link.
    </p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
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

        <div class="row">
            <div class="col-8">
                <a href="{{ route('login') }}">Back to login</a>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-primary btn-block">
                    Send link
                </button>
            </div>
        </div>
    </form>
@endsection
