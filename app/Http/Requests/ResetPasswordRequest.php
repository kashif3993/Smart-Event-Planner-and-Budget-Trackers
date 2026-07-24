<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'      => 'Please enter your email address.',
            'email.email'         => 'Please enter a valid email address.',
            'email.exists'        => "We couldn't find an account with that email address.",
            'password.required'   => 'Please enter a new password.',
            'password.min'        => 'Your password must be at least 8 characters.',
            'password.confirmed'  => 'Password confirmation does not match.',
        ];
    }
}
