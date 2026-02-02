<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeIncome;
use Illuminate\Http\Request;

class EmployeeIncomeController extends Controller
{
    public function index(Request $request)
    {
        $year = (int)($request->query('year', now()->year));
        $month = $request->query('month'); // optional filter

        $query = EmployeeIncome::query()
            ->with('employee')
            ->where('year', $year)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if (!empty($month)) {
            $query->where('month', (int)$month);
        }

        $rows = $query->paginate(20)->withQueryString();

        return view('emp_income.index', [
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function create()
    {
        $employees = Employee::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('emp_income.create', [
            'employees' => $employees,
            'defaultYear' => now()->year,
            'defaultMonth' => now()->month,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ]);

        // Ensure unique per employee/month/year
        $existing = EmployeeIncome::query()
            ->where('employee_id', $data['employee_id'])
            ->where('month', $data['month'])
            ->where('year', $data['year'])
            ->first();

        if ($existing) {
            return back()
                ->withErrors(['employee_id' => 'Entry already exists for this employee and month/year. Edit the existing one instead.'])
                ->withInput();
        }

        EmployeeIncome::create($data);

        return redirect()->route('admin.emp_income.index')->with('status', 'Employee income added.');
    }

    public function edit(EmployeeIncome $emp_income)
    {
        $employees = Employee::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('emp_income.edit', [
            'row' => $emp_income->load('employee'),
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, EmployeeIncome $emp_income)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ]);

        // Uniqueness check excluding current row
        $dupe = EmployeeIncome::query()
            ->where('employee_id', $data['employee_id'])
            ->where('month', $data['month'])
            ->where('year', $data['year'])
            ->where('id', '!=', $emp_income->id)
            ->exists();

        if ($dupe) {
            return back()
                ->withErrors(['employee_id' => 'Another entry already exists for this employee and month/year.'])
                ->withInput();
        }

        $emp_income->update($data);

        return redirect()->route('admin.emp_income.index')->with('status', 'Employee income updated.');
    }

    public function destroy(EmployeeIncome $emp_income)
    {
        $emp_income->delete();
        return back()->with('status', 'Employee income deleted.');
    }
}
