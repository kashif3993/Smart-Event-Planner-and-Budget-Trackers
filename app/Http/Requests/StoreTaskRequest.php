<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'task_name' => ['required', 'string', 'max:255'],
            'phase' => ['required', 'in:Pre-Planning,Preparation,Day-Of'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:Low,Medium,High'],
            'dependency_task_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where('event_id', $this->route('event')?->id),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
