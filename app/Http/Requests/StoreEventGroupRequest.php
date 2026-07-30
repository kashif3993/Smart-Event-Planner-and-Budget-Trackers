<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class StoreEventGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'group_type' => ['required', 'in:Wedding,Birthday Party,Corporate Event,Baby Shower,Graduation,Custom'],
            'custom_group_type' => ['nullable', 'required_if:group_type,Custom', 'string', 'max:100'],
            'event_ids' => ['required', 'array', 'min:2', 'max:6'],
            'event_ids.*' => ['integer', 'distinct', 'exists:events,id'],
        ];
    }

    /**
     * EG-4 (2–6 members), EG-5 (not already grouped), EG-9 (same account),
     * EG-10 (single currency) — enforced here since they span multiple rows,
     * not expressible as single-field rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('event_ids', []);

            if (! is_array($ids) || empty($ids)) {
                return;
            }

            $events = \App\Models\Event::whereIn('id', $ids)->get();

            $foreign = $events->firstWhere('user_id', '!=', Auth::id());
            if ($foreign) {
                $validator->errors()->add('event_ids', 'You can only group events you own.');

                return;
            }

            $alreadyGrouped = $events->firstWhere('event_group_id', '!=', null);
            if ($alreadyGrouped) {
                $validator->errors()->add('event_ids', "\"{$alreadyGrouped->event_name}\" already belongs to another group. Remove it from that group first.");

                return;
            }

            $currencies = $events->pluck('currency')->unique();
            if ($currencies->count() > 1) {
                $validator->errors()->add('event_ids', 'All events in a group must share the same currency.');
            }
        });
    }
}
