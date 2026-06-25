<?php

namespace App\Listeners;

use App\Support\Audit;
use Illuminate\Auth\Events\Login;

class AuditAuthLogin
{
    public function handle(Login $event): void
    {
        Audit::log(
            action: 'auth.login',
            category: 'auth',
            request: request(),
            userId: $event->user?->id,
            targetType: 'User',
            targetId: (string) ($event->user?->id),
            meta: ['guard' => $event->guard]
        );
    }
}
