<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncomeSource;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\SystemSetting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ConfigurationController extends Controller
{
    public function index()
    {
        $incomeSources = IncomeSource::orderBy('sort_order')->orderBy('name')->get();
        $expenseCategories = ExpenseCategory::orderBy('sort_order')->orderBy('name')->get();
        $paymentMethods = PaymentMethod::orderBy('sort_order')->orderBy('name')->get();

        // ✅ REQUIRED so the settings page shows the current values
        $system = SystemSetting::current();

        return view('admin.settings.configuration', compact(
            'incomeSources',
            'expenseCategories',
            'paymentMethods',
            'system'
        ));
    }

    /* ---------------------------
     | Income Sources
     * --------------------------*/
    public function storeIncomeSource(Request $request)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $row = IncomeSource::create([
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => (bool)($validated['is_active'] ?? true),
        ]);

        Audit::log(
            action: 'config.income_source_created',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'IncomeSource',
            targetId: (string)$row->id,
            meta: ['name' => $row->name, 'sort_order' => $row->sort_order, 'is_active' => $row->is_active]
        );

        return back()->with('status', 'Income source added.');
    }

    public function updateIncomeSource(Request $request, IncomeSource $incomeSource)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $before = $incomeSource->only(['name', 'sort_order', 'is_active']);

        $incomeSource->name = $validated['name'];
        $incomeSource->sort_order = $validated['sort_order'] ?? 0;
        $incomeSource->is_active = (bool)($validated['is_active'] ?? false);
        $incomeSource->save();

        Audit::log(
            action: 'config.income_source_updated',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'IncomeSource',
            targetId: (string)$incomeSource->id,
            meta: [
                'before' => $before,
                'after'  => $incomeSource->only(['name', 'sort_order', 'is_active']),
            ]
        );

        return back()->with('status', 'Income source updated.');
    }

    public function destroyIncomeSource(Request $request, IncomeSource $incomeSource)
    {
        if ($incomeSource->incomes()->exists()) {
            return back()->withErrors([
                'config' => "You can't delete '{$incomeSource->name}' because it is used by existing income entries.",
            ]);
        }

        $id = (string)$incomeSource->id;
        $name = $incomeSource->name;

        try {
            $incomeSource->delete();
        } catch (QueryException $e) {
            return back()->withErrors(['config' => 'Delete failed: ' . $e->getMessage()]);
        }

        Audit::log(
            action: 'config.income_source_deleted',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'IncomeSource',
            targetId: $id,
            meta: ['name' => $name]
        );

        return back()->with('status', 'Income source deleted.');
    }

    /* ---------------------------
     | Expense Categories
     * --------------------------*/
    public function storeExpenseCategory(Request $request)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $row = ExpenseCategory::create([
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => (bool)($validated['is_active'] ?? true),
        ]);

        Audit::log(
            action: 'config.expense_category_created',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'ExpenseCategory',
            targetId: (string)$row->id,
            meta: ['name' => $row->name, 'sort_order' => $row->sort_order, 'is_active' => $row->is_active]
        );

        return back()->with('status', 'Expense category added.');
    }

    public function updateExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $before = $expenseCategory->only(['name', 'sort_order', 'is_active']);

        $expenseCategory->name = $validated['name'];
        $expenseCategory->sort_order = $validated['sort_order'] ?? 0;
        $expenseCategory->is_active = (bool)($validated['is_active'] ?? false);
        $expenseCategory->save();

        Audit::log(
            action: 'config.expense_category_updated',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'ExpenseCategory',
            targetId: (string)$expenseCategory->id,
            meta: [
                'before' => $before,
                'after'  => $expenseCategory->only(['name', 'sort_order', 'is_active']),
            ]
        );

        return back()->with('status', 'Expense category updated.');
    }

    public function destroyExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->expenses()->exists()) {
            return back()->withErrors([
                'config' => "You can't delete '{$expenseCategory->name}' because it is used by existing expense entries.",
            ]);
        }

        $id = (string)$expenseCategory->id;
        $name = $expenseCategory->name;

        try {
            $expenseCategory->delete();
        } catch (QueryException $e) {
            return back()->withErrors(['config' => 'Delete failed: ' . $e->getMessage()]);
        }

        Audit::log(
            action: 'config.expense_category_deleted',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'ExpenseCategory',
            targetId: $id,
            meta: ['name' => $name]
        );

        return back()->with('status', 'Expense category deleted.');
    }

    /* ---------------------------
     | Payment Methods
     * --------------------------*/
    public function storePaymentMethod(Request $request)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $row = PaymentMethod::create([
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => (bool)($validated['is_active'] ?? true),
        ]);

        Audit::log(
            action: 'config.payment_method_created',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'PaymentMethod',
            targetId: (string)$row->id,
            meta: ['name' => $row->name, 'sort_order' => $row->sort_order, 'is_active' => $row->is_active]
        );

        return back()->with('status', 'Payment method added.');
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $before = $paymentMethod->only(['name', 'sort_order', 'is_active']);

        $paymentMethod->name = $validated['name'];
        $paymentMethod->sort_order = $validated['sort_order'] ?? 0;
        $paymentMethod->is_active = (bool)($validated['is_active'] ?? false);
        $paymentMethod->save();

        Audit::log(
            action: 'config.payment_method_updated',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'PaymentMethod',
            targetId: (string)$paymentMethod->id,
            meta: [
                'before' => $before,
                'after'  => $paymentMethod->only(['name', 'sort_order', 'is_active']),
            ]
        );

        return back()->with('status', 'Payment method updated.');
    }

    public function destroyPaymentMethod(Request $request, PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->expenses()->exists()) {
            return back()->withErrors([
                'config' => "You can't delete '{$paymentMethod->name}' because it is used by existing expense entries.",
            ]);
        }

        $id = (string)$paymentMethod->id;
        $name = $paymentMethod->name;

        try {
            $paymentMethod->delete();
        } catch (QueryException $e) {
            return back()->withErrors(['config' => 'Delete failed: ' . $e->getMessage()]);
        }

        Audit::log(
            action: 'config.payment_method_deleted',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'PaymentMethod',
            targetId: $id,
            meta: ['name' => $name]
        );

        return back()->with('status', 'Payment method deleted.');
    }

    /* ---------------------------
     | System
     * --------------------------*/
    public function updateSystem(Request $request)
    {
        $validated = $request->validate([
            'header_name' => ['required', 'string', 'max:255'],
            'footer_name' => ['required', 'string', 'max:255'],
        ]);

        $system = SystemSetting::current();

        $before = [
            'header_name' => $system->header_name,
            'footer_name' => $system->footer_name,
        ];

        $system->update($validated);

        Audit::log(
            action: 'system.updated',
            category: 'settings',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'SystemSetting',
            targetId: '1',
            meta: [
                'changed' => [
                    'header_name' => $before['header_name'] !== $system->header_name,
                    'footer_name' => $before['footer_name'] !== $system->footer_name,
                ],
                'values' => $validated,
            ]
        );

        return back()->with('status', 'System settings updated.');
    }
}
