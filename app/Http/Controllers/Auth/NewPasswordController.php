<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Show the "choose a new password" form.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Save the new password directly.
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        User::where('email', $request->email)->update([
            'password' => Hash::make($request->string('password')),
        ]);

        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
    }
}
