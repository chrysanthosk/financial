<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use Illuminate\Support\Carbon;

/**
 * Shared building blocks for the reporting pages. Centralizes the year-range,
 * month-label and month-bucketing logic that was previously duplicated across
 * almost every method of ReportsController.
 *
 * Money is aggregated in integer cents to avoid binary-float rounding drift,
 * then converted back to 2-decimal floats for the views.
 */
class ReportingService
{
    /**
     * Convert a stored decimal amount to integer cents.
     */
    public function toCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Convert integer cents back to a 2-decimal float.
     */
    public function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Short month labels (Jan..Dec) for a given year.
     *
     * @return array<int, string>
     */
    public function monthLabels(int $year): array
    {
        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->format('M');
        }

        return $labels;
    }

    /**
     * Inclusive full-year date range as Y-m-d strings.
     *
     * @return array{from: string, to: string}
     */
    public function yearRange(int $year): array
    {
        return [
            'from' => Carbon::create($year, 1, 1)->toDateString(),
            'to' => Carbon::create($year, 12, 31)->toDateString(),
        ];
    }

    /**
     * Bucket a row collection into [1..12 => float] by the month of $dateField.
     * Accumulates in integer cents for exact totals.
     *
     * @param  iterable<object>  $rows
     * @return array<int, float>
     */
    public function bucketByMonth(iterable $rows, string $dateField, string $amountField = 'amount'): array
    {
        $cents = array_fill(1, 12, 0);

        foreach ($rows as $r) {
            $m = Carbon::parse($r->{$dateField})->month;
            $cents[$m] += $this->toCents($r->{$amountField});
        }

        return array_map(fn (int $c) => $this->fromCents($c), $cents);
    }

    /**
     * Aggregate rows into [label => float total], sorted by total descending.
     * Accumulates in integer cents for exact totals.
     *
     * @param  iterable<object>  $rows
     * @param  callable(object): string  $labelResolver
     * @return array<string, float>
     */
    public function sumByLabel(iterable $rows, callable $labelResolver, string $amountField = 'amount'): array
    {
        $cents = [];

        foreach ($rows as $r) {
            $label = $labelResolver($r);
            $cents[$label] = ($cents[$label] ?? 0) + $this->toCents($r->{$amountField});
        }

        arsort($cents);

        return array_map(fn (int $c) => $this->fromCents($c), $cents);
    }

    /**
     * Resolve an optional from/to range, falling back to the full year.
     *
     * @return array{from: string, to: string}
     */
    public function resolveDateRange(int $year, ?string $from, ?string $to): array
    {
        $fallbackFrom = Carbon::create($year, 1, 1)->toDateString();
        $fallbackTo = Carbon::create($year, 12, 31)->toDateString();

        $start = ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) ? $from : $fallbackFrom;
        $end = ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) ? $to : $fallbackTo;

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['from' => $start, 'to' => $end];
    }

    /**
     * Income / expense / profit totals for a full year. DB SUM is exact for
     * decimals; results are rounded to guard the final subtraction.
     *
     * @return array{income: float, expenses: float, profit: float}
     */
    public function yearTotals(int $year): array
    {
        ['from' => $from, 'to' => $to] = $this->yearRange($year);

        $income = round((float) Income::whereBetween('income_date', [$from, $to])->sum('amount'), 2);
        $expenses = round((float) Expense::whereBetween('expense_date', [$from, $to])->sum('amount'), 2);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'profit' => round($income - $expenses, 2),
        ];
    }

    /**
     * Monthly profit [1..12 => float] for a year.
     *
     * @return array<int, float>
     */
    public function profitByMonth(int $year): array
    {
        ['from' => $from, 'to' => $to] = $this->yearRange($year);

        $incomeByMonth = $this->bucketByMonth(
            Income::whereBetween('income_date', [$from, $to])->get(['income_date', 'amount']),
            'income_date'
        );
        $expenseByMonth = $this->bucketByMonth(
            Expense::whereBetween('expense_date', [$from, $to])->get(['expense_date', 'amount']),
            'expense_date'
        );

        $profit = [];
        for ($m = 1; $m <= 12; $m++) {
            $profit[$m] = round($incomeByMonth[$m] - $expenseByMonth[$m], 2);
        }

        return $profit;
    }
}
