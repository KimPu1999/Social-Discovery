<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Discovery;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InterestController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);

    Route::middleware(['web', 'guest'])->group(function (): void {
        Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
        Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');
        Route::post('/auth/reactivate', [AccountController::class, 'reactivate'])->middleware('throttle:auth');
    });

    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');

    Route::middleware(['web', 'auth:sanctum'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:auth');
        Route::put('/auth/password', [AuthController::class, 'updatePassword'])->middleware('throttle:sensitive');
        Route::put('/auth/email', [AuthController::class, 'updateEmail'])->middleware('throttle:sensitive');

        Route::middleware('verified')->group(function (): void {
            Route::get('/profile', [ProfileController::class, 'show']);
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::post('/profile/avatar', [ProfileController::class, 'avatar']);
            Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);
            Route::post('/profile/cover', [ProfileController::class, 'cover']);
            Route::delete('/profile/cover', [ProfileController::class, 'removeCover']);
            Route::get('/profile/interests', [InterestController::class, 'mine']);
            Route::put('/profile/interests', [InterestController::class, 'update']);
            Route::get('/settings', [SettingsController::class, 'show']);
            Route::put('/settings', [SettingsController::class, 'update']);
            Route::get('/settings/privacy', [SettingsController::class, 'privacy']);
            Route::put('/settings/privacy', [SettingsController::class, 'updatePrivacy']);
            Route::post('/account/deactivate', [AccountController::class, 'deactivate']);
            Route::post('/account/delete-request', [AccountController::class, 'deleteRequest']);
        });
    });

    Route::get('/interests', [InterestController::class, 'index']);

    // Phase 04 — Discovery & Smart Interest Matching (authenticated, verified users).
    Route::middleware(['web', 'auth:sanctum', 'verified'])->prefix('discovery')->group(function (): void {
        Route::middleware('throttle:discovery')->group(function (): void {
            Route::get('/overview', [Discovery\DiscoveryController::class, 'overview']);
            Route::get('/people', [Discovery\DiscoveryController::class, 'people']);
            Route::get('/recommended', [Discovery\DiscoveryController::class, 'recommended']);
            Route::get('/interests', [Discovery\DiscoveryController::class, 'interests']);
            Route::get('/people/{username}', [Discovery\DiscoveryController::class, 'show'])->where('username', '[a-zA-Z0-9_.-]+');
            Route::get('/preferences', [Discovery\DiscoveryPreferenceController::class, 'show']);
        });
        Route::put('/preferences', [Discovery\DiscoveryPreferenceController::class, 'update'])->middleware('throttle:discovery-write');
        Route::post('/feedback', [Discovery\DiscoveryFeedbackController::class, 'store'])->middleware('throttle:discovery-write');
        Route::delete('/feedback/{targetUserId}', [Discovery\DiscoveryFeedbackController::class, 'destroy'])->whereNumber('targetUserId')->middleware('throttle:discovery-write');
    });

    Route::middleware(['web', 'auth:sanctum', 'verified', 'role:super_admin'])->prefix('admin')->group(function (): void {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/status', [AdminUserController::class, 'status']);
        Route::patch('/users/{user}/role', [AdminUserController::class, 'role']);
    });

    Route::middleware(['web', 'auth:sanctum', 'verified', 'role:moderator,super_admin'])->prefix('moderator')->group(function (): void {
        Route::get('/overview', fn () => response()->json(['status' => 'ok', 'scope' => 'moderation']));
    });
});
