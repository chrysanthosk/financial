<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorTrustedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthenticatedSessionController extends Controller
{
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

        // If 2FA is enabled -> potentially force challenge
        $twoFaEnabled = $user && method_exists($user, 'hasTwoFactorEnabled')
            ? $user->hasTwoFactorEnabled()
            : false;

        if ($twoFaEnabled) {
            // If trusted device cookie is valid -> skip challenge
            if ($this->trustedDeviceIsValidForUser($request, (int)$user->id)) {
                return redirect()->intended(route('dashboard'));
            }

            // Mark this session as pending 2FA verification
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);

            // Log out user until challenge is satisfied
            Auth::logout();

            // Keep ONLY the "pending 2fa" session data
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Re-store pending flags after invalidation
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);

            return redirect()->route('two-factor.challenge.show');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        // Optional (recommended): logging out should "untrust" this browser
        Cookie::queue(Cookie::forget($this->trustedCookieName()));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function trustedCookieName(): string
    {
        return (string) config('twofactor.cookie.name', 'tfa_trusted_device');
    }

    private function trustedDeviceIsValidForUser(Request $request, int $userId): bool
    {
        $cookie = $request->cookie($this->trustedCookieName());
        if (!$cookie) {
            return false;
        }

        // Cookie format: "{deviceId}.{token}"
        $parts = explode('.', (string)$cookie, 2);
        if (count($parts) !== 2) {
            Cookie::queue(Cookie::forget($this->trustedCookieName()));
            return false;
        }

        [$deviceIdRaw, $token] = $parts;

        if (!ctype_digit($deviceIdRaw) || $token === '') {
            Cookie::queue(Cookie::forget($this->trustedCookieName()));
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
            Cookie::queue(Cookie::forget($this->trustedCookieName()));
            return false;
        }

        if (!hash_equals((string)$row->token_hash, (string)$tokenHash)) {
            Cookie::queue(Cookie::forget($this->trustedCookieName()));
            return false;
        }

        // Touch last_used_at
        $row->last_used_at = now();
        $row->save();

        return true;
    }
}
