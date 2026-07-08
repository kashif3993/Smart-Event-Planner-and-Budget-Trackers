<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

/* ── Home ── */
Route::get('/', function () {
    return redirect()->route('register');
});

/* ── Auth ── */
Route::middleware('guest')->group(function () {
    Route::get('/register',  [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    
    Route::get('/login',     [LoginController::class, 'create'])->name('login');
    Route::post('/login',    [LoginController::class, 'store'])->name('login.store');
});

/* ── Protected ── */
Route::middleware('auth')->group(function () {
    Route::post('/logout',   [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
});
