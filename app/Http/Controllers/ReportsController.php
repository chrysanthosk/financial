<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        // Quick filter defaults
        $year = (int)($request->query('year') ?: now()->year);

        // Range filter (optional)
        $from = $request->query('from');
        $to   = $request->query('to');

        $range = $this->resolveDateRange($year, $from, $to);

        // Quick summary for selected range (DB-agnostic)
        $incomeTotal = (float) Income::whereBetween('income_date', [$range['from'], $range['to']])->sum('amount');
        $expenseTotal = (float) Expense::whereBetween('expense_date', [$range['from'], $range['to']])->sum('amount');
        $profit = $incomeTotal - $expenseTotal;

        return view('reports.index', [
            'year' => $year,
            'from' => $range['from'],
            'to'   => $range['to'],

            'incomeTotal'  => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'profit'       => $profit,
        ]);
    }

    public function monthlyProfit(Request $request)
    {
        $year = (int)($request->query('year') ?: now()->year);

        $from = Carbon::create($year, 1, 1)->startOfDay()->toDateString();
        $to   = Carbon::create($year, 12, 31)->endOfDay()->toDateString();

        // Fetch rows and aggregate by month in PHP (DB-agnostic)
        $incomeRows = Income::whereBetween('income_date', [$from, $to])->get(['income_date', 'amount']);
        $expenseRows = Expense::whereBetween('expense_date', [$from, $to])->get(['expense_date', 'amount']);

        $incomeByMonth = array_fill(1, 12, 0.0);
        $expenseByMonth = array_fill(1, 12, 0.0);

        foreach ($incomeRows as $r) {
            $m = Carbon::parse($r->income_date)->month;
            $incomeByMonth[$m] += (float)$r->amount;
        }
        foreach ($expenseRows as $r) {
            $m = Carbon::parse($r->expense_date)->month;
            $expenseByMonth[$m] += (float)$r->amount;
        }

        $profitByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $profitByMonth[$m] = $incomeByMonth[$m] - $expenseByMonth[$m];
        }

        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->format('M');
        }

        return view('reports.monthly_profit', [
            'year' => $year,
            'labels' => $labels,
            'incomeByMonth' => array_values($incomeByMonth),
            'expenseByMonth' => array_values($expenseByMonth),
            'profitByMonth' => array_values($profitByMonth),
            'totalIncome' => array_sum($incomeByMonth),
            'totalExpenses' => array_sum($expenseByMonth),
            'totalProfit' => array_sum($profitByMonth),
        ]);
    }

    public function ytdIncome(Request $request)
    {
        $year = (int)($request->query('year') ?: now()->year);

        $from = Carbon::create($year, 1, 1)->toDateString();
        $to   = Carbon::create($year, 12, 31)->toDateString();

        $rows = Income::with('source')
            ->whereBetween('income_date', [$from, $to])
            ->get(['income_date', 'income_source_id', 'amount']);

        $total = 0.0;
        $bySource = []; // name => total
        $byMonth = array_fill(1, 12, 0.0);

        foreach ($rows as $r) {
            $amt = (float)$r->amount;
            $total += $amt;

            $name = $r->source?->name ?? 'Unknown';
            $bySource[$name] = ($bySource[$name] ?? 0.0) + $amt;

            $m = Carbon::parse($r->income_date)->month;
            $byMonth[$m] += $amt;
        }

        // Sort breakdown descending
        arsort($bySource);

        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->format('M');
        }

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
        $year = (int)($request->query('year') ?: now()->year);

        $from = Carbon::create($year, 1, 1)->toDateString();
        $to   = Carbon::create($year, 12, 31)->toDateString();

        $rows = Expense::with(['category', 'method'])
            ->whereBetween('expense_date', [$from, $to])
            ->get(['expense_date', 'expense_category_id', 'payment_method_id', 'amount', 'payee_name']);

        $total = 0.0;
        $byMethod = [];   // method => total
        $byCategory = []; // category => total
        $byMonth = array_fill(1, 12, 0.0);

        foreach ($rows as $r) {
            $amt = (float)$r->amount;
            $total += $amt;

            $method = $r->method?->name ?? 'Unknown';
            $cat = $r->category?->name ?? 'Unknown';

            $byMethod[$method] = ($byMethod[$method] ?? 0.0) + $amt;
            $byCategory[$cat] = ($byCategory[$cat] ?? 0.0) + $amt;

            $m = Carbon::parse($r->expense_date)->month;
            $byMonth[$m] += $amt;
        }

        arsort($byMethod);
        arsort($byCategory);

        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->format('M');
        }

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
        $year = (int)($request->query('year') ?: now()->year);
        $prev = $year - 1;

        $current = $this->yearTotals($year);
        $previous = $this->yearTotals($prev);

        // Month-by-month profit comparison
        $curProfit = $this->profitByMonth($year);
        $prevProfit = $this->profitByMonth($prev);

        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->format('M');
        }

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
        $limit = (int)($request->query('limit') ?: 20);
        $limit = max(5, min(100, $limit));

        // Optional range
        $year = (int)($request->query('year') ?: now()->year);
        $range = $this->resolveDateRange($year, $request->query('from'), $request->query('to'));

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
        $monthsBack = (int)($request->query('months') ?: 12);
        $monthsBack = max(3, min(36, $monthsBack));

        $to = now()->endOfDay();
        $from = now()->copy()->subMonths($monthsBack)->startOfDay();

        $rows = Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get(['expense_date', 'payee_name', 'amount']);

        $map = []; // payee => [months => set, total => float, count => int]
        foreach ($rows as $r) {
            $payee = trim((string)$r->payee_name);
            if ($payee === '') $payee = 'Unknown';

            $monthKey = Carbon::parse($r->expense_date)->format('Y-m');

            if (!isset($map[$payee])) {
                $map[$payee] = [
                    'months' => [],
                    'total' => 0.0,
                    'count' => 0,
                ];
            }

            $map[$payee]['months'][$monthKey] = true;
            $map[$payee]['total'] += (float)$r->amount;
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
                    'total' => $data['total'],
                ];
            }
        }

        // Sort by total spend desc
        usort($results, fn($a, $b) => $b['total'] <=> $a['total']);

        return view('reports.recurring_expenses', [
            'monthsBack' => $monthsBack,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'results' => $results,
        ]);
    }

    public function categoryTrend(Request $request)
    {
        $year = (int)($request->query('year') ?: now()->year);

        $from = Carbon::create($year, 1, 1)->toDateString();
        $to   = Carbon::create($year, 12, 31)->toDateString();

        $rows = Expense::with('category')
            ->whereBetween('expense_date', [$from, $to])
            ->get(['expense_date', 'expense_category_id', 'amount']);

        // category => [1..12 => total]
        $series = [];

        foreach ($rows as $r) {
            $cat = $r->category?->name ?? 'Unknown';
            $m = Carbon::parse($r->expense_date)->month;
            if (!isset($series[$cat])) {
                $series[$cat] = array_fill(1, 12, 0.0);
            }
            $series[$cat][$m] += (float)$r->amount;
        }

        // Keep top N categories by total
        $totals = [];
        foreach ($series as $cat => $months) $totals[$cat] = array_sum($months);
        arsort($totals);

        $topN = (int)($request->query('top') ?: 6);
        $topN = max(3, min(12, $topN));

        $topCats = array_slice(array_keys($totals), 0, $topN);

        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->format('M');
        }

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
        $year = (int)($request->query('year') ?: now()->year);
        $limit = (int)($request->query('limit') ?: 15);
        $limit = max(5, min(50, $limit));

        $range = $this->resolveDateRange($year, $request->query('from'), $request->query('to'));

        // Fetch expenses for range (DB-agnostic) and aggregate in PHP
        $rows = Expense::whereBetween('expense_date', [$range['from'], $range['to']])
            ->get(['expense_date', 'payee_name', 'amount']);

        $totals = [];   // payee => total
        $counts = [];   // payee => tx count

        foreach ($rows as $r) {
            $payee = trim((string)$r->payee_name);
            if ($payee === '') $payee = 'Unknown';

            $totals[$payee] = ($totals[$payee] ?? 0.0) + (float)$r->amount;
            $counts[$payee] = ($counts[$payee] ?? 0) + 1;
        }

        arsort($totals);
        $topPayees = array_slice(array_keys($totals), 0, $limit);

        $table = [];
        foreach ($topPayees as $p) {
            $table[] = [
                'payee' => $p,
                'total' => (float)$totals[$p],
                'count' => (int)($counts[$p] ?? 0),
            ];
        }

        // Chart data
        $chartLabels = array_map(fn($r) => $r['payee'], $table);
        $chartTotals = array_map(fn($r) => (float)$r['total'], $table);

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
        $year = (int)($request->query('year') ?: now()->year);
        $range = $this->resolveDateRange($year, $request->query('from'), $request->query('to'));

        $rows = Expense::with('category')
            ->whereBetween('expense_date', [$range['from'], $range['to']])
            ->get(['expense_category_id', 'amount']);

        $totals = []; // category => total
        $grand = 0.0;

        foreach ($rows as $r) {
            $cat = $r->category?->name ?? 'Unknown';
            $amt = (float)$r->amount;

            $grand += $amt;
            $totals[$cat] = ($totals[$cat] ?? 0.0) + $amt;
        }

        arsort($totals);

        // Option: top N + "Other"
        $topN = (int)($request->query('top') ?: 8);
        $topN = max(3, min(20, $topN));

        $topCats = array_slice($totals, 0, $topN, true);
        $restCats = array_slice($totals, $topN, null, true);

        $otherTotal = array_sum($restCats);

        $chartLabels = array_keys($topCats);
        $chartTotals = array_values($topCats);

        if ($otherTotal > 0) {
            $chartLabels[] = 'Other';
            $chartTotals[] = (float)$otherTotal;
        }

        // Table for all categories
        $table = [];
        foreach ($totals as $name => $amt) {
            $table[] = [
                'category' => $name,
                'total' => (float)$amt,
                'percent' => $grand > 0 ? ((float)$amt / $grand) * 100.0 : 0.0,
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
        $year = (int)($request->query('year') ?: now()->year);

        $from = Carbon::create($year, 1, 1)->toDateString();
        $to   = Carbon::create($year, 12, 31)->toDateString();

        $topN = (int)($request->query('top') ?: 6);
        $topN = max(3, min(12, $topN));

        // Pull income rows for year, then build monthly totals per source in PHP
        $rows = Income::with('source')
            ->whereBetween('income_date', [$from, $to])
            ->get(['income_date', 'income_source_id', 'amount']);

        // sourceName => [1..12 => total]
        $bySourceMonth = [];
        $sourceTotals = [];

        foreach ($rows as $r) {
            $name = $r->source?->name ?? 'Unknown';
            $m = Carbon::parse($r->income_date)->month;
            $amt = (float)$r->amount;

            if (!isset($bySourceMonth[$name])) {
                $bySourceMonth[$name] = array_fill(1, 12, 0.0);
            }
            $bySourceMonth[$name][$m] += $amt;

            $sourceTotals[$name] = ($sourceTotals[$name] ?? 0.0) + $amt;
        }

        // Choose top N sources by total, rest goes to Other
        arsort($sourceTotals);
        $topSources = array_slice(array_keys($sourceTotals), 0, $topN);

        // Build month totals
        $monthTotals = array_fill(1, 12, 0.0);
        foreach ($rows as $r) {
            $m = Carbon::parse($r->income_date)->month;
            $monthTotals[$m] += (float)$r->amount;
        }

        // Build datasets as % share per month
        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->format('M');
        }

        $datasets = [];

        // Top sources
        foreach ($topSources as $s) {
            $data = [];
            for ($m = 1; $m <= 12; $m++) {
                $den = (float)$monthTotals[$m];
                $num = (float)($bySourceMonth[$s][$m] ?? 0.0);
                $data[] = $den > 0 ? ($num / $den) * 100.0 : 0.0;
            }
            $datasets[] = ['label' => $s, 'data' => $data];
        }

        // Other (everything not in topSources)
        $otherData = [];
        for ($m = 1; $m <= 12; $m++) {
            $den = (float)$monthTotals[$m];
            $topSum = 0.0;
            foreach ($topSources as $s) {
                $topSum += (float)($bySourceMonth[$s][$m] ?? 0.0);
            }
            $other = max(0.0, $den - $topSum);
            $otherData[] = $den > 0 ? ($other / $den) * 100.0 : 0.0;
        }
        if (array_sum($otherData) > 0) {
            $datasets[] = ['label' => 'Other', 'data' => $otherData];
        }

        // Table totals (topSources + other)
        $table = [];
        $grand = array_sum($sourceTotals);
        foreach ($sourceTotals as $name => $total) {
            $table[] = [
                'source' => $name,
                'total' => (float)$total,
                'percent' => $grand > 0 ? ((float)$total / $grand) * 100.0 : 0.0,
            ];
        }

        return view('reports.income_method_trend', [
            'year' => $year,
            'topN' => $topN,
            'labels' => $labels,
            'datasets' => $datasets,
            'monthTotals' => array_values($monthTotals),
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
        $year = (int)($request->query('year') ?: now()->year);
        $monthRaw = $request->query('month');

        // Month optional => null means whole year
        $month = ($monthRaw === null || $monthRaw === '') ? null : (int)$monthRaw;
        if (!is_null($month)) {
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
            1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
            7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'
        ];

        // Include active employees even if they have 0 records (LEFT JOIN)
        $q = DB::table('employees')
            ->leftJoin('employee_incomes', function ($join) use ($year, $month) {
                $join->on('employees.id', '=', 'employee_incomes.employee_id')
                    ->where('employee_incomes.year', '=', $year);

                if (!is_null($month)) {
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
        $series = $rows->pluck('total')->map(fn($v) => (float)$v)->toArray();
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

    /* -------------------------
       Helpers
    -------------------------*/
    private function resolveDateRange(int $year, ?string $from, ?string $to): array
    {
        // If user provides from/to, trust them (validated loosely), else use full year
        $fallbackFrom = Carbon::create($year, 1, 1)->startOfDay()->toDateString();
        $fallbackTo   = Carbon::create($year, 12, 31)->endOfDay()->toDateString();

        if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $start = $from;
        } else {
            $start = $fallbackFrom;
        }

        if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $end = $to;
        } else {
            $end = $fallbackTo;
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['from' => $start, 'to' => $end];
    }

    private function yearTotals(int $year): array
    {
        $from = Carbon::create($year, 1, 1)->toDateString();
        $to   = Carbon::create($year, 12, 31)->toDateString();

        $income = (float) Income::whereBetween('income_date', [$from, $to])->sum('amount');
        $expenses = (float) Expense::whereBetween('expense_date', [$from, $to])->sum('amount');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'profit' => $income - $expenses,
        ];
    }

    private function profitByMonth(int $year): array
    {
        $from = Carbon::create($year, 1, 1)->toDateString();
        $to   = Carbon::create($year, 12, 31)->toDateString();

        $incomeByMonth = array_fill(1, 12, 0.0);
        $expenseByMonth = array_fill(1, 12, 0.0);

        $incomeRows = Income::whereBetween('income_date', [$from, $to])->get(['income_date', 'amount']);
        foreach ($incomeRows as $r) {
            $m = Carbon::parse($r->income_date)->month;
            $incomeByMonth[$m] += (float)$r->amount;
        }

        $expenseRows = Expense::whereBetween('expense_date', [$from, $to])->get(['expense_date', 'amount']);
        foreach ($expenseRows as $r) {
            $m = Carbon::parse($r->expense_date)->month;
            $expenseByMonth[$m] += (float)$r->amount;
        }

        $profit = [];
        for ($m = 1; $m <= 12; $m++) {
            $profit[$m] = $incomeByMonth[$m] - $expenseByMonth[$m];
        }

        return $profit;
    }
}
