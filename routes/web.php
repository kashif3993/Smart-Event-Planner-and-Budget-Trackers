<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TaskController;
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

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::post('/events/{event}/tasks', [TaskController::class, 'store'])->name('events.tasks.store');
    Route::put('/events/{event}/tasks/{task}', [TaskController::class, 'update'])->name('events.tasks.update');
    Route::delete('/events/{event}/tasks/{task}', [TaskController::class, 'destroy'])->name('events.tasks.destroy');
    Route::patch('/events/{event}/tasks/{task}/toggle', [TaskController::class, 'toggleStatus'])->name('events.tasks.toggle');
    Route::post('/events/{event}/tasks/generate-ai', [TaskController::class, 'generateAi'])->name('events.tasks.generateAi');

    Route::resource('expenses', \App\Http\Controllers\ExpenseController::class)->except(['create', 'edit', 'show']);
    Route::get('/events/{event}/vendor-categories', [\App\Http\Controllers\ExpenseController::class, 'categoriesByEvent'])->name('events.vendorCategories');

    Route::resource('vendor-categories', \App\Http\Controllers\VendorCategoryController::class)->except(['create', 'edit', 'show']);
    Route::patch('/vendor-categories/{vendorCategory}/toggle-lock', [\App\Http\Controllers\VendorCategoryController::class, 'toggleLock'])->name('vendor-categories.toggleLock');

    Route::get('/timeline', [\App\Http\Controllers\TimelineController::class, 'index'])->name('timeline.index');

    Route::get('/budget', [\App\Http\Controllers\BudgetController::class, 'index'])->name('budget.index');
    Route::get('/budget/export', [\App\Http\Controllers\BudgetController::class, 'export'])->name('budget.export');

    Route::post('/events/{event}/rebalance-preview', [\App\Http\Controllers\BudgetRebalanceController::class, 'preview'])->name('budget.rebalancePreview');
    Route::post('/events/{event}/rebalance-commit', [\App\Http\Controllers\BudgetRebalanceController::class, 'commit'])->name('budget.rebalanceCommit');

    Route::get('/progress', [\App\Http\Controllers\ProgressController::class, 'index'])->name('progress.index');
    Route::get('/progress/export', [\App\Http\Controllers\ProgressController::class, 'export'])->name('progress.export');

    Route::get('/activity', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activity.index');
    Route::delete('/activity/clear-all', [\App\Http\Controllers\ActivityController::class, 'clearAll'])->name('activity.clearAll');
    Route::delete('/activity/{activity}', [\App\Http\Controllers\ActivityController::class, 'destroy'])->name('activity.destroy');
});
