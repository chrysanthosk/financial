<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use App\Models\SystemSetting;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigurationController extends Controller
{
    public function index()
    {
        $system = SystemSetting::safeCurrent();

        $incomeSources = IncomeSource::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $expenseCategories = ExpenseCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $employees = Employee::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.settings.configuration', compact(
            'system',
            'incomeSources',
            'expenseCategories',
            'paymentMethods',
            'employees'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | System settings
    |--------------------------------------------------------------------------
    */
    public function updateSystem(Request $request)
    {
        $data = $request->validate([
            'header_name' => ['required', 'string', 'max:255'],
            'footer_name' => ['required', 'string', 'max:255'],
        ]);

        // "safeCurrent" pattern: if table doesn't exist, safeCurrent returns null.
        $system = SystemSetting::safeCurrent();

        // If settings table exists but record doesn't, create it.
        if (!$system) {
            $system = new SystemSetting();
        }

        $system->header_name = $data['header_name'];
        $system->footer_name = $data['footer_name'];
        $system->save();

        return back()->with('status', 'System settings saved.');
    }

    /*
    |--------------------------------------------------------------------------
    | Income Sources
    |--------------------------------------------------------------------------
    */
    public function storeIncomeSource(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        IncomeSource::create([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool)($data['is_active'] ?? true),
        ]);

        return back()->with('status', 'Income source added.');
    }

    public function updateIncomeSource(Request $request, IncomeSource $incomeSource)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $incomeSource->update([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool)($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'Income source updated.');
    }

    public function destroyIncomeSource(IncomeSource $incomeSource)
    {
        if ($incomeSource->incomes()->exists()) {
            return back()->withErrors([
                'config' => 'Cannot delete income source "'.$incomeSource->name.'" — it is used by one or more income entries. Deactivate it instead.',
            ]);
        }

        $incomeSource->delete();
        return back()->with('status', 'Income source deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Expense Categories
    |--------------------------------------------------------------------------
    */
    public function storeExpenseCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ExpenseCategory::create([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool)($data['is_active'] ?? true),
        ]);

        return back()->with('status', 'Expense category added.');
    }

    public function updateExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $expenseCategory->update([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool)($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'Expense category updated.');
    }

    public function destroyExpenseCategory(ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->expenses()->exists() || $expenseCategory->templates()->exists()) {
            return back()->withErrors([
                'config' => 'Cannot delete expense category "'.$expenseCategory->name.'" — it is used by one or more expenses or recurring templates. Deactivate it instead.',
            ]);
        }

        $expenseCategory->delete();
        return back()->with('status', 'Expense category deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    */
    public function storePaymentMethod(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        PaymentMethod::create([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool)($data['is_active'] ?? true),
        ]);

        return back()->with('status', 'Payment method added.');
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paymentMethod->update([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool)($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'Payment method updated.');
    }

    public function destroyPaymentMethod(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->expenses()->exists() || $paymentMethod->templates()->exists()) {
            return back()->withErrors([
                'config' => 'Cannot delete payment method "'.$paymentMethod->name.'" — it is used by one or more expenses or recurring templates. Deactivate it instead.',
            ]);
        }

        $paymentMethod->delete();
        return back()->with('status', 'Payment method deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Employees (NEW)
    |--------------------------------------------------------------------------
    */
    public function storeEmployee(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Employee::create([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool)($data['is_active'] ?? true),
        ]);

        return back()->with('status', 'Employee added.');
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $employee->update([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool)($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'Employee updated.');
    }

    public function destroyEmployee(Employee $employee)
    {
        if ($employee->incomes()->exists()) {
            return back()->withErrors([
                'config' => 'Cannot delete employee "'.$employee->name.'" — it has linked employee income entries. Deactivate it instead.',
            ]);
        }

        $employee->delete();

        return back()->with('status', 'Employee deleted.');
    }
}
