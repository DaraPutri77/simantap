<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        Password::defaults(
            fn (): Password => Password::min(
                (int) config('simantap.security.password_min_length', 12)
            )
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
        );

        if ((bool) config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}