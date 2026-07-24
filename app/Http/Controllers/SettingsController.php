<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(protected DatabaseBackupService $backups)
    {
    }

    public function index(): View
    {
        return view('settings.index', [
            'user' => Auth::user(),
            'backups' => $this->backups->list(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $data['profile_image'] = $request->file('profile_image')->store('profile-images', 'public');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $user->update(['password' => Hash::make($request->validated()['password'])]);

        return back()->with('success', 'Password changed successfully.');
    }

    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
        ]);

        $user = Auth::user();

        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your account has been deleted.');
    }

    public function createBackup(): RedirectResponse
    {
        $filename = $this->backups->create();

        return back()->with('success', 'Backup created: '.$filename);
    }

    public function downloadBackup(string $filename)
    {
        abort_unless($this->backups->exists($filename), 404);

        return Storage::disk('local')->download($this->backups->path($filename));
    }

    public function destroyBackup(string $filename): RedirectResponse
    {
        $this->backups->delete($filename);

        return back()->with('success', 'Backup deleted.');
    }
}
