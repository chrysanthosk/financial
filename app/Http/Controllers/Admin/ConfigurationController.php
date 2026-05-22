<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfigEntityRequest;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

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
        if (! $system) {
            $system = new SystemSetting;
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
    public function storeIncomeSource(ConfigEntityRequest $request)
    {
        IncomeSource::create($request->configData());

        return back()->with('status', 'Income source added.');
    }

    public function updateIncomeSource(ConfigEntityRequest $request, IncomeSource $incomeSource)
    {
        $incomeSource->update($request->configData());

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
    public function storeExpenseCategory(ConfigEntityRequest $request)
    {
        ExpenseCategory::create($request->configData());

        return back()->with('status', 'Expense category added.');
    }

    public function updateExpenseCategory(ConfigEntityRequest $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($request->configData());

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
    public function storePaymentMethod(ConfigEntityRequest $request)
    {
        PaymentMethod::create($request->configData());

        return back()->with('status', 'Payment method added.');
    }

    public function updatePaymentMethod(ConfigEntityRequest $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update($request->configData());

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
    public function storeEmployee(ConfigEntityRequest $request)
    {
        Employee::create($request->configData());

        return back()->with('status', 'Employee added.');
    }

    public function updateEmployee(ConfigEntityRequest $request, Employee $employee)
    {
        $employee->update($request->configData());

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
