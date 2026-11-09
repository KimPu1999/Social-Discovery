<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\SocialPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Single authoritative policy for user-to-user social authorization.
        Gate::policy(User::class, SocialPolicy::class);

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
        // Discovery reads: generous but bounded per account to prevent scraping.
        RateLimiter::for('discovery', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        // Discovery writes (feedback, preferences): tighter budget.
        RateLimiter::for('discovery-write', fn (Request $request) => Limit::perMinute(15)->by($request->user()?->id ?: $request->ip()));
        // Social writes (follow, requests, blocks): bounded per account.
        RateLimiter::for('social-write', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        // Feed reads (feed, search, lists): generous but bounded for scraping.
        RateLimiter::for('feed-read', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        // Feed writes (posts, comments, reactions, saves, shares, reports).
        RateLimiter::for('feed-write', fn (Request $request) => Limit::perMinute(40)->by($request->user()?->id ?: $request->ip()));
    }
}
