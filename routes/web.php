<?php

use App\Http\Controllers\Web\GroupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name', 'Laravel'),
    ]);
});

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/join', [GroupController::class, 'join'])->name('groups.join');
    Route::post('/groups/join', [GroupController::class, 'processJoin'])->name('groups.process-join');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
});
