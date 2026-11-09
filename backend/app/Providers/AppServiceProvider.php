<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip() . '|' . $r->input('login', $r->input('mobile'))));
        RateLimiter::for('otp', fn (Request $r) => [Limit::perHour(5)->by('otp|' . $r->input('mobile')), Limit::perMinute(3)->by($r->ip())]);
        // GPS: driver pushes every 10–15 s; allow headroom for retries.
        RateLimiter::for('gps', fn (Request $r) => Limit::perMinute(20)->by('gps|' . $r->user()?->id));
    }
}
