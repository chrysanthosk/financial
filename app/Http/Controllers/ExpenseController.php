<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->string('month')->toString();

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $categoryId = $request->integer('category_id') ?: null;
        $methodId   = $request->integer('method_id') ?: null;

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $query = Expense::query()
            ->with(['category', 'method'])
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($categoryId) {
            $query->where('expense_category_id', $categoryId);
        }
        if ($methodId) {
            $query->where('payment_method_id', $methodId);
        }

        $expenses = $query->paginate(20)->withQueryString();

        $monthTotal = (clone $query)->sum('amount');

        // breakdown per payment method (ignores method filter so you can always see it)
        $totalsPerMethod = Expense::query()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->when($categoryId, fn($q) => $q->where('expense_category_id', $categoryId))
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        return view('expenses.index', [
            'month'           => $month,
            'categoryId'      => $categoryId,
            'methodId'        => $methodId,
            'categories'      => $categories,
            'methods'         => $methods,
            'expenses'        => $expenses,
            'monthTotal'      => $monthTotal,
            'totalsPerMethod' => $totalsPerMethod,
        ]);
    }

    public function create()
    {
        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        if ($categories->isEmpty() || $methods->isEmpty()) {
            return redirect()
                ->route('expenses.index')
                ->with('status', 'No expense categories/payment methods found. Please run the seeders (or configure from Settings later).');
        }

        return view('expenses.create', [
            'defaultDate' => now()->toDateString(),
            'categories'  => $categories,
            'methods'     => $methods,
        ]);
    }

    public function store(Request $request)
    {
        $validCategoryIds = ExpenseCategory::where('is_active', true)->pluck('id')->all();
        $validMethodIds   = PaymentMethod::where('is_active', true)->pluck('id')->all();

        $validated = $request->validate([
            'expense_date'         => ['required', 'date'],
            'payee_name'           => ['required', 'string', 'max:120'],
            'expense_category_id'  => ['required', Rule::in($validCategoryIds)],
            'payment_method_id'    => ['required', Rule::in($validMethodIds)],
            'amount'               => ['required', 'numeric', 'min:0'],
            'cheque_no'            => ['nullable', 'string', 'max:80'],
            'reason'               => ['nullable', 'string', 'max:255'],
        ]);

        // if not cheque, ignore cheque_no
        if ((int)$validated['payment_method_id'] !== (int)PaymentMethod::where('name', 'Cheque')->value('id')) {
            // keep whatever user typed; if you want strict behavior, uncomment:
            // $validated['cheque_no'] = null;
        }

        $validated['created_by'] = Auth::id();

        Expense::create($validated);

        return redirect()->route('expenses.index')->with('status', 'Expense added successfully.');
    }

    public function edit(Expense $expense)
    {
        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('expenses.edit', [
            'expense'     => $expense->load(['category', 'method']),
            'categories'  => $categories,
            'methods'     => $methods,
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $validCategoryIds = ExpenseCategory::where('is_active', true)->pluck('id')->all();
        $validMethodIds   = PaymentMethod::where('is_active', true)->pluck('id')->all();

        $validated = $request->validate([
            'expense_date'         => ['required', 'date'],
            'payee_name'           => ['required', 'string', 'max:120'],
            'expense_category_id'  => ['required', Rule::in($validCategoryIds)],
            'payment_method_id'    => ['required', Rule::in($validMethodIds)],
            'amount'               => ['required', 'numeric', 'min:0'],
            'cheque_no'            => ['nullable', 'string', 'max:80'],
            'reason'               => ['nullable', 'string', 'max:255'],
        ]);

        $expense->update($validated);

        return redirect()->route('expenses.index')->with('status', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')->with('status', 'Expense deleted successfully.');
    }
}
