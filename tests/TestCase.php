<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Aucun test n'appelle une vraie API : toute requête HTTP non simulée échoue.
        Http::preventStrayRequests();
    }
}
