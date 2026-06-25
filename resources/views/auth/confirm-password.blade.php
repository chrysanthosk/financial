@extends('layouts.guest-adminlte')

@section('title', 'Confirm Password')

@section('content')
    <p class="login-box-msg">
        This is a secure area of the application. Please confirm your password before continuing.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="input-group mb-3">
            <input
                type="password"
                name="password"
                class="form-control"
                placeholder="Password"
                required
                autofocus
                autocomplete="current-password"
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
        </div>
        <x-input-error :messages="$errors->get('password')" class="mb-2" />

        <div class="row">
            <div class="col-8">
                <a href="{{ route('login') }}">Back to login</a>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-primary btn-block">
                    Confirm
                </button>
            </div>
        </div>
    </form>
@endsection
