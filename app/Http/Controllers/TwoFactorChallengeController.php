<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class TwoFactorChallengeController extends Controller
{
    private const TRUSTED_COOKIE = 'tfa_trusted_device';
    private const TRUST_DAYS = 30;

    public function show(Request $request)
    {
        if (!$request->session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function cancel(Request $request)
    {
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

        $userId = (int) $request->session()->get('2fa:user:id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['2fa:user:id', '2fa:remember']);
            return redirect()->route('login');
        }

        // If 2FA not enabled anymore, just log in
        if (!$user->hasTwoFactorEnabled()) {
            $request->session()->forget(['2fa:user:id', '2fa:remember']);
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        if (!class_exists(\OTPHP\TOTP::class)) {
            return redirect()->route('login')
                ->with('status', 'Missing OTP library. Run: composer require spomky-labs/otphp');
        }

        // Because of encrypted cast in User, this is already plaintext
        $secret = (string) $user->two_factor_secret;

        $issuer = config('app.name', 'Financial');
        $label = $user->email ?: ('user-' . $user->id);

        $totp = \OTPHP\TOTP::create($secret);
        $totp->setIssuer($issuer);
        $totp->setLabel($label);

        $code = $request->input('code');
        $now = time();

        // Allow +/- 30s drift
        $valid =
            $totp->verify($code, $now) ||
            $totp->verify($code, $now - 30) ||
            $totp->verify($code, $now + 30);

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid authentication code.'])->withInput();
        }

        $rememberLogin = (bool) $request->session()->get('2fa:remember', false);
        $request->session()->forget(['2fa:user:id', '2fa:remember']);

        Auth::login($user, $rememberLogin);
        $request->session()->regenerate();

        if ($request->boolean('remember_device')) {
            $this->issueTrustedDeviceCookie($request, (int)$user->id);
        }

        return redirect()->intended(route('dashboard'));
    }

    private function issueTrustedDeviceCookie(Request $request, int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $row = TwoFactorTrustedDevice::create([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 255),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::TRUST_DAYS),
        ]);

        $cookieValue = $row->id . '.' . $token;
        $minutes = self::TRUST_DAYS * 24 * 60;

        Cookie::queue(
            Cookie::make(
                name: self::TRUSTED_COOKIE,
                value: $cookieValue,
                minutes: $minutes,
                path: '/',
                domain: null,
                secure: true,     // you are HTTPS
                httpOnly: true,
                raw: false,
                sameSite: 'lax'
            )
        );
    }
}
