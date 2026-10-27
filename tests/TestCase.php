<?php

namespace Tests;

use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\LoggedExceptionCollection;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Aucun test n'appelle une vraie API : toute requête HTTP non simulée échoue.
        Http::preventStrayRequests();

        // Aucun test n'écrit dans le vrai dossier storage/app/private (captures, attestations).
        Storage::fake('local');

        // Réglages de l'administration relus à chaque test (base vidée entre deux tests).
        SiteSettings::flush();

        // Fin trop tôt (LiveEarlyEnd) : vérifiée par tests/Feature/Live/EarlyEndTest.php seulement ; les autres tests
        // enregistrent un verdict ou un bilan sans attendre les minutes d'une vraie séance.
        config(['platform.live.refuse_early_end' => false]);
    }

    /**
     * Réponses comparées avec des espaces ordinaires (voir PlainSpacesResponse).
     */
    protected function createTestResponse($response, $request)
    {
        return tap(PlainSpacesResponse::fromBaseResponse($response, $request), function ($response) {
            $response->withExceptions(
                $this->app->bound(LoggedExceptionCollection::class)
                    ? $this->app->make(LoggedExceptionCollection::class)
                    : new LoggedExceptionCollection
            );
        });
    }
}
