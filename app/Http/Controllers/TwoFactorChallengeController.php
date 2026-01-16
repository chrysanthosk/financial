<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request)
    {
        if (!session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
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

        $remember = (bool) session('2fa:remember', false);
        session()->forget(['2fa:user:id', '2fa:remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
