<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    /**
     * Show the "enter your email" form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email is verified to exist — go straight to the new-password form.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        return redirect()->route('password.reset', ['email' => $request->email]);
    }
}
