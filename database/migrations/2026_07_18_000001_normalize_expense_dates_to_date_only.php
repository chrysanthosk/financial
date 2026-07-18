<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expense::$casts used a bare 'date' cast, which on SQLite persisted
 * "Y-m-d H:i:s" into a typeless column. Every inclusive range check in the
 * app compares against a bare "Y-m-d" string, and
 * '2025-01-31 00:00:00' <= '2025-01-31' is false — so expenses dated on the
 * last day of a range were dropped from dashboards, reports and totals.
 *
 * The cast is fixed for new writes; this backfills the existing rows.
 * Done in PHP rather than SQL so it is driver-agnostic and a no-op on
 * engines that already store a real DATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('expenses')
            ->select('id', 'expense_date')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $stored = (string) $row->expense_date;
                    $dateOnly = substr($stored, 0, 10);

                    if ($dateOnly !== $stored) {
                        DB::table('expenses')
                            ->where('id', $row->id)
                            ->update(['expense_date' => $dateOnly]);
                    }
                }
            });

        DB::table('expense_templates')
            ->select('id', 'last_generated_on')
            ->whereNotNull('last_generated_on')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $stored = (string) $row->last_generated_on;
                    $dateOnly = substr($stored, 0, 10);

                    if ($dateOnly !== $stored) {
                        DB::table('expense_templates')
                            ->where('id', $row->id)
                            ->update(['last_generated_on' => $dateOnly]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Irreversible by design: the discarded time component was always
        // 00:00:00 padding, never real data.
    }
};
