<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmtpSetting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SmtpSettingsController extends Controller
{
    public function edit()
    {
        $missingTable = false;
        $smtp = null;

        try {
            if (\Schema::hasTable('smtp_settings')) {
                $smtp = SmtpSetting::current(); // should return first row (or create default if your model does that)
            } else {
                $missingTable = true;
            }
        } catch (\Throwable $e) {
            $missingTable = true;
        }

        return view('admin.settings.smtp', [
            'smtp' => $smtp,
            'missingTable' => $missingTable,
        ]);
    }

    public function update(Request $request)
    {
        if (! \Schema::hasTable('smtp_settings')) {
            return back()->withErrors([
                'smtp_test' => 'SMTP settings table is missing. Please run migrations.',
            ]);
        }

        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2048'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Use currentOrCreate so the first save on a fresh install (no row yet)
        // does not assign properties on null.
        $smtp = SmtpSetting::currentOrCreate();

        // Assign everything except password first
        $smtp->enabled = (bool) ($validated['enabled'] ?? false);
        $smtp->host = $validated['host'] ?? null;
        $smtp->port = $validated['port'] ?? null;
        $smtp->username = $validated['username'] ?? null;
        $smtp->encryption = $validated['encryption'] ?? null;
        $smtp->from_address = $validated['from_address'] ?? null;
        $smtp->from_name = $validated['from_name'] ?? null;

        // Only update password if provided (so you can keep existing)
        if (! empty($validated['password'])) {
            $smtp->password = $validated['password']; // encrypted cast handles it
        }

        $smtp->save();

        Audit::log(
            action: 'smtp.updated',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'SmtpSetting',
            targetId: (string) $smtp->id,
            meta: [
                'enabled' => $smtp->enabled,
                'host' => $smtp->host,
                'port' => $smtp->port,
                'encryption' => $smtp->encryption,
                'from_address' => $smtp->from_address,
                'from_name' => $smtp->from_name,
                'password_changed' => ! empty($validated['password']),
            ]
        );

        return back()->with('status', 'SMTP settings updated.');
    }

    /**
     * Send a test email using the saved SMTP settings.
     * Route: admin.settings.smtp.test
     */
    public function test(Request $request)
    {
        if (! \Schema::hasTable('smtp_settings')) {
            return back()->withErrors([
                'smtp_test' => 'SMTP settings table is missing. Please run migrations.',
            ]);
        }

        $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $smtp = SmtpSetting::current();

        if (! $smtp || ! $smtp->enabled) {
            return back()->withErrors([
                'smtp_test' => 'SMTP is disabled. Enable it first.',
            ]);
        }

        if (empty($smtp->host) || empty($smtp->port)) {
            return back()->withErrors([
                'smtp_test' => 'SMTP host/port are required to send a test email.',
            ]);
        }

        $to = $request->input('test_email');

        try {
            Mail::raw(
                'This is a test email from '.($smtp->from_name ?: config('app.name')).'.',
                function ($message) use ($to, $smtp) {
                    // Explicit FROM if set (good for many SMTP providers)
                    if (! empty($smtp->from_address)) {
                        $message->from($smtp->from_address, $smtp->from_name ?: config('app.name'));
                    }

                    $message->to($to)->subject('SMTP Test Email');
                }
            );

            // Store last tested timestamp
            $smtp->last_tested_at = now();
            $smtp->save();

            Audit::log(
                action: 'smtp.test_sent',
                category: 'settings',
                request: $request,
                userId: $request->user()?->id,
                targetType: 'SmtpSetting',
                targetId: (string) $smtp->id,
                meta: [
                    'to' => $to,
                    'host' => $smtp->host,
                    'port' => $smtp->port,
                    'encryption' => $smtp->encryption,
                ]
            );

        } catch (\Throwable $e) {
            Audit::log(
                action: 'smtp.test_failed',
                category: 'settings',
                request: $request,
                userId: $request->user()?->id,
                targetType: 'SmtpSetting',
                targetId: (string) $smtp->id,
                meta: [
                    'to' => $to,
                    'error' => $e->getMessage(),
                ]
            );

            return back()->withErrors([
                'smtp_test' => 'SMTP test failed. Check the audit log for details.',
            ]);
        }

        return back()->with('status', "Test email sent successfully to {$to}.");
    }
}
