<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        // When the site is configured as https (APP_URL=https://…), every
        // generated link is https regardless of what the proxy/host reports —
        // a login page or manifest link that downgrades to http is a
        // mixed-content failure waiting to happen.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Named on purpose. Two plain `throttle:N,M` middleware on one route share a
        // single counter per IP, so every write counted twice and ate into the read
        // budget — "10 writes a minute" was really about five. Each limiter now
        // has its own key: 60 requests of any kind, and 10 writes, per IP per
        // minute (routes/api.php). Behind venue wifi many attendees share one IP,
        // so if writes get refused at a busy event, this is the number to raise.
        RateLimiter::for('api-general', fn (Request $request) => Limit::perMinute(60)->by('api-general:'.$request->ip()));
        RateLimiter::for('api-writes', fn (Request $request) => Limit::perMinute(10)->by('api-writes:'.$request->ip()));
    }
}
