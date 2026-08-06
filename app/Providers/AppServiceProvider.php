<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        // Keyed by email + IP, not IP alone: an office or NAT sharing one
        // public IP would otherwise let one tenant's login/register attempts
        // exhaust the budget for everyone else behind that IP. Keying by the
        // pair means each attacked account still gets its own limit per
        // source, while a single client hammering many different emails
        // from the same IP is still bounded by the IP half of the key.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').'|'.$request->ip());
        });
    }
}
