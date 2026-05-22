<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already gated by the 'admin' middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            // Uniqueness is enforced here (backed by the DB unique index on
            // employee_id+month+year), removing the previous read-then-write race.
            'employee_id' => [
                'required',
                'exists:employees,id',
                Rule::unique('employee_incomes', 'employee_id')
                    ->where('month', $this->input('month'))
                    ->where('year', $this->input('year'))
                    ->ignore($this->route('emp_income')),
            ],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.unique' => 'An entry already exists for this employee and month/year. Edit the existing one instead.',
        ];
    }
}
