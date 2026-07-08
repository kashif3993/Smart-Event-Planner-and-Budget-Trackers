<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'    => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:150', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'profile_image'=> ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'terms'        => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'  => 'Please enter your full name.',
            'email.required'      => 'Please enter your email address.',
            'email.unique'        => 'This email is already registered. Try signing in.',
            'password.required'   => 'Please create a password.',
            'password.min'        => 'Password must be at least 8 characters.',
            'password.confirmed'  => 'Passwords do not match.',
            'profile_image.image' => 'Only image files are allowed.',
            'profile_image.max'   => 'Profile image must be under 2MB.',
            'terms.accepted'      => 'You must agree to the Terms & Conditions.',
        ];
    }
}
