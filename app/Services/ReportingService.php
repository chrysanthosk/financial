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
     * Bucket a row collection into [year => float] by the year of $dateField,
     * keeping only the years within [$startYear, $endYear] (missing years => 0).
     * Accumulates in integer cents for exact totals.
     *
     * @param  iterable<object>  $rows
     * @return array<int, float>
     */
    public function bucketByYear(iterable $rows, string $dateField, int $startYear, int $endYear, string $amountField = 'amount'): array
    {
        $cents = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            $cents[$y] = 0;
        }

        foreach ($rows as $r) {
            $y = Carbon::parse($r->{$dateField})->year;
            if (isset($cents[$y])) {
                $cents[$y] += $this->toCents($r->{$amountField});
            }
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
     * Company income / expenses / profit per year across a span ending at
     * $endYear (inclusive). Rows are ordered oldest → newest. Years without
     * activity are still returned with zero totals so the series is contiguous.
     *
     * @return array<int, array{year: int, income: float, expenses: float, profit: float}>
     */
    public function revenueByYear(int $endYear, int $span = 10): array
    {
        $span = max(1, $span);
        $startYear = $endYear - $span + 1;

        ['from' => $from] = $this->yearRange($startYear);
        ['to' => $to] = $this->yearRange($endYear);

        $incomeByYear = $this->bucketByYear(
            Income::whereBetween('income_date', [$from, $to])->get(['income_date', 'amount']),
            'income_date',
            $startYear,
            $endYear
        );
        $expenseByYear = $this->bucketByYear(
            Expense::whereBetween('expense_date', [$from, $to])->get(['expense_date', 'amount']),
            'expense_date',
            $startYear,
            $endYear
        );

        $rows = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            $income = $incomeByYear[$y];
            $expenses = $expenseByYear[$y];
            $rows[] = [
                'year' => $y,
                'income' => $income,
                'expenses' => $expenses,
                'profit' => round($income - $expenses, 2),
            ];
        }

        return $rows;
    }

    /**
     * Monthly cash-flow for a year: the opening balance (net of all activity
     * strictly before the year), each month's net movement, and the running
     * (cumulative) net position after each month.
     *
     * @return array{
     *     opening: float,
     *     incomeByMonth: array<int, float>,
     *     expenseByMonth: array<int, float>,
     *     netByMonth: array<int, float>,
     *     runningByMonth: array<int, float>,
     *     closing: float
     * }
     */
    public function cashFlow(int $year): array
    {
        ['from' => $from, 'to' => $to] = $this->yearRange($year);

        // Opening balance = everything that happened before the year started.
        $openingIncome = round((float) Income::where('income_date', '<', $from)->sum('amount'), 2);
        $openingExpense = round((float) Expense::where('expense_date', '<', $from)->sum('amount'), 2);
        $opening = round($openingIncome - $openingExpense, 2);

        $incomeByMonth = $this->bucketByMonth(
            Income::whereBetween('income_date', [$from, $to])->get(['income_date', 'amount']),
            'income_date'
        );
        $expenseByMonth = $this->bucketByMonth(
            Expense::whereBetween('expense_date', [$from, $to])->get(['expense_date', 'amount']),
            'expense_date'
        );

        $running = $opening;
        $netByMonth = [];
        $runningByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $net = round($incomeByMonth[$m] - $expenseByMonth[$m], 2);
            $running = round($running + $net, 2);
            $netByMonth[$m] = $net;
            $runningByMonth[$m] = $running;
        }

        return [
            'opening' => $opening,
            'incomeByMonth' => $incomeByMonth,
            'expenseByMonth' => $expenseByMonth,
            'netByMonth' => $netByMonth,
            'runningByMonth' => $runningByMonth,
            'closing' => $running,
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
