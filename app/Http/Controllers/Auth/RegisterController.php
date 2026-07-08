<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle the registration form submission.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $imagePath = null;

        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')
                ->store('profile-images', 'public');
        }

        $user = User::create([
            'full_name'     => $request->full_name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'profile_image' => $imagePath,
        ]);

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to Smart Event Planner, ' . $user->full_name . '!');
    }
}
