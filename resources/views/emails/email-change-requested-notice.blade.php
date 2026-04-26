<!doctype html>
<html>
  <body>
    <p>Hello {{ $user->full_name ?? $user->email }},</p>

    <p>An email-change request for your account on <strong>{{ $appName }}</strong> was just submitted.</p>

    <p>The new address requested is: <strong>{{ $newEmail }}</strong></p>

    <p>
      If you did <strong>NOT</strong> request this change, please sign in immediately,
      change your password, and contact an administrator. The change will not take
      effect until the link sent to the new address is confirmed.
    </p>
  </body>
</html>
