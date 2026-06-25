<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeIncomeRequest;
use App\Models\Employee;
use App\Models\EmployeeIncome;
use App\Support\Audit;
use Illuminate\Http\Request;

class EmployeeIncomeController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->query('year', now()->year));
        $month = $request->query('month'); // optional filter

        $query = EmployeeIncome::query()
            ->with('employee')
            ->where('year', $year)
            ->orderBy('month', 'desc');

        if (! empty($month)) {
            $query->where('month', (int) $month);
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
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('emp_income.create', [
            'employees' => $employees,
            'defaultYear' => now()->year,
            'defaultMonth' => now()->month,
        ]);
    }

    public function store(EmployeeIncomeRequest $request)
    {
        $row = EmployeeIncome::create($request->validated());

        Audit::log(
            action: 'employee_income.created',
            category: 'income',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'EmployeeIncome',
            targetId: (string) $row->id,
            meta: [
                'employee_id' => $row->employee_id,
                'year' => $row->year,
                'month' => $row->month,
            ]
        );

        return redirect()->route('admin.emp_income.index')->with('status', 'Employee income added.');
    }

    public function edit(EmployeeIncome $emp_income)
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('emp_income.edit', [
            'row' => $emp_income->load('employee'),
            'employees' => $employees,
        ]);
    }

    public function update(EmployeeIncomeRequest $request, EmployeeIncome $emp_income)
    {
        $emp_income->update($request->validated());

        Audit::log(
            action: 'employee_income.updated',
            category: 'income',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'EmployeeIncome',
            targetId: (string) $emp_income->id,
            meta: [
                'employee_id' => $emp_income->employee_id,
                'year' => $emp_income->year,
                'month' => $emp_income->month,
            ]
        );

        return redirect()->route('admin.emp_income.index')->with('status', 'Employee income updated.');
    }

    public function destroy(Request $request, EmployeeIncome $emp_income)
    {
        $id = $emp_income->id;
        $emp_income->delete();

        Audit::log(
            action: 'employee_income.deleted',
            category: 'income',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'EmployeeIncome',
            targetId: (string) $id,
        );

        return back()->with('status', 'Employee income deleted.');
    }
}
