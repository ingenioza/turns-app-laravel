<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\TurnController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes for authenticated users
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('settings', [AuthController::class, 'updateSettings']);
    });
    
    // User management routes
    Route::apiResource('users', UserController::class)->except(['store']);
    Route::prefix('users')->group(function () {
        Route::get('search', [UserController::class, 'search']);
        Route::get('recently-active', [UserController::class, 'recentlyActive']);
        Route::get('{user}/groups', [UserController::class, 'groups']);
        Route::post('{user}/settings', [UserController::class, 'updateSettings']);
    });
    
    // Group management routes
    Route::apiResource('groups', GroupController::class);
    Route::prefix('groups')->group(function () {
        Route::post('join', [GroupController::class, 'join']);
        Route::get('search', [GroupController::class, 'search']);
        Route::post('{group}/leave', [GroupController::class, 'leave']);
        Route::get('{group}/members', [GroupController::class, 'members']);
        Route::delete('{group}/members/{member}', [GroupController::class, 'removeMember']);
        Route::patch('{group}/members/{member}/role', [GroupController::class, 'updateMemberRole']);
    });
    
    // Turn management routes
    Route::apiResource('turns', TurnController::class)->except(['update']);
    Route::prefix('turns')->group(function () {
        Route::post('{turn}/complete', [TurnController::class, 'complete']);
        Route::post('{turn}/skip', [TurnController::class, 'skip']);
        Route::post('{turn}/force-end', [TurnController::class, 'forceEnd']);
        Route::get('user-stats', [TurnController::class, 'userStats']);
    });
    
    // Group-specific turn routes
    Route::prefix('groups/{group}/turns')->group(function () {
        Route::get('active', [TurnController::class, 'active']);
        Route::get('current', [TurnController::class, 'current']);
        Route::get('history', [TurnController::class, 'groupHistory']);
        Route::get('stats', [TurnController::class, 'groupStats']);
    });
});
