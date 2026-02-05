@extends('layouts.guest-adminlte')

@section('title', 'Two-Factor Challenge')

@section('content')
  <p class="login-box-msg">Enter your 2FA code</p>

  @if ($errors->any())
    <div class="alert alert-danger py-2">
      {{ $errors->first() }}
    </div>
  @endif

  @if (session('status'))
    <div class="alert alert-info py-2">
      {{ session('status') }}
    </div>
  @endif

  <form method="POST" action="{{ route('two-factor.challenge.verify') }}">
    @csrf

    <div class="input-group mb-3">
      <input
        type="text"
        name="code"
        class="form-control"
        placeholder="6-digit code"
        inputmode="numeric"
        pattern="[0-9]*"
        maxlength="6"
        autocomplete="one-time-code"
        required
        autofocus
      >
      <div class="input-group-append">
        <div class="input-group-text">
          <span class="fas fa-key"></span>
        </div>
      </div>
    </div>

    <div class="form-group mb-3">
      <div class="icheck-primary">
        <input type="checkbox" id="remember_device" name="remember_device" value="1">
        <label for="remember_device">
          Remember this device for 30 days
        </label>
      </div>
      <div class="text-muted small mt-1">
        You will still need your password. 2FA will be requested again after 30 days (or if cookies are cleared).
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-12">
        <button type="submit" class="btn btn-primary btn-block">Verify</button>
      </div>
    </div>
  </form>

  <form method="POST" action="{{ route('two-factor.challenge.cancel') }}">
    @csrf
    <button type="submit" class="btn btn-link p-0">Cancel and go back to login</button>
  </form>
@endsection
