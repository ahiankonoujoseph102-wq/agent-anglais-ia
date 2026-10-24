<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Fluidité sur téléphone : aucune forme ronde animée en boucle sous 900 pixels (réservées à l'ordinateur), en-tête
 * fixe plein, sans flou recalculé à chaque image du défilement.
 */
class PhoneFluidityTest extends TestCase
{
    public function test_phones_get_no_endless_shapes_and_no_blurred_header(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $phone = substr($css, strpos($css, "@media (max-width: 899.98px) {\n    .students::before"));
        $phone = substr($phone, 0, strpos($phone, "\n}\n"));

        $this->assertStringContainsString('.students::before, .cta-band::before, .cta-band::after, .result-hero::before { animation: none; }', $phone);
        $this->assertStringContainsString('.site-header { background: var(--c-bg); backdrop-filter: none; -webkit-backdrop-filter: none; }', $phone);
    }
}
