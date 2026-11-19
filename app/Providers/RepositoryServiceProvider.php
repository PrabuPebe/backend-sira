<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     */
    public array $bindings = [
        \App\Repositories\Contracts\FacilityRepositoryInterface::class => \App\Repositories\Eloquent\FacilityRepository::class,
        \App\Services\Contracts\FacilityServiceInterface::class => \App\Services\FacilityService::class,
        // [BINDINGS_PLACEHOLDER]
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Bindings can also be registered dynamically here
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
