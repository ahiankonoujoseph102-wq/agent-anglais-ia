<?php

namespace Tests\Concerns;

/**
 * Ces tests avancent l'horloge sans les signaux réguliers du navigateur (toutes les 15 secondes). Sans eux, le
 * silence serait une coupure de connexion, et le temps de séance serait mis en pause (voir
 * Tests\Feature\Live\DisconnectionPauseTest) : on le leur fait ignorer.
 */
trait IgnoresSignalSilence
{
    protected function setUpIgnoresSignalSilence(): void
    {
        config(['platform.live.disconnect_after_seconds' => 86400]);
    }
}
