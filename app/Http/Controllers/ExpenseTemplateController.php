<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseTemplate;
use App\Models\PaymentMethod;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExpenseTemplateController extends Controller
{
    public function index()
    {
        $templates = ExpenseTemplate::query()
            ->with(['category', 'method'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return view('expenses.recurring.index', [
            'templates' => $templates,
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
                ->route('expenses.recurring.index')
                ->with('status', 'No expense categories/payment methods found. Configure them first.');
        }

        return view('expenses.recurring.create', [
            'categories' => $categories,
            'methods' => $methods,
            'payees' => $this->payeeSuggestions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $validated['created_by'] = Auth::id();

        $template = ExpenseTemplate::create($validated);

        Audit::log(
            action: 'expense_template.created',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'ExpenseTemplate',
            targetId: (string) $template->id,
            meta: [
                'name' => $template->name,
                'payee_name' => $template->payee_name,
                'amount' => (float) $template->amount,
                'auto_create' => (bool) $template->auto_create,
                'day_of_month' => (int) $template->day_of_month,
            ]
        );

        return redirect()->route('expenses.recurring.index')
            ->with('status', 'Recurring template added.');
    }

    public function edit(ExpenseTemplate $template)
    {
        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('expenses.recurring.edit', [
            'template' => $template->load(['category', 'method']),
            'categories' => $categories,
            'methods' => $methods,
            'payees' => $this->payeeSuggestions(),
        ]);
    }

    /**
     * Distinct payee names from prior expenses AND existing templates,
     * used to back a <datalist> autocomplete on the template forms.
     */
    private function payeeSuggestions(): array
    {
        $fromExpenses = Expense::query()
            ->select('payee_name')
            ->whereNotNull('payee_name')
            ->where('payee_name', '!=', '')
            ->distinct()
            ->pluck('payee_name');

        $fromTemplates = ExpenseTemplate::query()
            ->select('payee_name')
            ->whereNotNull('payee_name')
            ->where('payee_name', '!=', '')
            ->distinct()
            ->pluck('payee_name');

        return $fromExpenses
            ->merge($fromTemplates)
            ->unique()
            ->sort()
            ->take(500)
            ->values()
            ->all();
    }

    public function update(Request $request, ExpenseTemplate $template)
    {
        $validated = $this->validatePayload($request);

        $template->update($validated);

        Audit::log(
            action: 'expense_template.updated',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'ExpenseTemplate',
            targetId: (string) $template->id,
            meta: [
                'name' => $template->name,
                'auto_create' => (bool) $template->auto_create,
                'day_of_month' => (int) $template->day_of_month,
                'is_active' => (bool) $template->is_active,
            ]
        );

        return redirect()->route('expenses.recurring.index')
            ->with('status', 'Recurring template updated.');
    }

    public function destroy(Request $request, ExpenseTemplate $template)
    {
        $id = (string) $template->id;
        $meta = ['name' => $template->name, 'payee_name' => $template->payee_name];

        $template->delete();

        Audit::log(
            action: 'expense_template.deleted',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'ExpenseTemplate',
            targetId: $id,
            meta: $meta
        );

        return redirect()->route('expenses.recurring.index')
            ->with('status', 'Recurring template deleted.');
    }

    /**
     * Manually materialize a template into an Expense for today.
     */
    public function insert(Request $request, ExpenseTemplate $template)
    {
        if (! $template->is_active) {
            return back()->withErrors(['template' => 'This template is inactive.']);
        }

        $expense = Expense::create($template->toExpenseAttributes(now(), Auth::id()));

        Audit::log(
            action: 'expense_template.inserted',
            category: 'expenses',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Expense',
            targetId: (string) $expense->id,
            meta: [
                'template_id' => $template->id,
                'amount' => (float) $expense->amount,
                'payee_name' => $expense->payee_name,
                'source' => 'manual',
            ]
        );

        return redirect()->route('expenses.index')
            ->with('status', 'Expense created from template "'.$template->name.'".');
    }

    private function validatePayload(Request $request): array
    {
        $validCategoryIds = ExpenseCategory::where('is_active', true)->pluck('id')->all();
        $validMethodIds = PaymentMethod::where('is_active', true)->pluck('id')->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'payee_name' => ['required', 'string', 'max:120'],
            'expense_category_id' => ['required', Rule::in($validCategoryIds)],
            'payment_method_id' => ['required', Rule::in($validMethodIds)],
            'amount' => ['required', 'numeric', 'min:0'],
            'cheque_no' => ['nullable', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:255'],
            'is_paid_default' => ['nullable', 'boolean'],
            'auto_create' => ['nullable', 'boolean'],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:28'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_paid_default'] = $request->boolean('is_paid_default');
        $data['auto_create'] = $request->boolean('auto_create');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
