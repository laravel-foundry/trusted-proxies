<?php

declare(strict_types=1);

/*
 * This file is part of the Laravel trusted proxies package.
 *
 * (ɔ) Frugan <dev@frugan.it>
 *
 * This source file is subject to the GNU GPLv3 license that is bundled
 * with this source code in the file COPYING.
 */

namespace LaravelFoundry\TrustedProxies\Provider;

use Illuminate\Support\ServiceProvider;
use LaravelFoundry\TrustedProxies\Service\TrustedProxyService;
use Monicahq\Cloudflare\TrustedProxyServiceProvider;

class TrustedProxiesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Explicitly register the monicahq/laravel-cloudflare service provider to ensure
        // its Artisan commands (e.g. cloudflare:reload) are available in environments where
        // transitive dependencies are not auto-discovered, such as Sage themes running on Acorn.
        // In standard Laravel, app->register() is idempotent and will not re-register it.
        $this->app->register(TrustedProxyServiceProvider::class);

        $this->mergeConfigFrom(
            __DIR__.'/../../config/trustedproxies.php',
            'trustedproxies'
        );

        $this->app->singleton(TrustedProxyService::class, fn ($app) => new TrustedProxyService($app['config']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/trustedproxies.php' => \config_path('trustedproxies.php'),
        ], 'trustedproxies-config');

        // Configure trusted proxies early in the request lifecycle
        $this->app->make(TrustedProxyService::class)->configure();
    }
}
