<?php

namespace Tests;

use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
    }
}
