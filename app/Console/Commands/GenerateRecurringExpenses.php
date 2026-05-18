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
    protected $signature   = 'expenses:generate-recurring {--dry-run : Show what would be generated without writing}';
    protected $description = 'Materialize active expense templates with auto_create=true into expenses, with month-level catch-up.';

    public function handle(): int
    {
        $today        = Carbon::today();
        $monthStart   = $today->copy()->startOfMonth();
        $monthEnd     = $today->copy()->endOfMonth();
        $dryRun       = (bool)$this->option('dry-run');

        $templates = ExpenseTemplate::query()
            ->where('is_active', true)
            ->where('auto_create', true)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($templates as $tpl) {
            // Already generated this calendar month? skip.
            if ($tpl->last_generated_on && $tpl->last_generated_on->greaterThanOrEqualTo($monthStart)) {
                $skipped++;
                continue;
            }

            // Only fire once we're at or past the configured day-of-month.
            // day_of_month is 1-28 per validation, so clamping isn't needed.
            if ($today->day < (int)$tpl->day_of_month) {
                $skipped++;
                continue;
            }

            $targetDate = $monthStart->copy()->addDays((int)$tpl->day_of_month - 1);
            if ($targetDate->greaterThan($monthEnd)) {
                $targetDate = $monthEnd->copy();
            }

            $this->line(sprintf(
                '%s template #%d "%s" (€%s) on %s',
                $dryRun ? '[dry-run]' : '[create]',
                $tpl->id,
                $tpl->name,
                number_format((float)$tpl->amount, 2),
                $targetDate->toDateString()
            ));

            if ($dryRun) {
                $created++;
                continue;
            }

            DB::transaction(function () use ($tpl, $targetDate) {
                $expense = Expense::create($tpl->toExpenseAttributes($targetDate, $tpl->created_by));
                $tpl->forceFill(['last_generated_on' => $targetDate])->save();

                Audit::log(
                    action: 'expense_template.inserted',
                    category: 'expenses',
                    request: null,
                    userId: $tpl->created_by,
                    targetType: 'Expense',
                    targetId: (string)$expense->id,
                    meta: [
                        'template_id' => $tpl->id,
                        'amount'      => (float)$expense->amount,
                        'payee_name'  => $expense->payee_name,
                        'source'      => 'auto',
                    ]
                );
            });

            $created++;
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
