<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;

class TwoFactorChallengeController extends Controller
{
    private const TRUSTED_COOKIE = 'tfa_trusted_device';
    private const TRUST_DAYS = 30;

    public function show(Request $request)
    {
        if (!session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function cancel(Request $request)
    {
        // user is not authenticated here; just clear pending flags
        $request->session()->forget(['2fa:user:id', '2fa:remember']);
        $request->session()->regenerateToken();

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

        $user = User::find($userId);
        if (!$user) {
            session()->forget(['2fa:user:id', '2fa:remember']);
            return redirect()->route('login');
        }

        $twoFaEnabled = (bool)($user->two_factor_enabled ?? false)
            && !empty($user->two_factor_secret);

        if (!$twoFaEnabled) {
            session()->forget(['2fa:user:id', '2fa:remember']);
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        if (!class_exists(\OTPHP\TOTP::class)) {
            return redirect()->route('login')
                ->with('status', 'Missing OTP library. Run: composer require spomky-labs/otphp');
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

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

        $rememberLogin = (bool) session('2fa:remember', false);
        session()->forget(['2fa:user:id', '2fa:remember']);

        Auth::login($user, $rememberLogin);
        $request->session()->regenerate();

        // If user checked "remember this device" -> trust for 30 days
        $rememberDevice = (bool)$request->boolean('remember_device');
        if ($rememberDevice) {
            $this->issueTrustedDeviceCookie($request, (int)$user->id);
        }

        return redirect()->intended(route('dashboard'));
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
            'expires_at' => now()->addDays(self::TRUST_DAYS),
        ]);

        // Cookie holds: "{deviceId}.{token}"
        $cookieValue = $row->id . '.' . $token;

        $minutes = self::TRUST_DAYS * 24 * 60;

        Cookie::queue(
            Cookie::make(
                name: self::TRUSTED_COOKIE,
                value: $cookieValue,
                minutes: $minutes,
                path: '/',
                domain: null,
                secure: (bool)config('session.secure', false), // secure in https
                httpOnly: true,
                raw: false,
                sameSite: 'lax'
            )
        );
    }
}
