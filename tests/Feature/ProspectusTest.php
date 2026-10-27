<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Prospectus papier (tools/prospectus) : mêmes règles de texte que les pages publiques, valeurs lues dans la configuration.
 */
class ProspectusTest extends TestCase
{
    private function template(): string
    {
        return file_get_contents(base_path('tools/prospectus/prospectus.html'));
    }

    public function test_the_flyer_never_mentions_artificial_intelligence_providers_or_tiktok(): void
    {
        $text = mb_strtolower(strip_tags(preg_replace('#<(style|script)\b.*?</\1>#s', '', $this->template())));

        foreach (['intelligence artificielle', ' ia ', 'gemini', 'openai', 'chatgpt', 'google', 'tiktok', 'garanti', '100 %', 'sans engagement', 'carte bancaire', 'certificat', 'diplôme'] as $word) {
            $this->assertStringNotContainsString($word, $text, $word);
        }
    }

    public function test_prices_names_and_durations_come_from_the_configuration(): void
    {
        $template = $this->template();

        foreach (['{{price}}', '{{period}}', '{{minutes}}', '{{teacher_nb}}', '{{platform}}', '{{phone}}', '{{phone2}}', '{{email}}', '{{address}}', '{{site}}', '{{qr}}'] as $marker) {
            $this->assertStringContainsString($marker, $template);
        }
        $this->assertDoesNotMatchRegularExpression('/\d[\d  ]*\s*FCFA/u', $template);
        $this->assertStringNotContainsString('Teacher Joe', $template);
        $this->assertStringNotContainsString('Talkami', $template);
    }

    public function test_the_qr_code_counts_flyers_in_the_origin_page(): void
    {
        $build = file_get_contents(base_path('tools/prospectus/build.mjs'));

        $this->assertStringContainsString('?utm_source=prospectus', $build);
        $this->assertStringContainsString('&utm_campaign=', $build);
        $this->assertFileExists(base_path('tools/prospectus/logo-noir.png'));
    }
}
