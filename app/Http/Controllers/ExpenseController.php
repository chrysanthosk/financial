<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->string('month')->toString();

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $categoryId = $request->integer('category_id') ?: null;
        $methodId = $request->integer('method_id') ?: null;

        // NEW: sorting params (date toggle)
        $allowedSorts = ['expense_date'];
        $sort = $request->string('sort')->toString() ?: 'expense_date';
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'expense_date';
        }

        $direction = strtolower($request->string('direction')->toString() ?: 'desc');
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        // Use [startOfMonth, startOfNextMonth) to avoid end-of-month/time issues
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = (clone $start)->addMonth(); // exclusive

        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $query = Expense::query()
            ->with(['category', 'method'])
            ->where('expense_date', '>=', $start)
            ->where('expense_date', '<', $end);

        if ($categoryId) {
            $query->where('expense_category_id', $categoryId);
        }
        if ($methodId) {
            $query->where('payment_method_id', $methodId);
        }

        // Apply sorting (primary + stable secondary)
        if ($sort === 'expense_date') {
            if ($direction === 'asc') {
                $query->orderBy('expense_date', 'asc')->orderBy('id', 'asc');
            } else {
                $query->orderBy('expense_date', 'desc')->orderBy('id', 'desc');
            }
        }

        $expenses = $query->paginate(20)->withQueryString();

        // Sum should match the current filtered query (but without pagination)
        $monthTotal = (clone $query)->sum('amount');

        // breakdown per payment method (ignores method filter so you can always see it)
        $totalsPerMethod = Expense::query()
            ->selectRaw('payment_method_id, SUM(amount) as total')
            ->where('expense_date', '>=', $start)
            ->where('expense_date', '<', $end)
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        return view('expenses.index', [
            'month' => $month,
            'categoryId' => $categoryId,
            'methodId' => $methodId,
            'categories' => $categories,
            'methods' => $methods,
            'expenses' => $expenses,
            'monthTotal' => $monthTotal,
            'totalsPerMethod' => $totalsPerMethod,

            // NEW: pass sort state to blade
            'sort' => $sort,
            'direction' => $direction,
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
            'categories' => $categories,
            'methods' => $methods,
            'payees' => $this->payeeSuggestions(),
        ]);
    }

    public function store(ExpenseRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();

        $expense = Expense::create($validated);

        Audit::log(
            action: 'expense.created',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Expense',
            targetId: (string) $expense->id,
            meta: [
                'expense_date' => (string) $expense->expense_date,
                'payee_name' => (string) $expense->payee_name,
                'expense_category_id' => (int) $expense->expense_category_id,
                'payment_method_id' => (int) $expense->payment_method_id,
                'amount' => (float) $expense->amount,
                'cheque_no_present' => ! empty($expense->cheque_no),
                'reason_present' => ! empty($expense->reason),
                'is_paid' => (bool) $expense->is_paid,
            ]
        );

        return redirect()->route('expenses.index')->with('status', 'Expense added successfully.');
    }

    public function edit(Expense $expense)
    {
        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('expenses.edit', [
            'expense' => $expense->load(['category', 'method']),
            'categories' => $categories,
            'methods' => $methods,
            'payees' => $this->payeeSuggestions(),
        ]);
    }

    /**
     * Distinct, non-empty payee names from prior expenses, used to back a
     * <datalist> autocomplete on the create/edit forms.
     */
    private function payeeSuggestions(): array
    {
        return Expense::query()
            ->select('payee_name')
            ->whereNotNull('payee_name')
            ->where('payee_name', '!=', '')
            ->distinct()
            ->orderBy('payee_name')
            ->limit(500)
            ->pluck('payee_name')
            ->all();
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $validated = $request->validated();

        $before = [
            'expense_date' => (string) $expense->expense_date,
            'payee_name' => (string) $expense->payee_name,
            'expense_category_id' => (int) $expense->expense_category_id,
            'payment_method_id' => (int) $expense->payment_method_id,
            'amount' => (float) $expense->amount,
            'cheque_no' => (string) ($expense->cheque_no ?? ''),
            'reason' => (string) ($expense->reason ?? ''),
            'is_paid' => (bool) $expense->is_paid,
        ];

        $expense->update($validated);

        $after = [
            'expense_date' => (string) $expense->expense_date,
            'payee_name' => (string) $expense->payee_name,
            'expense_category_id' => (int) $expense->expense_category_id,
            'payment_method_id' => (int) $expense->payment_method_id,
            'amount' => (float) $expense->amount,
            'cheque_no' => (string) ($expense->cheque_no ?? ''),
            'reason' => (string) ($expense->reason ?? ''),
            'is_paid' => (bool) $expense->is_paid,
        ];

        Audit::log(
            action: 'expense.updated',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Expense',
            targetId: (string) $expense->id,
            meta: [
                'changed' => [
                    'expense_date' => $before['expense_date'] !== $after['expense_date'],
                    'payee_name' => $before['payee_name'] !== $after['payee_name'],
                    'expense_category_id' => $before['expense_category_id'] !== $after['expense_category_id'],
                    'payment_method_id' => $before['payment_method_id'] !== $after['payment_method_id'],
                    'amount' => $before['amount'] !== $after['amount'],
                    'cheque_no' => $before['cheque_no'] !== $after['cheque_no'],
                    'reason' => $before['reason'] !== $after['reason'],
                    'is_paid' => $before['is_paid'] !== $after['is_paid'],
                ],
                'before' => [
                    'expense_date' => $before['expense_date'],
                    'expense_category_id' => $before['expense_category_id'],
                    'payment_method_id' => $before['payment_method_id'],
                    'amount' => $before['amount'],
                    'cheque_no_present' => $before['cheque_no'] !== '',
                    'reason_present' => $before['reason'] !== '',
                    'is_paid' => $before['is_paid'],
                ],
                'after' => [
                    'expense_date' => $after['expense_date'],
                    'expense_category_id' => $after['expense_category_id'],
                    'payment_method_id' => $after['payment_method_id'],
                    'amount' => $after['amount'],
                    'cheque_no_present' => $after['cheque_no'] !== '',
                    'reason_present' => $after['reason'] !== '',
                    'is_paid' => $after['is_paid'],
                ],
            ]
        );

        return redirect()->route('expenses.index')->with('status', 'Expense updated successfully.');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $meta = [
            'expense_date' => (string) $expense->expense_date,
            'payee_name' => (string) $expense->payee_name,
            'expense_category_id' => (int) $expense->expense_category_id,
            'payment_method_id' => (int) $expense->payment_method_id,
            'amount' => (float) $expense->amount,
            'cheque_no_present' => ! empty($expense->cheque_no),
            'reason_present' => ! empty($expense->reason),
            'is_paid' => (bool) $expense->is_paid,
        ];

        $id = (string) $expense->id;

        $expense->delete();

        Audit::log(
            action: 'expense.deleted',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Expense',
            targetId: $id,
            meta: $meta
        );

        return redirect()->route('expenses.index')->with('status', 'Expense deleted successfully.');
    }
}
