<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataMaintenanceController extends Controller
{
    /**
     * Allowlist of operational targets that may be cleared. Financial and
     * core tables (incomes, expenses, users, migrations, …) are deliberately
     * NOT here and can never be reached by this controller.
     *
     * type: 'table' => DB truncate; 'logfile' => empty the app log file.
     *
     * @return array<string, array<string, string>>
     */
    private function targets(): array
    {
        return [
            'audit_logs' => [
                'label' => 'Audit log',
                'type' => 'table',
                'table' => 'audit_logs',
                'desc' => 'History of user actions. A record of this clear is kept.',
            ],
            'sessions' => [
                'label' => 'Sessions',
                'type' => 'table',
                'table' => 'sessions',
                'desc' => 'Active login sessions — clearing signs everyone out.',
            ],
            'cache' => [
                'label' => 'Cache',
                'type' => 'table',
                'table' => 'cache',
                'desc' => 'Application cache entries.',
            ],
            'failed_jobs' => [
                'label' => 'Failed jobs',
                'type' => 'table',
                'table' => 'failed_jobs',
                'desc' => 'Failed queue jobs.',
            ],
            'app_log' => [
                'label' => 'Application log',
                'type' => 'logfile',
                'desc' => 'The storage/logs application log file.',
            ],
        ];
    }

    public function index()
    {
        $targets = [];

        foreach ($this->targets() as $key => $t) {
            if (($t['type'] ?? null) === 'table') {
                $t['count'] = Schema::hasTable($t['table']) ? DB::table($t['table'])->count() : null;
            } else {
                $path = $this->logPath();
                $t['size'] = is_file($path) ? (int) filesize($path) : 0;
            }

            $targets[$key] = $t;
        }

        return view('admin.settings.maintenance.index', ['targets' => $targets]);
    }

    public function truncate(Request $request, string $key)
    {
        $targets = $this->targets();
        abort_unless(isset($targets[$key]), 404);

        $request->validate(['confirm' => ['required', 'string']]);

        // Require the admin to type the exact target name to confirm.
        if ($request->input('confirm') !== $key) {
            return back()->withErrors(['confirm' => 'Type "'.$key.'" exactly to confirm.']);
        }

        $t = $targets[$key];

        if ($t['type'] === 'table') {
            $count = Schema::hasTable($t['table']) ? DB::table($t['table'])->count() : 0;
            DB::table($t['table'])->truncate();
            $meta = ['target' => $key, 'table' => $t['table'], 'rows_deleted' => $count];
            $message = $t['label']." cleared ({$count} rows).";
        } else {
            $path = $this->logPath();
            $bytes = is_file($path) ? (int) filesize($path) : 0;
            if (is_file($path)) {
                file_put_contents($path, '');
            }
            $meta = ['target' => $key, 'bytes_cleared' => $bytes];
            $message = $t['label'].' cleared.';
        }

        // Logged AFTER the action so clearing the audit log still leaves a
        // record that it was cleared (who/when).
        Audit::log(
            action: 'maintenance.truncated',
            category: 'admin',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'maintenance',
            targetId: $key,
            meta: $meta,
        );

        return back()->with('status', $message);
    }

    private function logPath(): string
    {
        return config('logging.channels.single.path') ?: storage_path('logs/laravel.log');
    }
}
