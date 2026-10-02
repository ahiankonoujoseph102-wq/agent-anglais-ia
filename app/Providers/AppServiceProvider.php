<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
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
        // Dates affichées en français (« 2 octobre 2026 »).
        Carbon::setLocale(config('app.locale'));

        // Signale les requêtes N+1 et les attributs inconnus hors production.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Pagination en HTML simple (classes Bootstrap 4, sans Bootstrap), stylée par public/css/app.css.
        Paginator::useBootstrapFour();
    }
}
