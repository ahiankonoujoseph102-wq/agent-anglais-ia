<?php

namespace App\Providers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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

        // Production : liens en https:// et mode débogage toujours coupé (jamais de trace d'erreur affichée).
        if (config('app.force_https')) {
            URL::forceHttps();
        }

        if ($this->app->isProduction()) {
            config(['app.debug' => false]);
        }

        // Signale les requêtes N+1 et les attributs inconnus hors production.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Pagination en HTML simple (classes Bootstrap 4, sans Bootstrap), stylée par public/css/app.css.
        Paginator::useBootstrapFour();

        // Compteur de paiements en attente : en-tête et onglets de l'administration.
        View::composer(['components.layout', 'admin.nav'], function ($view) {
            $view->with('pendingPaymentsCount', auth()->user()?->isAdmin()
                ? once(fn () => Payment::query()->where('status', PaymentStatus::Pending)->count())
                : 0);
        });
    }
}
