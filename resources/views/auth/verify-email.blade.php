@extends('layouts.guest-adminlte')

@section('title', 'Verify Email')

@section('content')
    <p class="login-box-msg">
        Thanks for signing up! Before getting started, could you verify your email address by clicking on the
        link we just emailed to you? If you didn't receive the email, we will gladly send you another.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success">
            A new verification link has been sent to the email address you provided during registration.
        </div>
    @endif

    <div class="row">
        <div class="col-6">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-block">
                    Resend Verification Email
                </button>
            </form>
        </div>
        <div class="col-6">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-block">
                    Log Out
                </button>
            </form>
        </div>
    </div>
@endsection
