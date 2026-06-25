<?php

namespace App\Http\Requests;

use App\Models\IncomeSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already gated by the 'auth' middleware.
        return true;
    }

    public function rules(): array
    {
        $validSourceIds = IncomeSource::where('is_active', true)->pluck('id')->all();

        return [
            'income_date' => ['required', 'date'],
            'income_source_id' => ['required', Rule::in($validSourceIds)],
            // Column is decimal(12,2); cap below its max to avoid overflow.
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
