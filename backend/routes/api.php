<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Discovery;
use App\Http\Controllers\Api\V1\Feed;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InterestController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\Social\BlockingController;
use App\Http\Controllers\Api\V1\Social\ConnectionController;
use App\Http\Controllers\Api\V1\Social\ConnectionRequestController;
use App\Http\Controllers\Api\V1\Social\FollowController;
use App\Http\Controllers\Api\V1\Social\MutualConnectionController;
use App\Http\Controllers\Api\V1\Social\NotificationController;
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

    // Phase 05 — Social connections, follows, friend requests, blocking (authenticated, verified users).
    Route::middleware(['web', 'auth:sanctum', 'verified'])->group(function (): void {
        Route::middleware('throttle:social-write')->group(function (): void {
            Route::post('/users/{user}/follow', [FollowController::class, 'follow'])->whereNumber('user');
            Route::delete('/users/{user}/follow', [FollowController::class, 'unfollow'])->whereNumber('user');
            Route::post('/users/{user}/block', [BlockingController::class, 'block'])->whereNumber('user');
            Route::delete('/users/{user}/block', [BlockingController::class, 'unblock'])->whereNumber('user');
            Route::post('/connections/requests', [ConnectionRequestController::class, 'store']);
            Route::post('/connections/requests/{requestId}/accept', [ConnectionRequestController::class, 'accept'])->whereNumber('requestId');
            Route::post('/connections/requests/{requestId}/decline', [ConnectionRequestController::class, 'decline'])->whereNumber('requestId');
            Route::post('/connections/requests/{requestId}/cancel', [ConnectionRequestController::class, 'cancel'])->whereNumber('requestId');
            Route::delete('/connections/{user}', [ConnectionController::class, 'destroy'])->whereNumber('user');
            Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
            Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read'])->whereUuid('notificationId');
        });

        Route::get('/users/{user}/relationship', [FollowController::class, 'relationship'])->whereNumber('user');
        Route::get('/users/{user}/followers', [FollowController::class, 'followers'])->whereNumber('user');
        Route::get('/users/{user}/following', [FollowController::class, 'following'])->whereNumber('user');
        Route::get('/users/{user}/connections', [ConnectionController::class, 'forUser'])->whereNumber('user');
        Route::get('/users/{user}/mutual-connections', [MutualConnectionController::class, 'show'])->whereNumber('user');
        Route::get('/connections', [ConnectionController::class, 'index']);
        Route::get('/connections/requests/incoming', [ConnectionRequestController::class, 'incoming']);
        Route::get('/connections/requests/outgoing', [ConnectionRequestController::class, 'outgoing']);
        Route::get('/settings/blocked-users', [BlockingController::class, 'index']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    });

    Route::middleware(['web', 'auth:sanctum', 'verified', 'role:super_admin'])->prefix('admin')->group(function (): void {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/status', [AdminUserController::class, 'status']);
        Route::patch('/users/{user}/role', [AdminUserController::class, 'role']);
    });

    // Phase 06 — Social feed, posts, media, comments, saves, shares,
    // hashtags, search, reports (authenticated, verified users).
    Route::middleware(['web', 'auth:sanctum', 'verified'])->group(function (): void {
        Route::middleware('throttle:feed-read')->group(function (): void {
            Route::get('/feed', [Feed\FeedController::class, 'index']);
            Route::get('/posts/search', [Feed\PostController::class, 'search']);
            Route::get('/posts/{post}', [Feed\PostController::class, 'show'])->whereNumber('post');
            Route::get('/me/posts', [Feed\PostController::class, 'mine']);
            Route::get('/me/posts/drafts', [Feed\PostController::class, 'drafts']);
            Route::get('/me/saved-posts', [Feed\SavedPostController::class, 'index']);
            Route::get('/users/{user}/posts', [Feed\PostController::class, 'forUser'])->whereNumber('user');
            Route::get('/hashtags', [Feed\HashtagController::class, 'index']);
            Route::get('/hashtags/{slug}/posts', [Feed\HashtagController::class, 'posts']);
            Route::get('/posts/{post}/comments', [Feed\PostCommentController::class, 'index'])->whereNumber('post');
            Route::get('/feed/media/{uuid}', [Feed\PostMediaController::class, 'show']);
            Route::get('/posts/{post}/share-preview', [Feed\PostSharingController::class, 'preview'])->whereNumber('post');
        });

        Route::middleware('throttle:feed-write')->group(function (): void {
            Route::post('/posts', [Feed\PostController::class, 'store']);
            Route::patch('/posts/{post}', [Feed\PostController::class, 'update'])->whereNumber('post');
            Route::delete('/posts/{post}', [Feed\PostController::class, 'destroy'])->whereNumber('post');
            Route::post('/posts/{post}/publish', [Feed\PostController::class, 'publish'])->whereNumber('post');
            Route::post('/posts/{post}/like', [Feed\PostReactionController::class, 'like'])->whereNumber('post');
            Route::delete('/posts/{post}/like', [Feed\PostReactionController::class, 'unlike'])->whereNumber('post');
            Route::post('/posts/{post}/save', [Feed\SavedPostController::class, 'save'])->whereNumber('post');
            Route::delete('/posts/{post}/save', [Feed\SavedPostController::class, 'unsave'])->whereNumber('post');
            Route::post('/posts/{post}/share', [Feed\PostSharingController::class, 'share'])->whereNumber('post');
            Route::post('/posts/{post}/hide', [Feed\PostController::class, 'hide'])->whereNumber('post');
            Route::delete('/posts/{post}/hide', [Feed\PostController::class, 'unhide'])->whereNumber('post');
            Route::post('/posts/{post}/report', [Feed\ReportController::class, 'reportPost'])->whereNumber('post');
            Route::post('/posts/{post}/comments', [Feed\PostCommentController::class, 'store'])->whereNumber('post');
            Route::post('/comments/{comment}/replies', [Feed\PostCommentController::class, 'reply'])->whereNumber('comment');
            Route::patch('/comments/{comment}', [Feed\PostCommentController::class, 'update'])->whereNumber('comment');
            Route::delete('/comments/{comment}', [Feed\PostCommentController::class, 'destroy'])->whereNumber('comment');
            Route::post('/comments/{comment}/report', [Feed\ReportController::class, 'reportComment'])->whereNumber('comment');
            Route::delete('/feed/media/{uuid}', [Feed\PostMediaController::class, 'destroy']);
        });
    });

    Route::middleware(['web', 'auth:sanctum', 'verified', 'role:moderator,super_admin'])->prefix('moderator')->group(function (): void {
        Route::get('/feed-reports', [Feed\ModeratorReportController::class, 'index']);
        Route::post('/feed-reports/{reportId}/review', [Feed\ModeratorReportController::class, 'review'])->whereNumber('reportId');
        Route::patch('/feed-reports/{reportId}', [Feed\ModeratorReportController::class, 'resolve'])->whereNumber('reportId');
        Route::get('/overview', fn () => response()->json(['status' => 'ok', 'scope' => 'moderation']));
    });
});
