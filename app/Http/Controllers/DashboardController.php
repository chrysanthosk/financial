<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Greeting
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        // Display name (prefer first/last)
        $displayName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($displayName === '') {
            $displayName = $user->name ?: ($user->email ?: 'User');
        }

        // Dates
        $today = now()->toDateString();
        $startOfMonth = now()->copy()->startOfMonth()->toDateString();
        $endOfMonth = now()->copy()->endOfMonth()->toDateString();
        $daysInMonth = (int) now()->daysInMonth;

        // Totals (DB-agnostic)
        $todayIncome = (float) Income::whereDate('income_date', $today)->sum('amount');

        $mtdIncome = (float) Income::whereBetween('income_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $mtdExpenses = (float) Expense::whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $mtdProfit = $mtdIncome - $mtdExpenses;

        // -------------------------
        // Unpaid expenses (latest)
        // -------------------------
        $unpaidExpenses = Expense::query()
            ->where('is_paid', false)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'expense_date', 'payee_name', 'amount']);

        // -------------------------
        // Chart 1: Income vs Expenses by day (current month)
        // DB-agnostic grouping: groupBy(date column itself)
        // -------------------------
        $labels = [];
        $incomeByDay = array_fill(1, $daysInMonth, 0.0);
        $expenseByDay = array_fill(1, $daysInMonth, 0.0);

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labels[] = Carbon::createFromDate(now()->year, now()->month, $d)->toDateString();
        }

        $incomeRows = Income::query()
            ->selectRaw('income_date as d, SUM(amount) as total')
            ->whereBetween('income_date', [$startOfMonth, $endOfMonth])
            ->groupBy('income_date')
            ->get();

        foreach ($incomeRows as $r) {
            $day = Carbon::parse($r->d)->day; // 1..31
            if ($day >= 1 && $day <= $daysInMonth) {
                $incomeByDay[$day] = (float) $r->total;
            }
        }

        $expenseRows = Expense::query()
            ->selectRaw('expense_date as d, SUM(amount) as total')
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->groupBy('expense_date')
            ->get();

        foreach ($expenseRows as $r) {
            $day = Carbon::parse($r->d)->day;
            if ($day >= 1 && $day <= $daysInMonth) {
                $expenseByDay[$day] = (float) $r->total;
            }
        }

        $chartIncomeByDay = array_values($incomeByDay);
        $chartExpensesByDay = array_values($expenseByDay);

        // -------------------------
        // Chart 2: Income by method/source (current month)
        // group by income_source_id is DB-agnostic
        // -------------------------
        $incomeBySourceRows = Income::with('source')
            ->selectRaw('income_source_id, SUM(amount) as total')
            ->whereBetween('income_date', [$startOfMonth, $endOfMonth])
            ->groupBy('income_source_id')
            ->orderByDesc('total')
            ->get();

        $chartIncomeSourceLabels = [];
        $chartIncomeSourceTotals = [];

        foreach ($incomeBySourceRows as $row) {
            $chartIncomeSourceLabels[] = $row->source?->name ?? 'Unknown';
            $chartIncomeSourceTotals[] = (float) $row->total;
        }

        return view('dashboard', [
            'greeting' => $greeting,
            'displayName' => $displayName,

            'todayIncome' => $todayIncome,
            'mtdIncome' => $mtdIncome,
            'mtdExpenses' => $mtdExpenses,
            'mtdProfit' => $mtdProfit,

            'unpaidExpenses' => $unpaidExpenses,

            'chartLabels' => $labels,
            'chartIncomeByDay' => $chartIncomeByDay,
            'chartExpensesByDay' => $chartExpensesByDay,

            'chartIncomeSourceLabels' => $chartIncomeSourceLabels,
            'chartIncomeSourceTotals' => $chartIncomeSourceTotals,
        ]);
    }
}
