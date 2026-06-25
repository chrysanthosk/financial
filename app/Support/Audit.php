<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public static function log(
        string $action,
        string $category = 'app',
        ?Request $request = null,
        ?int $userId = null,
        ?string $targetType = null,
        ?string $targetId = null,
        array $meta = []
    ): void {
        try {
            $req = $request ?? request();

            AuditLog::create([
                'user_id' => $userId ?? (Auth::check() ? Auth::id() : null),
                'action' => $action,
                'category' => $category,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'ip' => $req?->ip(),
                'user_agent' => substr((string) ($req?->userAgent()), 0, 255),
                'meta' => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            // best-effort: never break the request due to audit logging
        }
    }
}
