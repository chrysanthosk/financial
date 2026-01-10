<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use App\Support\Audit;

class AuditAuthFailed
{
    public function handle(Failed $event): void
    {
        $email = is_array($event->credentials) ? ($event->credentials['email'] ?? null) : null;

        Audit::log(
            action: 'auth.failed',
            category: 'auth',
            request: request(),
            userId: $event->user?->id,
            targetType: 'User',
            targetId: $event->user?->id ? (string)$event->user->id : null,
            meta: [
                'email' => $email,
                'guard' => $event->guard,
            ]
        );
    }
}
