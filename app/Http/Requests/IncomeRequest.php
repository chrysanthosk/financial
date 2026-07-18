<?php

namespace App\Http\Requests;

use App\Models\IncomeSource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class IncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already gated by the 'auth' middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'income_date' => ['required', 'date'],
            // One amount per active source, keyed by source id.
            'amounts' => ['required', 'array'],
            // Column is decimal(12,2); cap below its max to avoid overflow.
            'amounts.*' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $amounts = $this->input('amounts');

                if (! is_array($amounts)) {
                    return;
                }

                $validSourceIds = IncomeSource::where('is_active', true)->pluck('id')->all();

                foreach (array_keys($amounts) as $sourceId) {
                    if (! in_array((int) $sourceId, $validSourceIds, true)) {
                        $validator->errors()->add('amounts', 'One or more income sources are invalid.');

                        return;
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'amounts.*' => 'amount',
        ];
    }
}
