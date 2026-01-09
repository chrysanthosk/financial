@extends('layouts.guest-adminlte')

@section('title', 'Register')

@section('content')
    <p class="login-box-msg">Register a new membership</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="input-group mb-3">
            <input
                type="text"
                name="name"
                class="form-control"
                placeholder="Full name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-user"></span>
                </div>
            </div>
        </div>
        <x-input-error :messages="$errors->get('name')" class="mb-2" />

        <div class="input-group mb-3">
            <input
                type="email"
                name="email"
                class="form-control"
                placeholder="Email"
                value="{{ old('email') }}"
                required
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
                autocomplete="new-password"
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
        </div>
        <x-input-error :messages="$errors->get('password')" class="mb-2" />

        <div class="input-group mb-3">
            <input
                type="password"
                name="password_confirmation"
                class="form-control"
                placeholder="Retype password"
                required
                autocomplete="new-password"
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
        </div>
        <x-input-error :messages="$errors->get('password_confirmation')" class="mb-2" />

        <div class="row">
            <div class="col-8">
                <a href="{{ route('login') }}" class="text-center">I already have a membership</a>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </div>
        </div>
    </form>
@endsection
