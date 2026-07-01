<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Income;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function __construct(private ReportingService $reports) {}

    public function index(Request $request)
    {
        // Quick filter defaults
        $year = (int) ($request->query('year') ?: now()->year);

        // Range filter (optional)
        $from = $request->query('from');
        $to = $request->query('to');

        $range = $this->reports->resolveDateRange($year, $from, $to);

        // Quick summary for selected range (DB SUM is exact for decimals)
        $incomeTotal = round((float) Income::whereBetween('income_date', [$range['from'], $range['to']])->sum('amount'), 2);
        $expenseTotal = round((float) Expense::whereBetween('expense_date', [$range['from'], $range['to']])->sum('amount'), 2);
        $profit = round($incomeTotal - $expenseTotal, 2);

        return view('reports.index', [
            'year' => $year,
            'from' => $range['from'],
            'to' => $range['to'],

            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'profit' => $profit,
        ]);
    }

    public function monthlyProfit(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        ['from' => $from, 'to' => $to] = $this->reports->yearRange($year);

        // Fetch rows and aggregate by month in PHP (DB-agnostic)
        $incomeByMonth = $this->reports->bucketByMonth(
            Income::whereBetween('income_date', [$from, $to])->get(['income_date', 'amount']),
            'income_date'
        );
        $expenseByMonth = $this->reports->bucketByMonth(
            Expense::whereBetween('expense_date', [$from, $to])->get(['expense_date', 'amount']),
            'expense_date'
        );

        $profitByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $profitByMonth[$m] = round($incomeByMonth[$m] - $expenseByMonth[$m], 2);
        }

        $labels = $this->reports->monthLabels($year);

        return view('reports.monthly_profit', [
            'year' => $year,
            'labels' => $labels,
            'incomeByMonth' => array_values($incomeByMonth),
            'expenseByMonth' => array_values($expenseByMonth),
            'profitByMonth' => array_values($profitByMonth),
            'totalIncome' => round(array_sum($incomeByMonth), 2),
            'totalExpenses' => round(array_sum($expenseByMonth), 2),
            'totalProfit' => round(array_sum($profitByMonth), 2),
        ]);
    }

    public function revenueByYear(Request $request)
    {
        $endYear = (int) ($request->query('year') ?: now()->year);
        $span = (int) ($request->query('years') ?: 10);
        $span = max(1, min(50, $span));

        $rows = $this->reports->revenueByYear($endYear, $span);

        $labels = array_map(fn ($r) => (string) $r['year'], $rows);
        $incomeByYear = array_map(fn ($r) => $r['income'], $rows);
        $expenseByYear = array_map(fn ($r) => $r['expenses'], $rows);
        $profitByYear = array_map(fn ($r) => $r['profit'], $rows);

        return view('reports.revenue_by_year', [
            'endYear' => $endYear,
            'span' => $span,
            'rows' => $rows,
            'labels' => $labels,
            'incomeByYear' => $incomeByYear,
            'expenseByYear' => $expenseByYear,
            'profitByYear' => $profitByYear,
            'totalIncome' => round(array_sum($incomeByYear), 2),
            'totalExpenses' => round(array_sum($expenseByYear), 2),
            'totalProfit' => round(array_sum($profitByYear), 2),
        ]);
    }

    public function profitMarginByYear(Request $request)
    {
        $endYear = (int) ($request->query('year') ?: now()->year);
        $span = (int) ($request->query('years') ?: 10);
        $span = max(1, min(50, $span));

        $rows = $this->reports->revenueByYear($endYear, $span);

        // Margin % = profit / income * 100 (0 when there is no income).
        foreach ($rows as &$r) {
            $r['margin'] = $r['income'] > 0 ? round($r['profit'] / $r['income'] * 100, 1) : 0.0;
        }
        unset($r);

        $labels = array_map(fn ($r) => (string) $r['year'], $rows);

        return view('reports.profit_margin_by_year', [
            'endYear' => $endYear,
            'span' => $span,
            'rows' => $rows,
            'labels' => $labels,
            'incomeByYear' => array_map(fn ($r) => $r['income'], $rows),
            'profitByYear' => array_map(fn ($r) => $r['profit'], $rows),
            'marginByYear' => array_map(fn ($r) => $r['margin'], $rows),
        ]);
    }

    public function cashFlow(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        $flow = $this->reports->cashFlow($year);
        $labels = $this->reports->monthLabels($year);

        return view('reports.cash_flow', [
            'year' => $year,
            'labels' => $labels,
            'opening' => $flow['opening'],
            'closing' => $flow['closing'],
            'incomeByMonth' => array_values($flow['incomeByMonth']),
            'expenseByMonth' => array_values($flow['expenseByMonth']),
            'netByMonth' => array_values($flow['netByMonth']),
            'runningByMonth' => array_values($flow['runningByMonth']),
        ]);
    }

    public function incomeSourceByYear(Request $request)
    {
        $endYear = (int) ($request->query('year') ?: now()->year);
        $span = (int) ($request->query('years') ?: 10);
        $span = max(1, min(50, $span));
        $startYear = $endYear - $span + 1;

        ['from' => $from] = $this->reports->yearRange($startYear);
        ['to' => $to] = $this->reports->yearRange($endYear);

        $incomeRows = Income::with('source')
            ->whereBetween('income_date', [$from, $to])
            ->get(['income_date', 'income_source_id', 'amount']);

        // Accumulate cents into [source][year] so totals stay exact.
        $cents = [];
        foreach ($incomeRows as $r) {
            $source = $r->source?->name ?? 'Unknown';
            $y = Carbon::parse($r->income_date)->year;
            $cents[$source][$y] = ($cents[$source][$y] ?? 0) + $this->reports->toCents($r->amount);
        }

        $years = range($startYear, $endYear);

        // Build one row per source (sorted by grand total desc) + per-year totals.
        $rows = [];
        $totalsByYear = array_fill_keys($years, 0.0);
        foreach ($cents as $source => $byYear) {
            $series = [];
            $rowTotal = 0.0;
            foreach ($years as $y) {
                $val = $this->reports->fromCents($byYear[$y] ?? 0);
                $series[] = $val;
                $rowTotal = round($rowTotal + $val, 2);
                $totalsByYear[$y] = round($totalsByYear[$y] + $val, 2);
            }
            $rows[] = ['source' => $source, 'series' => $series, 'total' => $rowTotal];
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $datasets = array_map(fn ($r) => ['label' => $r['source'], 'data' => $r['series']], $rows);

        return view('reports.income_source_by_year', [
            'endYear' => $endYear,
            'span' => $span,
            'years' => $years,
            'labels' => array_map(fn ($y) => (string) $y, $years),
            'rows' => $rows,
            'datasets' => $datasets,
            'totalsByYear' => array_values($totalsByYear),
            'grandTotal' => round(array_sum($totalsByYear), 2),
        ]);
    }

    public function quarterlySummary(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        $incomeByMonth = $this->reports->bucketByMonth(
            Income::whereBetween('income_date', $this->reports->yearRange($year))->get(['income_date', 'amount']),
            'income_date'
        );
        $expenseByMonth = $this->reports->bucketByMonth(
            Expense::whereBetween('expense_date', $this->reports->yearRange($year))->get(['expense_date', 'amount']),
            'expense_date'
        );

        // Fold the 12 months into 4 quarters.
        $rows = [];
        $incomeByQuarter = [];
        $expenseByQuarter = [];
        $profitByQuarter = [];
        for ($q = 1; $q <= 4; $q++) {
            $months = range(($q - 1) * 3 + 1, $q * 3);
            $income = round(array_sum(array_map(fn ($m) => $incomeByMonth[$m], $months)), 2);
            $expenses = round(array_sum(array_map(fn ($m) => $expenseByMonth[$m], $months)), 2);
            $profit = round($income - $expenses, 2);

            $rows[] = ['quarter' => "Q{$q}", 'income' => $income, 'expenses' => $expenses, 'profit' => $profit];
            $incomeByQuarter[] = $income;
            $expenseByQuarter[] = $expenses;
            $profitByQuarter[] = $profit;
        }

        return view('reports.quarterly_summary', [
            'year' => $year,
            'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'rows' => $rows,
            'incomeByQuarter' => $incomeByQuarter,
            'expenseByQuarter' => $expenseByQuarter,
            'profitByQuarter' => $profitByQuarter,
            'totalIncome' => round(array_sum($incomeByQuarter), 2),
            'totalExpenses' => round(array_sum($expenseByQuarter), 2),
            'totalProfit' => round(array_sum($profitByQuarter), 2),
        ]);
    }

    public function ytdIncome(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        ['from' => $from, 'to' => $to] = $this->reports->yearRange($year);

        $rows = Income::with('source')
            ->whereBetween('income_date', [$from, $to])
            ->get(['income_date', 'income_source_id', 'amount']);

        $bySource = $this->reports->sumByLabel($rows, fn ($r) => $r->source?->name ?? 'Unknown');
        $byMonth = $this->reports->bucketByMonth($rows, 'income_date');
        $total = round(array_sum($bySource), 2);

        $labels = $this->reports->monthLabels($year);

        return view('reports.ytd_income', [
            'year' => $year,
            'total' => $total,
            'bySource' => $bySource,
            'labels' => $labels,
            'byMonth' => array_values($byMonth),
        ]);
    }

    public function ytdExpenses(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        ['from' => $from, 'to' => $to] = $this->reports->yearRange($year);

        $rows = Expense::with(['category', 'method'])
            ->whereBetween('expense_date', [$from, $to])
            ->get(['expense_date', 'expense_category_id', 'payment_method_id', 'amount', 'payee_name']);

        $byMethod = $this->reports->sumByLabel($rows, fn ($r) => $r->method?->name ?? 'Unknown');
        $byCategory = $this->reports->sumByLabel($rows, fn ($r) => $r->category?->name ?? 'Unknown');
        $byMonth = $this->reports->bucketByMonth($rows, 'expense_date');
        $total = round(array_sum($byCategory), 2);

        $labels = $this->reports->monthLabels($year);

        return view('reports.ytd_expenses', [
            'year' => $year,
            'total' => $total,
            'byMethod' => $byMethod,
            'byCategory' => $byCategory,
            'labels' => $labels,
            'byMonth' => array_values($byMonth),
        ]);
    }

    public function prevYearComparison(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $prev = $year - 1;

        $current = $this->reports->yearTotals($year);
        $previous = $this->reports->yearTotals($prev);

        // Month-by-month profit comparison
        $curProfit = $this->reports->profitByMonth($year);
        $prevProfit = $this->reports->profitByMonth($prev);

        $labels = $this->reports->monthLabels($year);

        return view('reports.prev_year_comparison', [
            'year' => $year,
            'prevYear' => $prev,
            'current' => $current,
            'previous' => $previous,
            'labels' => $labels,
            'curProfit' => array_values($curProfit),
            'prevProfit' => array_values($prevProfit),
        ]);
    }

    public function largestTransactions(Request $request)
    {
        $type = $request->query('type', 'expenses'); // expenses|income
        $limit = (int) ($request->query('limit') ?: 20);
        $limit = max(5, min(100, $limit));

        // Optional range
        $year = (int) ($request->query('year') ?: now()->year);
        $range = $this->reports->resolveDateRange($year, $request->query('from'), $request->query('to'));

        if ($type === 'income') {
            $rows = Income::with('source')
                ->whereBetween('income_date', [$range['from'], $range['to']])
                ->orderByDesc('amount')
                ->orderByDesc('income_date')
                ->limit($limit)
                ->get();

            return view('reports.largest_transactions', [
                'type' => 'income',
                'year' => $year,
                'from' => $range['from'],
                'to' => $range['to'],
                'limit' => $limit,
                'rows' => $rows,
            ]);
        }

        $rows = Expense::with(['category', 'method'])
            ->whereBetween('expense_date', [$range['from'], $range['to']])
            ->orderByDesc('amount')
            ->orderByDesc('expense_date')
            ->limit($limit)
            ->get();

        return view('reports.largest_transactions', [
            'type' => 'expenses',
            'year' => $year,
            'from' => $range['from'],
            'to' => $range['to'],
            'limit' => $limit,
            'rows' => $rows,
        ]);
    }

    public function recurringExpenses(Request $request)
    {
        // Simple detector: last 12 months, vendors appearing in >=3 distinct months
        $monthsBack = (int) ($request->query('months') ?: 12);
        $monthsBack = max(3, min(36, $monthsBack));

        $to = now()->endOfDay();
        $from = now()->copy()->subMonths($monthsBack)->startOfDay();

        $rows = Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get(['expense_date', 'payee_name', 'amount']);

        $map = []; // payee => [months => set, total => float, count => int]
        foreach ($rows as $r) {
            $payee = trim((string) $r->payee_name);
            if ($payee === '') {
                $payee = 'Unknown';
            }

            $monthKey = Carbon::parse($r->expense_date)->format('Y-m');

            if (! isset($map[$payee])) {
                $map[$payee] = [
                    'months' => [],
                    'total_cents' => 0,
                    'count' => 0,
                ];
            }

            $map[$payee]['months'][$monthKey] = true;
            $map[$payee]['total_cents'] += $this->reports->toCents($r->amount);
            $map[$payee]['count']++;
        }

        $results = [];
        foreach ($map as $payee => $data) {
            $distinctMonths = count($data['months']);
            if ($distinctMonths >= 3) {
                $results[] = [
                    'payee' => $payee,
                    'distinct_months' => $distinctMonths,
                    'tx_count' => $data['count'],
                    'total' => $this->reports->fromCents($data['total_cents']),
                ];
            }
        }

        // Sort by total spend desc
        usort($results, fn ($a, $b) => $b['total'] <=> $a['total']);

        return view('reports.recurring_expenses', [
            'monthsBack' => $monthsBack,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'results' => $results,
        ]);
    }

    public function categoryTrend(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        ['from' => $from, 'to' => $to] = $this->reports->yearRange($year);

        $rows = Expense::with('category')
            ->whereBetween('expense_date', [$from, $to])
            ->get(['expense_date', 'expense_category_id', 'amount']);

        // category => [1..12 => cents]
        $seriesCents = [];

        foreach ($rows as $r) {
            $cat = $r->category?->name ?? 'Unknown';
            $m = Carbon::parse($r->expense_date)->month;
            if (! isset($seriesCents[$cat])) {
                $seriesCents[$cat] = array_fill(1, 12, 0);
            }
            $seriesCents[$cat][$m] += $this->reports->toCents($r->amount);
        }

        // Convert to float and compute per-category totals
        $series = [];
        $totals = [];
        foreach ($seriesCents as $cat => $months) {
            $series[$cat] = array_map(fn (int $c) => $this->reports->fromCents($c), $months);
            $totals[$cat] = round(array_sum($series[$cat]), 2);
        }
        arsort($totals);

        $topN = (int) ($request->query('top') ?: 6);
        $topN = max(3, min(12, $topN));

        $topCats = array_slice(array_keys($totals), 0, $topN);

        $labels = $this->reports->monthLabels($year);

        $datasets = [];
        foreach ($topCats as $cat) {
            $datasets[] = [
                'label' => $cat,
                'data' => array_values($series[$cat]),
            ];
        }

        return view('reports.category_trend', [
            'year' => $year,
            'topN' => $topN,
            'labels' => $labels,
            'datasets' => $datasets,
        ]);
    }

    // Scaffold pages (can enhance later)
    public function topVendors(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $limit = (int) ($request->query('limit') ?: 15);
        $limit = max(5, min(50, $limit));

        $range = $this->reports->resolveDateRange($year, $request->query('from'), $request->query('to'));

        // Fetch expenses for range (DB-agnostic) and aggregate in PHP (exact cents)
        $rows = Expense::whereBetween('expense_date', [$range['from'], $range['to']])
            ->get(['expense_date', 'payee_name', 'amount']);

        $payeeOf = function ($r) {
            $payee = trim((string) $r->payee_name);

            return $payee === '' ? 'Unknown' : $payee;
        };

        $totals = $this->reports->sumByLabel($rows, $payeeOf); // payee => total, sorted desc

        $counts = []; // payee => tx count
        foreach ($rows as $r) {
            $payee = $payeeOf($r);
            $counts[$payee] = ($counts[$payee] ?? 0) + 1;
        }

        $topPayees = array_slice(array_keys($totals), 0, $limit);

        $table = [];
        foreach ($topPayees as $p) {
            $table[] = [
                'payee' => $p,
                'total' => (float) $totals[$p],
                'count' => (int) ($counts[$p] ?? 0),
            ];
        }

        // Chart data
        $chartLabels = array_map(fn ($r) => $r['payee'], $table);
        $chartTotals = array_map(fn ($r) => (float) $r['total'], $table);

        return view('reports.top_vendors', [
            'year' => $year,
            'from' => $range['from'],
            'to' => $range['to'],
            'limit' => $limit,
            'table' => $table,
            'chartLabels' => $chartLabels,
            'chartTotals' => $chartTotals,
        ]);
    }

    public function expenseCategoryBreakdown(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $range = $this->reports->resolveDateRange($year, $request->query('from'), $request->query('to'));

        $rows = Expense::with('category')
            ->whereBetween('expense_date', [$range['from'], $range['to']])
            ->get(['expense_category_id', 'amount']);

        $totals = $this->reports->sumByLabel($rows, fn ($r) => $r->category?->name ?? 'Unknown');
        $grand = round(array_sum($totals), 2);

        // Option: top N + "Other"
        $topN = (int) ($request->query('top') ?: 8);
        $topN = max(3, min(20, $topN));

        $topCats = array_slice($totals, 0, $topN, true);
        $restCats = array_slice($totals, $topN, null, true);

        $otherTotal = array_sum($restCats);

        $chartLabels = array_keys($topCats);
        $chartTotals = array_values($topCats);

        if ($otherTotal > 0) {
            $chartLabels[] = 'Other';
            $chartTotals[] = (float) $otherTotal;
        }

        // Table for all categories
        $table = [];
        foreach ($totals as $name => $amt) {
            $table[] = [
                'category' => $name,
                'total' => (float) $amt,
                'percent' => $grand > 0 ? ((float) $amt / $grand) * 100.0 : 0.0,
            ];
        }

        return view('reports.expense_category_breakdown', [
            'year' => $year,
            'from' => $range['from'],
            'to' => $range['to'],
            'topN' => $topN,
            'grand' => $grand,
            'table' => $table,
            'chartLabels' => $chartLabels,
            'chartTotals' => $chartTotals,
        ]);
    }

    public function incomeMethodTrend(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        ['from' => $from, 'to' => $to] = $this->reports->yearRange($year);

        $topN = (int) ($request->query('top') ?: 6);
        $topN = max(3, min(12, $topN));

        // Pull income rows for year, then build monthly totals per source in PHP
        $rows = Income::with('source')
            ->whereBetween('income_date', [$from, $to])
            ->get(['income_date', 'income_source_id', 'amount']);

        // sourceName => [1..12 => cents]
        $bySourceMonth = [];
        $sourceTotals = []; // sourceName => cents
        $monthTotals = array_fill(1, 12, 0); // 1..12 => cents

        foreach ($rows as $r) {
            $name = $r->source?->name ?? 'Unknown';
            $m = Carbon::parse($r->income_date)->month;
            $cents = $this->reports->toCents($r->amount);

            if (! isset($bySourceMonth[$name])) {
                $bySourceMonth[$name] = array_fill(1, 12, 0);
            }
            $bySourceMonth[$name][$m] += $cents;
            $sourceTotals[$name] = ($sourceTotals[$name] ?? 0) + $cents;
            $monthTotals[$m] += $cents;
        }

        // Choose top N sources by total, rest goes to Other
        arsort($sourceTotals);
        $topSources = array_slice(array_keys($sourceTotals), 0, $topN);

        // Build datasets as % share per month
        $labels = $this->reports->monthLabels($year);

        $datasets = [];

        // Top sources
        foreach ($topSources as $s) {
            $data = [];
            for ($m = 1; $m <= 12; $m++) {
                $den = $monthTotals[$m];
                $num = $bySourceMonth[$s][$m] ?? 0;
                $data[] = $den > 0 ? round($num / $den * 100, 2) : 0.0;
            }
            $datasets[] = ['label' => $s, 'data' => $data];
        }

        // Other (everything not in topSources)
        $otherData = [];
        for ($m = 1; $m <= 12; $m++) {
            $den = $monthTotals[$m];
            $topSum = 0;
            foreach ($topSources as $s) {
                $topSum += $bySourceMonth[$s][$m] ?? 0;
            }
            $other = max(0, $den - $topSum);
            $otherData[] = $den > 0 ? round($other / $den * 100, 2) : 0.0;
        }
        if (array_sum($otherData) > 0) {
            $datasets[] = ['label' => 'Other', 'data' => $otherData];
        }

        // Table totals (topSources + other)
        $table = [];
        $grand = array_sum($sourceTotals);
        foreach ($sourceTotals as $name => $totalCents) {
            $table[] = [
                'source' => $name,
                'total' => $this->reports->fromCents($totalCents),
                'percent' => $grand > 0 ? round($totalCents / $grand * 100, 2) : 0.0,
            ];
        }

        return view('reports.income_method_trend', [
            'year' => $year,
            'topN' => $topN,
            'labels' => $labels,
            'datasets' => $datasets,
            'monthTotals' => array_map(fn (int $c) => $this->reports->fromCents($c), array_values($monthTotals)),
            'table' => $table,
        ]);
    }

    /**
     * ✅ NEW REPORT: Employee Income
     * Admin-only via route middleware.
     *
     * Aggregates employee_incomes.total_amount per employee.
     * Filters by year + optional month.
     */
    public function employeeIncome(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $monthRaw = $request->query('month');

        // Month optional => null means whole year
        $month = ($monthRaw === null || $monthRaw === '') ? null : (int) $monthRaw;
        if (! is_null($month)) {
            $month = max(1, min(12, $month));
        }

        // Years available for dropdown (fallback to current year)
        $years = DB::table('employee_incomes')
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        if (empty($years)) {
            $years = [$year];
        }

        // Month dropdown labels
        $monthOptions = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        // Include active employees even if they have 0 records (LEFT JOIN)
        $q = DB::table('employees')
            ->leftJoin('employee_incomes', function ($join) use ($year, $month) {
                $join->on('employees.id', '=', 'employee_incomes.employee_id')
                    ->where('employee_incomes.year', '=', $year);

                if (! is_null($month)) {
                    $join->where('employee_incomes.month', '=', $month);
                }
            })
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.name')
            ->orderBy('employees.sort_order')
            ->orderBy('employees.name')
            ->selectRaw('employees.name as employee_name, COALESCE(SUM(employee_incomes.total_amount), 0) as total');

        $rows = $q->get();

        $labels = $rows->pluck('employee_name')->toArray();
        $series = $rows->pluck('total')->map(fn ($v) => (float) $v)->toArray();
        $grandTotal = array_sum($series);

        return view('reports.employee_income', [
            'year' => $year,
            'month' => $month,
            'years' => $years,
            'monthOptions' => $monthOptions,
            'rows' => $rows,
            'labels' => $labels,
            'series' => $series,
            'grandTotal' => $grandTotal,
        ]);
    }

    /**
     * Employee Revenue Contribution by Year (admin-only via route middleware).
     *
     * Multi-year companion to employeeIncome(): sums employee_incomes.total_amount
     * per employee across a span of years, so per-person trends are visible.
     */
    public function employeeRevenueByYear(Request $request)
    {
        $endYear = (int) ($request->query('year') ?: now()->year);
        $span = (int) ($request->query('years') ?: 10);
        $span = max(1, min(50, $span));
        $startYear = $endYear - $span + 1;
        $years = range($startYear, $endYear);

        // Active employees plus their per-year totals within the span.
        $records = DB::table('employees')
            ->leftJoin('employee_incomes', function ($join) use ($startYear, $endYear) {
                $join->on('employees.id', '=', 'employee_incomes.employee_id')
                    ->whereBetween('employee_incomes.year', [$startYear, $endYear]);
            })
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.name', 'employee_incomes.year')
            ->orderBy('employees.sort_order')
            ->orderBy('employees.name')
            ->selectRaw('employees.name as employee_name, employee_incomes.year as year, COALESCE(SUM(employee_incomes.total_amount), 0) as total')
            ->get();

        // Pivot into [employee][year] => total.
        $byEmployee = [];
        foreach ($records as $rec) {
            $byEmployee[$rec->employee_name] ??= array_fill_keys($years, 0.0);
            if (! is_null($rec->year) && isset($byEmployee[$rec->employee_name][(int) $rec->year])) {
                $byEmployee[$rec->employee_name][(int) $rec->year] = round((float) $rec->total, 2);
            }
        }

        $rows = [];
        $totalsByYear = array_fill_keys($years, 0.0);
        foreach ($byEmployee as $name => $byYear) {
            $series = [];
            $rowTotal = 0.0;
            foreach ($years as $y) {
                $val = $byYear[$y];
                $series[] = $val;
                $rowTotal = round($rowTotal + $val, 2);
                $totalsByYear[$y] = round($totalsByYear[$y] + $val, 2);
            }
            $rows[] = ['employee' => $name, 'series' => $series, 'total' => $rowTotal];
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $datasets = array_map(fn ($r) => ['label' => $r['employee'], 'data' => $r['series']], $rows);

        return view('reports.employee_revenue_by_year', [
            'endYear' => $endYear,
            'span' => $span,
            'years' => $years,
            'labels' => array_map(fn ($y) => (string) $y, $years),
            'rows' => $rows,
            'datasets' => $datasets,
            'totalsByYear' => array_values($totalsByYear),
            'grandTotal' => round(array_sum($totalsByYear), 2),
        ]);
    }

    public function prevYearMonthlyIncomeComparison(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $prev = $year - 1;

        ['from' => $fromYear, 'to' => $toYear] = $this->reports->yearRange($year);
        ['from' => $fromPrev, 'to' => $toPrev] = $this->reports->yearRange($prev);

        // Income rows (DB-agnostic)
        $incomeByMonthYear = $this->reports->bucketByMonth(
            Income::whereBetween('income_date', [$fromYear, $toYear])->get(['income_date', 'amount']),
            'income_date'
        );
        $incomeByMonthPrev = $this->reports->bucketByMonth(
            Income::whereBetween('income_date', [$fromPrev, $toPrev])->get(['income_date', 'amount']),
            'income_date'
        );

        $labels = $this->reports->monthLabels($year);

        return view('reports.prev_year_monthly_income', [
            'year' => $year,
            'prevYear' => $prev,
            'labels' => $labels,
            'incomeYear' => array_values($incomeByMonthYear),
            'incomePrev' => array_values($incomeByMonthPrev),
            'totalYear' => round(array_sum($incomeByMonthYear), 2),
            'totalPrev' => round(array_sum($incomeByMonthPrev), 2),
        ]);
    }
}
