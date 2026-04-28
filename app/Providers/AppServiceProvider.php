<?php

namespace App\Providers;

use App\Services\Aspel\AspelService;
use App\Services\Generic\GenericPlatformService;
use App\Services\Hubspot\HubspotService;
use App\Services\NetSuite\NetSuiteService;
use App\Services\Odoo\OdooService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as FoundationEventServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        FoundationEventServiceProvider::disableEventDiscovery();

        $this->app->singleton('platformClassList', function () {
            return [
                'hubspot' => HubspotService::class,
                'odoo' => OdooService::class,
                'netsuite' => NetSuiteService::class,
                'generic' => GenericPlatformService::class,
            ];
        });

        $this->app->singleton('platformDriverClassList', function () {
            return [
                'aspel' => AspelService::class,
            ];
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
