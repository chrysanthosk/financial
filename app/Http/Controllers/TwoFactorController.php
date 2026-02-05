<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request)
    {
        if (!session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function cancel(Request $request)
    {
        $user = $request->user();

        $secret = $this->generateBase32Secret(24);

        // Because User model casts two_factor_secret as "encrypted",
        // we store plaintext here and Laravel encrypts it.
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = null;

        // Reset recovery codes until confirmed
        $user->two_factor_recovery_codes = null;

        $user->save();

        $issuer = config('app.name', 'Financial');
        $label = $user->email ?: ('user-' . $user->id);

        $otpauth = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($label),
            $secret,
            rawurlencode($issuer)
        );

        if (!class_exists(Writer::class)) {
            return Redirect::route('profile.2fa.show')
                ->with('status', 'Missing QR library. Run: composer require bacon/bacon-qr-code:^3.0');
        }

        $qrDataUri = $this->makeQrSvgDataUri($otpauth, 220);

        Audit::log(
            action: 'security.2fa_qr_generated',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string)$user->id
        );

        return redirect()->route('login')->with('status', 'Signed out.');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
            'remember_device' => ['nullable', 'boolean'],
        ]);

        $userId = session('2fa:user:id');
        if (!$userId) {
            return redirect()->route('login');
        }

        if (!class_exists(\OTPHP\TOTP::class)) {
            return redirect()->route('login')
                ->with('status', 'Missing OTP library. Run: composer require spomky-labs/otphp');
        }

        // Because of encrypted cast, this is already plaintext
        $secret = (string)$user->two_factor_secret;

        $issuer = config('app.name', 'Financial');
        $label = $user->email ?: ('user-' . $user->id);

        $totp = \OTPHP\TOTP::create($secret);
        $totp->setIssuer($issuer);
        $totp->setLabel($label);

        $code = $request->input('code');
        $now = time();

        $valid =
            $totp->verify($code, $now) ||
            $totp->verify($code, $now - 30) ||
            $totp->verify($code, $now + 30);

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid authentication code.'])->withInput();
        }

        $user->two_factor_confirmed_at = now();

        // Recovery codes as array (encrypted cast will store safely)
        $recoveryCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $recoveryCodes[] = strtoupper(Str::random(10)) . '-' . strtoupper(Str::random(10));
        }
        $user->two_factor_recovery_codes = $recoveryCodes;

        $user->save();

        // If user checked "remember this device" -> trust for N days
        $rememberDevice = (bool)$request->boolean('remember_device');
        if ($rememberDevice) {
            $this->issueTrustedDeviceCookie($request, (int)$user->id);
        }

        return redirect()->intended(route('dashboard'));
    }

    private function trustedCookieName(): string
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        Audit::log(
            action: 'security.2fa_disabled',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string)$user->id
        );

        return Redirect::route('profile.2fa.show')->with('status', '2FA disabled.');
    }

    private function trustDays(): int
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return Redirect::route('profile.2fa.show')->with('status', 'Enable 2FA first.');
        }

        $recoveryCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $recoveryCodes[] = strtoupper(Str::random(10)) . '-' . strtoupper(Str::random(10));
        }

        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->save();

        Audit::log(
            action: 'security.2fa_recovery_regenerated',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string)$user->id
        );

        return Redirect::route('profile.2fa.show')->with('status', 'Recovery codes regenerated. Save the new codes.');
    }

    private function issueTrustedDeviceCookie(Request $request, int $userId): void
    {
        // token stored only in cookie; hash stored in DB
        $token = bin2hex(random_bytes(32)); // 64 chars
        $tokenHash = hash('sha256', $token);

        $row = TwoFactorTrustedDevice::create([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 255),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($this->trustDays()),
        ]);

        // Cookie holds: "{deviceId}.{token}"
        $cookieValue = $row->id . '.' . $token;

        $minutes = $this->trustDays() * 24 * 60;

        Cookie::queue(
            Cookie::make(
                name: $this->trustedCookieName(),
                value: $cookieValue,
                minutes: $minutes,
                path: '/',
                domain: null,
                secure: (bool) config('session.secure', false),
                httpOnly: true,
                raw: false,
                sameSite: 'lax'
            )
        );
    }
}
