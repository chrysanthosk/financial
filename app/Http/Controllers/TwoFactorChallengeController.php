<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorTrustedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthenticatedSessionController extends Controller
{
    private const TRUSTED_COOKIE = 'tfa_trusted_device';

    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (!Auth::attempt($request->only('email', 'password'), $remember)) {
            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user && method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            if ($this->trustedDeviceIsValidForUser($request, (int)$user->id)) {
                return redirect()->intended(route('dashboard'));
            }

            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);

            return redirect()->route('two-factor.challenge.show');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        Cookie::queue(Cookie::forget(self::TRUSTED_COOKIE));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function trustedDeviceIsValidForUser(Request $request, int $userId): bool
    {
        $cookie = $request->cookie(self::TRUSTED_COOKIE);
        if (!$cookie) return false;

        $parts = explode('.', (string)$cookie, 2);
        if (count($parts) !== 2) {
            Cookie::queue(Cookie::forget(self::TRUSTED_COOKIE));
            return false;
        }

        [$deviceIdRaw, $token] = $parts;

        if (!ctype_digit($deviceIdRaw) || $token === '') {
            Cookie::queue(Cookie::forget(self::TRUSTED_COOKIE));
            return false;
        }

        $deviceId = (int)$deviceIdRaw;
        $tokenHash = hash('sha256', $token);

        $row = TwoFactorTrustedDevice::query()
            ->where('id', $deviceId)
            ->where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            Cookie::queue(Cookie::forget(self::TRUSTED_COOKIE));
            return false;
        }

        if (!hash_equals((string)$row->token_hash, (string)$tokenHash)) {
            Cookie::queue(Cookie::forget(self::TRUSTED_COOKIE));
            return false;
        }

        $row->last_used_at = now();
        $row->save();

        return true;
    }
}
