<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class TwoFactorChallengeController extends Controller
{
    private const TRUSTED_COOKIE = 'tfa_trusted_device';

    public function show(Request $request)
    {
        // If there's no pending 2FA user, go back to login
        if (! $request->session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        // IMPORTANT: match your blade location/name
        // You showed: two-factor-challenge.blade.php
        // If it is located at resources/views/auth/two-factor-challenge.blade.php:
        // return view('auth.two-factor-challenge');
        //
        // If it is located at resources/views/two-factor-challenge.blade.php:
        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        // Accepts EITHER a 6-digit TOTP code OR a recovery code, so the
        // exact format is validated below once we know which path applies.
        $request->validate([
            'code' => ['required', 'string'],
            'remember_device' => ['nullable', 'boolean'],
        ]);

        $userId = (int) $request->session()->get('2fa:user:id', 0);
        $remember = (bool) $request->session()->get('2fa:remember', false);

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (! $user) {
            return redirect()->route('login');
        }

        // Must have OTP library
        if (! class_exists(\OTPHP\TOTP::class)) {
            return back()->withErrors(['code' => 'OTP library missing (spomky-labs/otphp).']);
        }

        // Must have secret + confirmed
        if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
            return redirect()->route('login');
        }

        $code = trim((string) $request->input('code'));
        $viaRecovery = false;

        if (preg_match('/^\d{6}$/', $code)) {
            // TOTP path
            $secret = (string) $user->two_factor_secret;

            $issuer = config('app.name', 'Financial');
            $label = $user->email ?: ('user-'.$user->id);

            $totp = \OTPHP\TOTP::create($secret);
            $totp->setIssuer($issuer);
            $totp->setLabel($label);

            $now = time();

            // Allow ±1 step for clock skew, but remember which step matched so a
            // used code cannot be replayed within its validity window.
            $matchedAt = null;
            foreach ([$now, $now - 30, $now + 30] as $ts) {
                if ($totp->verify($code, $ts)) {
                    $matchedAt = $ts;
                    break;
                }
            }

            $valid = $matchedAt !== null;

            if ($valid) {
                $counter = intdiv($matchedAt, 30);

                if ($user->two_factor_last_used_counter !== null
                    && $counter <= (int) $user->two_factor_last_used_counter) {
                    // Code (or an earlier one) already consumed — reject replay.
                    $valid = false;
                } else {
                    $user->two_factor_last_used_counter = $counter;
                    $user->save();
                }
            }
        } else {
            // Recovery-code path: single-use, consumed on success.
            $valid = $user->useRecoveryCode($code);

            if ($valid) {
                $user->save();
                $viaRecovery = true;

                Audit::log(
                    action: 'security.2fa_recovery_used',
                    category: 'security',
                    request: $request,
                    userId: $user->id,
                    targetType: 'User',
                    targetId: (string) $user->id,
                    meta: ['remaining' => $user->recoveryCodesRemaining()]
                );
            }
        }

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        // Login the user after successful challenge
        Auth::login($user, $remember);

        // Optionally remember device
        if ($request->boolean('remember_device')) {
            $token = Str::random(64);

            $row = TwoFactorTrustedDevice::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(30),
                'last_used_at' => now(),
            ]);

            Cookie::queue(cookie(
                self::TRUSTED_COOKIE,
                $row->id.'.'.$token,
                60 * 24 * 30, // 30 days
                null,
                null,
                true,
                true,
                false,
                'Lax'
            ));
        }

        // Clear pending session flags
        $request->session()->forget(['2fa:user:id', '2fa:remember']);

        $redirect = redirect()->intended(route('dashboard'));

        if ($viaRecovery) {
            $redirect->with('status', 'You signed in with a recovery code. '.$user->recoveryCodesRemaining().' codes remain — consider regenerating them.');
        }

        return $redirect;
    }

    public function cancel(Request $request)
    {
        // Cancel challenge and force back to login
        $request->session()->forget(['2fa:user:id', '2fa:remember']);

        return redirect()->route('login');
    }
}
