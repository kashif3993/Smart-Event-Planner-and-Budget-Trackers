<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
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
            'event_name' => ['required', 'string', 'max:200'],
            'event_type' => ['required', 'in:Wedding,Birthday Party,Corporate Event,Baby Shower,Graduation,Custom'],
            'custom_event_type' => ['nullable', 'required_if:event_type,Custom', 'string', 'max:100'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'guest_count' => ['nullable', 'integer', 'min:0'],
            'max_guests' => ['nullable', 'integer', 'min:0'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue_image' => ['nullable', 'image', 'max:2048'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'budget_spent' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'in:PKR,USD'],
            'description' => ['nullable', 'string'],
        ];
    }
}
