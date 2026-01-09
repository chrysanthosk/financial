@extends('layouts.guest-adminlte')

@section('title', 'Reset Password')

@section('content')
    <p class="login-box-msg">You are only one step away from your new password.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="input-group mb-3">
            <input
                type="email"
                name="email"
                class="form-control"
                placeholder="Email"
                value="{{ old('email', $request->email) }}"
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
                placeholder="New password"
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
                placeholder="Confirm new password"
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
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-block">
                    Reset Password
                </button>
            </div>
        </div>
    </form>
@endsection
