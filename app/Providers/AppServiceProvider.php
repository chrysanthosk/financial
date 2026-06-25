<?php

namespace App\Providers;

use App\Models\SmtpSetting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Use Bootstrap pagination views (AdminLTE/Bootstrap compatible).
         * Fixes the "huge arrows" issue caused by Tailwind pagination templates.
         */
        Paginator::useBootstrapFive();

        // Apply SMTP settings from DB (best-effort, safe)
        try {
            if (Schema::hasTable('smtp_settings')) {
                $s = SmtpSetting::current();

                if ($s && $s->enabled && $s->host && $s->port) {
                    config([
                        'mail.default' => 'smtp',
                        'mail.mailers.smtp.host' => $s->host,
                        'mail.mailers.smtp.port' => (int) $s->port,
                        'mail.mailers.smtp.username' => $s->username ?: null,
                        'mail.mailers.smtp.password' => $s->password ?: null,
                        'mail.mailers.smtp.encryption' => $s->encryption ?: null,
                    ]);

                    if (! empty($s->from_address)) {
                        config(['mail.from.address' => $s->from_address]);
                    }
                    if (! empty($s->from_name)) {
                        config(['mail.from.name' => $s->from_name]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Don't break the app if DB isn't ready yet
        }
    }
}
