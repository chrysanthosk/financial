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
            'expense_date' => ['required', 'date'],
            'payee_name' => ['required', 'string', 'max:120'],
            'expense_category_id' => ['required', Rule::in($validCategoryIds)],
            'payment_method_id' => ['required', Rule::in($validMethodIds)],
            'amount' => ['required', 'numeric', 'min:0'],
            'cheque_no' => ['nullable', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:255'],
            'is_paid' => ['nullable', 'boolean'],
        ];
    }
}
