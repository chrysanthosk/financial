<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Support\Audit;
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

        // Use [startOfMonth, startOfNextMonth) to avoid end-of-month/time issues
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end   = (clone $start)->addMonth(); // exclusive

        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $query = Expense::query()
            ->with(['category', 'method'])
            ->where('expense_date', '>=', $start)
            ->where('expense_date', '<', $end)
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
            ->where('expense_date', '>=', $start)
            ->where('expense_date', '<', $end)
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

        $chequeId = PaymentMethod::where('name', 'Cheque')->value('id');
        if ($chequeId && (int)$validated['payment_method_id'] !== (int)$chequeId) {
            // Optional strict behavior:
            // $validated['cheque_no'] = null;
        }

        $validated['created_by'] = Auth::id();

        $expense = Expense::create($validated);

        Audit::log(
            action: 'expense.created',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Expense',
            targetId: (string)$expense->id,
            meta: [
                'expense_date' => (string)$expense->expense_date,
                'payee_name' => (string)$expense->payee_name,
                'expense_category_id' => (int)$expense->expense_category_id,
                'payment_method_id' => (int)$expense->payment_method_id,
                'amount' => (float)$expense->amount,
                'cheque_no_present' => !empty($expense->cheque_no),
                'reason_present' => !empty($expense->reason),
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

        $before = [
            'expense_date' => (string)$expense->expense_date,
            'payee_name' => (string)$expense->payee_name,
            'expense_category_id' => (int)$expense->expense_category_id,
            'payment_method_id' => (int)$expense->payment_method_id,
            'amount' => (float)$expense->amount,
            'cheque_no' => (string)($expense->cheque_no ?? ''),
            'reason' => (string)($expense->reason ?? ''),
        ];

        $expense->update($validated);

        $after = [
            'expense_date' => (string)$expense->expense_date,
            'payee_name' => (string)$expense->payee_name,
            'expense_category_id' => (int)$expense->expense_category_id,
            'payment_method_id' => (int)$expense->payment_method_id,
            'amount' => (float)$expense->amount,
            'cheque_no' => (string)($expense->cheque_no ?? ''),
            'reason' => (string)($expense->reason ?? ''),
        ];

        Audit::log(
            action: 'expense.updated',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Expense',
            targetId: (string)$expense->id,
            meta: [
                'changed' => [
                    'expense_date' => $before['expense_date'] !== $after['expense_date'],
                    'payee_name' => $before['payee_name'] !== $after['payee_name'],
                    'expense_category_id' => $before['expense_category_id'] !== $after['expense_category_id'],
                    'payment_method_id' => $before['payment_method_id'] !== $after['payment_method_id'],
                    'amount' => $before['amount'] !== $after['amount'],
                    'cheque_no' => $before['cheque_no'] !== $after['cheque_no'],
                    'reason' => $before['reason'] !== $after['reason'],
                ],
                'before' => [
                    'expense_date' => $before['expense_date'],
                    'expense_category_id' => $before['expense_category_id'],
                    'payment_method_id' => $before['payment_method_id'],
                    'amount' => $before['amount'],
                    'cheque_no_present' => $before['cheque_no'] !== '',
                    'reason_present' => $before['reason'] !== '',
                ],
                'after' => [
                    'expense_date' => $after['expense_date'],
                    'expense_category_id' => $after['expense_category_id'],
                    'payment_method_id' => $after['payment_method_id'],
                    'amount' => $after['amount'],
                    'cheque_no_present' => $after['cheque_no'] !== '',
                    'reason_present' => $after['reason'] !== '',
                ],
            ]
        );

        return redirect()->route('expenses.index')->with('status', 'Expense updated successfully.');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $meta = [
            'expense_date' => (string)$expense->expense_date,
            'payee_name' => (string)$expense->payee_name,
            'expense_category_id' => (int)$expense->expense_category_id,
            'payment_method_id' => (int)$expense->payment_method_id,
            'amount' => (float)$expense->amount,
            'cheque_no_present' => !empty($expense->cheque_no),
            'reason_present' => !empty($expense->reason),
        ];

        $id = (string)$expense->id;

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
