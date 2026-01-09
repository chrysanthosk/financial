@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Profile</h1>
    <a href="{{ route('profile.2fa.show') }}" class="btn btn-outline-secondary">
      <i class="fas fa-shield-alt"></i> 2FA
    </a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-8">

      {{-- Profile info --}}
      <div class="card mb-3">
        <div class="card-header"><strong>Profile Information</strong></div>
        <div class="card-body">

          <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PATCH')

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">First name</label>
                <input type="text" name="first_name" class="form-control"
                       value="{{ old('first_name', $user->first_name) }}">
                @error('first_name') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label">Last name</label>
                <input type="text" name="last_name" class="form-control"
                       value="{{ old('last_name', $user->last_name) }}">
                @error('last_name') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 mb-2">
                <label class="form-label">Current email</label>
                <input type="email" class="form-control" value="{{ $user->email }}" disabled>
              </div>

              <div class="col-12">
                <button class="btn btn-primary" type="submit">
                  <i class="fas fa-save"></i> Save Profile
                </button>
              </div>
            </div>
          </form>

        </div>
      </div>

      {{-- Request email change --}}
      <div class="card mb-3">
        <div class="card-header"><strong>Change Email</strong></div>
        <div class="card-body">

          @if($user->pending_email)
            <div class="alert alert-info">
              Pending email change to <strong>{{ $user->pending_email }}</strong>.
              Please check that inbox for the confirmation link.
            </div>
          @endif

          <form method="POST" action="{{ route('profile.email.request') }}">
            @csrf

            <div class="row">
              <div class="col-md-8 mb-3">
                <label class="form-label">New email</label>
                <input type="email" name="new_email" class="form-control" value="{{ old('new_email') }}" required>
                @error('new_email') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4 d-flex align-items-end mb-3">
                <button class="btn btn-outline-primary w-100" type="submit">
                  <i class="fas fa-envelope"></i> Send confirmation
                </button>
              </div>
            </div>

            <div class="text-muted small">
              We’ll send a confirmation link to the <strong>new</strong> email address. The email won’t change until confirmed.
            </div>
          </form>

        </div>
      </div>

      {{-- Change password --}}
      <div class="card">
        <div class="card-header"><strong>Change Password</strong></div>
        <div class="card-body">

          <form method="POST" action="{{ route('profile.password') }}">
            @csrf
            @method('PATCH')

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
                @error('current_password') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label class="form-label">New Password</label>
                <input id="newPassword" type="password" name="password" class="form-control" required>
                @error('password') <div class="text-danger small">{{ $message }}</div> @enderror

                <div class="progress mt-2" style="height: 8px;">
                  <div id="pwBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
                <div id="pwHint" class="small text-muted mt-1">Use at least 8 characters, mix letters/numbers/symbols.</div>
              </div>

              <div class="col-md-4 mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control" required>
              </div>
            </div>

            <button class="btn btn-primary" type="submit">
              <i class="fas fa-key"></i> Update Password
            </button>
          </form>

        </div>
      </div>

    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><strong>Security</strong></div>
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <span>2FA</span>
            <strong>{{ $user->hasTwoFactorEnabled() ? 'Enabled' : 'Disabled' }}</strong>
          </div>

          <hr>

          <div class="text-muted small">
            Email verified:
            <strong>{{ $user->email_verified_at ? 'Yes' : 'No' }}</strong>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  const input = document.getElementById('newPassword');
  const bar = document.getElementById('pwBar');
  const hint = document.getElementById('pwHint');
  if (!input || !bar || !hint) return;

  function score(pw) {
    let s = 0;
    if (!pw) return 0;
    if (pw.length >= 8) s += 1;
    if (pw.length >= 12) s += 1;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s += 1;
    if (/\d/.test(pw)) s += 1;
    if (/[^A-Za-z0-9]/.test(pw)) s += 1;
    return s; // 0..5
  }

  function update() {
    const pw = input.value || '';
    const s = score(pw);
    const pct = (s / 5) * 100;

    bar.style.width = pct + '%';

    // Use Bootstrap contextual classes
    bar.classList.remove('bg-danger', 'bg-warning', 'bg-info', 'bg-success');

    if (s <= 1) {
      bar.classList.add('bg-danger');
      hint.textContent = 'Weak: add length + numbers + symbols.';
    } else if (s === 2) {
      bar.classList.add('bg-warning');
      hint.textContent = 'Fair: add uppercase/lowercase mix.';
    } else if (s === 3) {
      bar.classList.add('bg-info');
      hint.textContent = 'Good: add more length or symbols.';
    } else {
      bar.classList.add('bg-success');
      hint.textContent = 'Strong password.';
    }
  }

  input.addEventListener('input', update);
  update();
})();
</script>
@endsection
