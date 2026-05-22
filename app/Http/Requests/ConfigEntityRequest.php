<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for the simple "config" entities managed on the
 * configuration screen (income sources, expense categories, payment
 * methods, employees). They all share the same name/sort_order/is_active
 * shape.
 */
class ConfigEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already gated by the 'admin' middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Checkbox semantics: present+truthy => true, absent => false.
        // (Edit forms also post a hidden is_active=0, so this is consistent
        // across both create and update.)
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Normalized attributes ready to persist.
     *
     * @return array{name: string, sort_order: int, is_active: bool}
     */
    public function configData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
