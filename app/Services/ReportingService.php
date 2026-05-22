<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use Illuminate\Support\Carbon;

/**
 * Shared building blocks for the reporting pages. Centralizes the year-range,
 * month-label and month-bucketing logic that was previously duplicated across
 * almost every method of ReportsController.
 */
class ReportingService
{
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
     *
     * @param  iterable<object>  $rows
     * @return array<int, float>
     */
    public function bucketByMonth(iterable $rows, string $dateField, string $amountField = 'amount'): array
    {
        $byMonth = array_fill(1, 12, 0.0);

        foreach ($rows as $r) {
            $m = Carbon::parse($r->{$dateField})->month;
            $byMonth[$m] += (float) $r->{$amountField};
        }

        return $byMonth;
    }

    /**
     * Resolve an optional from/to range, falling back to the full year.
     *
     * @return array{from: string, to: string}
     */
    public function resolveDateRange(int $year, ?string $from, ?string $to): array
    {
        $fallbackFrom = Carbon::create($year, 1, 1)->startOfDay()->toDateString();
        $fallbackTo = Carbon::create($year, 12, 31)->endOfDay()->toDateString();

        $start = ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) ? $from : $fallbackFrom;
        $end = ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) ? $to : $fallbackTo;

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['from' => $start, 'to' => $end];
    }

    /**
     * Income / expense / profit totals for a full year.
     *
     * @return array{income: float, expenses: float, profit: float}
     */
    public function yearTotals(int $year): array
    {
        ['from' => $from, 'to' => $to] = $this->yearRange($year);

        $income = (float) Income::whereBetween('income_date', [$from, $to])->sum('amount');
        $expenses = (float) Expense::whereBetween('expense_date', [$from, $to])->sum('amount');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'profit' => $income - $expenses,
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
            $profit[$m] = $incomeByMonth[$m] - $expenseByMonth[$m];
        }

        return $profit;
    }
}
