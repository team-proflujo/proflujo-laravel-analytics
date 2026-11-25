<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

Route::get('/', function () {
    return redirect('/analytics');
});

// Workbench-only convenience: log in as the seeded user, then run `php artisan analytics:aggregate --from=...`.
Route::get('/login', function () {
    Auth::login(User::query()->firstOrFail());

    return redirect('/analytics');
})->name('login');
