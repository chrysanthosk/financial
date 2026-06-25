<?php

namespace App\Listeners;

use App\Support\Audit;
use Illuminate\Auth\Events\Logout;

class AuditAuthLogout
{
    public function handle(Logout $event): void
    {
        Audit::log(
            action: 'auth.logout',
            category: 'auth',
            request: request(),
            userId: $event->user?->id,
            targetType: 'User',
            targetId: (string) ($event->user?->id),
            meta: ['guard' => $event->guard]
        );
    }
}
