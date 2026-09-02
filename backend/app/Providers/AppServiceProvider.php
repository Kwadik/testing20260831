<?php

namespace App\Providers;

use App\Services\Delivery\DeliveryOrchestrator;
use App\Services\Delivery\DeliveryProvider;
use App\Services\Delivery\ProviderA;
use App\Services\Delivery\ProviderB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DeliveryProvider::class,
            ProviderA::class,
        );

        $this->app->singleton(
            DeliveryOrchestrator::class,
            function ($app) {
                return new DeliveryOrchestrator(
                    providerA: $app->make(ProviderA::class),
                    providerB: $app->make(ProviderB::class),
                );
            },
        );
    }

    public function boot(): void
    {
        //
    }
}
