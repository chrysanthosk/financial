<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],

            // We validate email here only if your form submits it.
            // If your profile edit does NOT submit email, you can remove this rule entirely.
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $oldFirst = $user->first_name;
        $oldLast = $user->last_name;

        $user->first_name = $validated['first_name'] ?? null;
        $user->last_name = $validated['last_name'] ?? null;

        // Keep "name" compatible (navbar, etc.)
        $user->name = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->name ?: 'User');

        // DO NOT set $user->email here (handled by EmailChangeController flow)
        $user->save();

        Audit::log(
            action: 'profile.updated',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string) $user->id,
            meta: [
                'changed' => [
                    'first_name' => $oldFirst !== $user->first_name,
                    'last_name' => $oldLast !== $user->last_name,
                ],
            ]
        );

        return Redirect::route('profile.edit')->with('status', 'Profile updated.');
    }

    public function password(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($validated['password']);
        $user->save();

        // A password change should invalidate previously trusted 2FA devices.
        $user->revokeTrustedDevices();
        Cookie::queue(Cookie::forget(config('twofactor.cookie.name', 'tfa_trusted_device')));

        Audit::log(
            action: 'profile.password_changed',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string) $user->id
        );

        return Redirect::route('profile.edit')->with('status', 'Password updated.');
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Audit::log(
            action: 'profile.deleted',
            category: 'security',
            request: $request,
            userId: $user->id,
            targetType: 'User',
            targetId: (string) $user->id
        );

        auth()->logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
