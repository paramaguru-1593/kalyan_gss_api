<?php

namespace App\Providers;

use App\Services\DocumanApiService;
use App\Services\DocumanTokenService;
use App\Services\ThirdPartyApiService;
use App\Services\ThirdPartyAuthService;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ThirdPartyAuthService::class, function ($app) {
            return new ThirdPartyAuthService(
                config('thirdparty.mykalyan.token_name', 'mykalyan')
            );
        });

        $this->app->singleton(ThirdPartyApiService::class, function ($app) {
            return new ThirdPartyApiService(
                $app->make(ThirdPartyAuthService::class)
            );
        });

        $this->app->singleton(DocumanTokenService::class);
        $this->app->singleton(DocumanApiService::class, function ($app) {
            return new DocumanApiService($app->make(DocumanTokenService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::ignoreRoutes();
    }
}
