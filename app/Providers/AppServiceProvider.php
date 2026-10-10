<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One pricing service per request, so the active rules are read once.
        $this->app->scoped(\App\Services\PricingService::class);

        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The customer portal wears the public website's header and footer,
        // which render from the same RJ_DATA the storefront pages use.
        \Illuminate\Support\Facades\View::composer('components.layouts.portal', function ($view) {
            $view->with('rj', app(\App\Services\StorefrontCatalog::class)->payload());
        });
    }
}
