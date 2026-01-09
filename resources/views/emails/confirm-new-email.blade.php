<!doctype html>
<html>
  <body>
    <p>Hello {{ $user->full_name ?? $user->email }},</p>

    <p>You requested to change your email address on <strong>{{ config('app.name') }}</strong>.</p>

    <p>Click the link below to confirm your new email:</p>

    <p>
      <a href="{{ $confirmUrl }}">{{ $confirmUrl }}</a>
    </p>

    <p>If you didn’t request this change, you can ignore this email.</p>
  </body>
</html>
