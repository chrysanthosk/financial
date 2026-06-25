<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\ExpenseTemplate;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring {--dry-run : Show what would be generated without writing}';

    protected $description = 'Materialize active expense templates with auto_create=true into expenses, with month-level catch-up.';

    /**
     * Safety cap on how many months a single template will backfill in one run,
     * so a very stale (or never-generated) template can't flood the ledger.
     */
    private const MAX_CATCHUP_MONTHS = 36;

    public function handle(): int
    {
        $today = Carbon::today();
        $currentMonthStart = $today->copy()->startOfMonth();
        $dryRun = (bool) $this->option('dry-run');

        $templates = ExpenseTemplate::query()
            ->where('is_active', true)
            ->where('auto_create', true)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($templates as $tpl) {
            $day = (int) $tpl->day_of_month; // 1-28 per validation, no clamping needed

            // First month to consider: the month *after* the last generated one,
            // or the current month if this template has never generated. This
            // backfills any months missed while the scheduler was not running.
            $cursor = $tpl->last_generated_on
                ? $tpl->last_generated_on->copy()->startOfMonth()->addMonth()
                : $currentMonthStart->copy();

            $generatedForTpl = 0;
            $iterations = 0;

            while ($cursor->lessThanOrEqualTo($currentMonthStart) && $iterations < self::MAX_CATCHUP_MONTHS) {
                $iterations++;

                $monthEnd = $cursor->copy()->endOfMonth();
                $targetDate = $cursor->copy()->addDays($day - 1);
                if ($targetDate->greaterThan($monthEnd)) {
                    $targetDate = $monthEnd->copy();
                }

                // Don't generate a month whose target day hasn't arrived yet
                // (only ever true for the current month). Stop walking forward.
                if ($targetDate->greaterThan($today)) {
                    break;
                }

                $this->line(sprintf(
                    '%s template #%d "%s" (€%s) on %s',
                    $dryRun ? '[dry-run]' : '[create]',
                    $tpl->id,
                    $tpl->name,
                    number_format((float) $tpl->amount, 2),
                    $targetDate->toDateString()
                ));

                if (! $dryRun) {
                    DB::transaction(function () use ($tpl, $targetDate) {
                        $expense = Expense::create($tpl->toExpenseAttributes($targetDate, $tpl->created_by));
                        $tpl->forceFill(['last_generated_on' => $targetDate])->save();

                        Audit::log(
                            action: 'expense_template.inserted',
                            category: 'expenses',
                            request: null,
                            userId: $tpl->created_by,
                            targetType: 'Expense',
                            targetId: (string) $expense->id,
                            meta: [
                                'template_id' => $tpl->id,
                                'amount' => (float) $expense->amount,
                                'payee_name' => $expense->payee_name,
                                'source' => 'auto',
                            ]
                        );
                    });
                }

                $created++;
                $generatedForTpl++;
                $cursor->addMonth();
            }

            if ($generatedForTpl === 0) {
                $skipped++;
            }
        }

        $this->info(sprintf(
            'Done. created=%d skipped=%d total_active_auto=%d',
            $created,
            $skipped,
            $templates->count()
        ));

        return self::SUCCESS;
    }
}
