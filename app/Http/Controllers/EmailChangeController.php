<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmNewEmail;
use App\Mail\EmailChangeRequestedNotice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailChangeController extends Controller
{
    public function requestChange(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $token = Str::random(64);

        $user->pending_email = $validated['new_email'];
        $user->pending_email_token = hash('sha256', $token);
        $user->pending_email_requested_at = now();
        $user->save();

        Mail::to($user->pending_email)->send(new ConfirmNewEmail($user, $token));

        if ($user->email) {
            Mail::to($user->email)->send(new EmailChangeRequestedNotice($user, $user->pending_email));
        }

        return back()->with('status', 'We sent a confirmation link to your new email address.');
    }

    public function confirm(Request $request, string $token)
    {
        $user = $request->user();

        if (! $user->pending_email || ! $user->pending_email_token) {
            return redirect()->route('profile.edit')->with('status', 'No pending email change found.');
        }

        $hash = hash('sha256', $token);

        if (! hash_equals($user->pending_email_token, $hash)) {
            abort(403, 'Invalid email confirmation token.');
        }

        // Optional expiry (e.g., 60 minutes)
        if ($user->pending_email_requested_at && $user->pending_email_requested_at->lt(now()->subMinutes(60))) {
            $user->pending_email = null;
            $user->pending_email_token = null;
            $user->pending_email_requested_at = null;
            $user->save();

            return redirect()->route('profile.edit')->with('status', 'Email confirmation link expired. Please try again.');
        }

        // Re-check uniqueness at confirmation time: another account may have
        // taken this address between the request and the confirmation (TOCTOU).
        $taken = User::where('email', $user->pending_email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            $user->pending_email = null;
            $user->pending_email_token = null;
            $user->pending_email_requested_at = null;
            $user->save();

            return redirect()->route('profile.edit')
                ->with('status', 'That email address is no longer available. Please try a different one.');
        }

        $user->email = $user->pending_email;
        $user->email_verified_at = null;

        $user->pending_email = null;
        $user->pending_email_token = null;
        $user->pending_email_requested_at = null;

        $user->save();

        return redirect()->route('profile.edit')->with('status', 'Email updated. Please verify your email if required.');
    }
}
