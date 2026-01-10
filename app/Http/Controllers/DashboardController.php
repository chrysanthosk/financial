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

        $now = now(); // uses app timezone
        $today = $now->toDateString();

        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth   = $now->copy()->endOfMonth()->toDateString();

        // Greeting
        $hour = (int)$now->format('H');
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good morning';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Good afternoon';
        } else {
            $greeting = 'Good evening';
        }

        $displayName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($displayName === '') {
            $displayName = $user->name ?: $user->email ?: 'User';
        }

        // Totals
        $todayIncome = 0;
        $todayExpenses = 0;
        $monthIncome = 0;
        $monthExpenses = 0;

        try {
            $todayIncome = (float) Income::whereDate('income_date', $today)->sum('amount');
            $todayExpenses = (float) Expense::whereDate('expense_date', $today)->sum('amount');

            $monthIncome = (float) Income::whereBetween('income_date', [$startOfMonth, $endOfMonth])->sum('amount');
            $monthExpenses = (float) Expense::whereBetween('expense_date', [$startOfMonth, $endOfMonth])->sum('amount');
        } catch (\Throwable $e) {
            // If tables don't exist yet, keep zeros (don't break dashboard)
        }

        return view('dashboard', [
            'greeting' => $greeting,
            'displayName' => $displayName,
            'todayIncome' => $todayIncome,
            'todayExpenses' => $todayExpenses,
            'monthIncome' => $monthIncome,
            'monthExpenses' => $monthExpenses,
        ]);
    }
}
