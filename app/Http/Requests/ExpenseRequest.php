<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already gated by the 'auth' middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // An unchecked checkbox is not submitted; normalize to a real boolean.
        $this->merge(['is_paid' => $this->boolean('is_paid')]);
    }

    public function rules(): array
    {
        $validCategoryIds = ExpenseCategory::where('is_active', true)->pluck('id')->all();
        $validMethodIds = PaymentMethod::where('is_active', true)->pluck('id')->all();

        return [
            // Bound the year so a mistyped date (e.g. "0205-02-10") cannot skew
            // every all-time report. Matches EmployeeIncomeRequest's year range.
            'expense_date' => ['required', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'payee_name' => ['required', 'string', 'max:120'],
            'expense_category_id' => ['required', Rule::in($validCategoryIds)],
            'payment_method_id' => ['required', Rule::in($validMethodIds)],
            // Column is decimal(12,2); cap below its max to avoid overflow.
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'cheque_no' => ['nullable', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:255'],
            'is_paid' => ['nullable', 'boolean'],
        ];
    }
}
