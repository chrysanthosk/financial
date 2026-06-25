<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Redirect;

class TwoFactorController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return view('profile.2fa', [
            'user' => $user,
            'qrPngDataUri' => null,
            'secret' => null,
        ]);
    }

    public function enable(Request $request)
    {
        $user = $request->user();

        $secret = $this->generateBase32Secret(24);

        // User model casts two_factor_secret as encrypted => assign plaintext
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = null;

        // Reset recovery codes until confirmed
        $user->two_factor_recovery_codes = null;

        $user->save();

        $issuer = config('app.name', 'Financial');
        $label = $user->email ?: ('user-'.$user->id);

        $otpauth = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($label),
            $secret,
            rawurlencode($issuer)
        );

        if (! class_exists(Writer::class)) {
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
            targetId: (string) $user->id
        );

        return view('profile.2fa', [
            'user' => $user,
            'qrPngDataUri' => $qrDataUri,
            'secret' => $secret,
        ]);
    }

    public function confirm(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! $user->two_factor_secret) {
            return Redirect::route('profile.2fa.show')->with('status', 'Please generate a QR first.');
        }

        if (! class_exists(\OTPHP\TOTP::class)) {
            return Redirect::route('profile.2fa.show')
                ->with('status', 'Missing OTP library. Run: composer require spomky-labs/otphp');
        }

        // encrypted cast => already plaintext
        $secret = (string) $user->two_factor_secret;

        $issuer = config('app.name', 'Financial');
        $label = $user->email ?: ('user-'.$user->id);

        $totp = \OTPHP\TOTP::create($secret);
        $totp->setIssuer($issuer);
        $totp->setLabel($label);

        $code = $request->input('code');
        $now = time();

        $valid =
            $totp->verify($code, $now) ||
            $totp->verify($code, $now - 30) ||
            $totp->verify($code, $now + 30);

        if (! $valid) {
            Audit::log(
                action: 'security.2fa_confirm_failed',
                category: 'security',
                request: $request,
                userId: $user->id,
                targetType: 'User',
                targetId: (string) $user->id
            );

            return Redirect::back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $user->two_factor_confirmed_at = now();

        // Only hashes are stored; plaintext is flashed for one-time display.
        $recoveryCodes = $user->generateRecoveryCodes();
        $user->save();

        Audit::log(
            action: 'security.2fa_enabled',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string) $user->id
        );

        return Redirect::route('profile.2fa.show')
            ->with('status', '2FA enabled. Save your recovery codes now — they will not be shown again.')
            ->with('recoveryCodes', $recoveryCodes);
    }

    public function disable(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_last_used_counter = null;
        $user->save();

        // Revoke any "remember this device" tokens so a captured cookie can't
        // bypass 2FA after it has been turned off.
        $user->revokeTrustedDevices();
        Cookie::queue(Cookie::forget(config('twofactor.cookie.name', 'tfa_trusted_device')));

        Audit::log(
            action: 'security.2fa_disabled',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string) $user->id
        );

        return Redirect::route('profile.2fa.show')->with('status', '2FA disabled.');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return Redirect::route('profile.2fa.show')->with('status', 'Enable 2FA first.');
        }

        // Only hashes are stored; plaintext is flashed for one-time display.
        $recoveryCodes = $user->generateRecoveryCodes();
        $user->save();

        Audit::log(
            action: 'security.2fa_recovery_regenerated',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string) $user->id
        );

        return Redirect::route('profile.2fa.show')
            ->with('status', 'Recovery codes regenerated. Save the new codes now — they will not be shown again.')
            ->with('recoveryCodes', $recoveryCodes);
    }

    private function makeQrSvgDataUri(string $text, int $size = 220): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd);
        $writer = new Writer($renderer);

        $svg = $writer->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function generateBase32Secret(int $length = 24): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }
}
