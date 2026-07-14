<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_name' => ['required', 'string', 'max:150'],
            'suggested_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_locked' => ['sometimes', 'boolean'],
            'ai_slash_priority' => ['nullable', 'integer', 'min:1', 'max:255'],
        ];
    }
}
