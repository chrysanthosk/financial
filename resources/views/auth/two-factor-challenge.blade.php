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

    <div class="row mb-3">
      <div class="col-12">
        <button type="submit" class="btn btn-primary btn-block">Verify</button>
      </div>
    </div>
  </form>

  <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="btn btn-link p-0">Cancel and sign out</button>
  </form>
@endsection
