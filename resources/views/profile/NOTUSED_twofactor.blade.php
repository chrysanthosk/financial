@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Two-Factor Authentication</h1>
    <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Back</a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-8">

      <div class="card mb-3">
        <div class="card-header"><strong>Status</strong></div>
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span>2FA</span>
            <strong>{{ $user->hasTwoFactorEnabled() ? 'Enabled' : 'Disabled' }}</strong>
          </div>
        </div>
      </div>

      @if(!$user->hasTwoFactorEnabled())
        <div class="card mb-3">
          <div class="card-header"><strong>Enable 2FA</strong></div>
          <div class="card-body">

            @if(!$pendingSecret)
              <form method="POST" action="{{ route('profile.2fa.enable') }}">
                @csrf
                <button class="btn btn-primary" type="submit">
                  <i class="fas fa-qrcode"></i> Generate QR
                </button>
              </form>
            @else
              <div class="row">
                <div class="col-md-4">
                  <div class="text-muted small mb-2">Scan this QR with Authy/Google Authenticator:</div>
                  <img src="{{ $qrInline }}" alt="2FA QR" class="img-thumbnail">
                </div>
                <div class="col-md-8">
                  <div class="text-muted small mb-2">
                    After scanning, enter the 6-digit code to confirm.
                  </div>

                  <form method="POST" action="{{ route('profile.2fa.confirm') }}">
                    @csrf

                    <div class="mb-3">
                      <label class="form-label">Authenticator Code</label>
                      <input type="text" name="code" class="form-control" placeholder="123456" required>
                      @error('code') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <button class="btn btn-success" type="submit">
                      <i class="fas fa-check"></i> Confirm & Enable
                    </button>
                  </form>
                </div>
              </div>
            @endif

          </div>
        </div>
      @else
        <div class="card mb-3">
          <div class="card-header"><strong>Recovery Codes</strong></div>
          <div class="card-body">
            <div class="text-muted small mb-2">
              Save these codes somewhere safe. Each code can be used once if you lose access to your authenticator.
            </div>

            <div class="row">
              @foreach(($user->two_factor_recovery_codes ?? []) as $code)
                <div class="col-md-4 mb-2">
                  <code class="d-block p-2 border rounded">{{ $code }}</code>
                </div>
              @endforeach
            </div>

            <form method="POST" action="{{ route('profile.2fa.recovery.regenerate') }}" class="d-inline"
                  onsubmit="return confirm('Regenerate recovery codes? Old codes will stop working.');">
              @csrf
              <div class="row mt-3">
                <div class="col-md-6">
                  <label class="form-label">Confirm with password</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                  <button class="btn btn-outline-primary w-100" type="submit">
                    <i class="fas fa-sync"></i> Regenerate Codes
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><strong>Disable 2FA</strong></div>
          <div class="card-body">
            <form method="POST" action="{{ route('profile.2fa.disable') }}"
                  onsubmit="return confirm('Disable 2FA?');">
              @csrf
              <div class="row">
                <div class="col-md-6">
                  <label class="form-label">Confirm with password</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                  <button class="btn btn-outline-danger w-100" type="submit">
                    <i class="fas fa-times"></i> Disable 2FA
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      @endif

    </div>
  </div>

</div>
@endsection
