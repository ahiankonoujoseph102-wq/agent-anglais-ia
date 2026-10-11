<?php

namespace Tests\Unit;

use App\Enums\Level;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Vérifie la structure du programme officiel (resources/curriculum/*.php).
 */
class CurriculumTest extends TestCase
{
    /**
     * @return array<string, array{0: Level}>
     */
    public static function levels(): array
    {
        return array_combine(
            array_map(fn (Level $level) => $level->value, Level::cases()),
            array_map(fn (Level $level) => [$level], Level::cases()),
        );
    }

    #[DataProvider('levels')]
    public function test_each_level_has_twelve_complete_units(Level $level): void
    {
        $units = Curriculum::units($level);

        $this->assertCount(Curriculum::UNITS_PER_LEVEL, $units);
        $this->assertNotSame('', Curriculum::title($level));
        $this->assertSame(range(1, 12), array_keys($units));

        $titles = [];

        foreach ($units as $unit) {
            $label = $unit->code();
            $titles[] = $unit->title;

            $this->assertInstanceOf(Unit::class, $unit);
            $this->assertNotSame('', trim($unit->title), $label);
            $this->assertGreaterThanOrEqual(2, count($unit->objectives), $label.' objectifs');
            $this->assertGreaterThanOrEqual(15, count($unit->vocabulary), $label.' vocabulaire');
            $this->assertLessThanOrEqual(25, count($unit->vocabulary), $label.' vocabulaire');
            $this->assertNotEmpty($unit->grammar, $label.' grammaire');
            $this->assertNotEmpty($unit->functions, $label.' fonctions orales');
            $this->assertNotEmpty($unit->pronunciation, $label.' prononciation');
            $this->assertNotEmpty($unit->spoken, $label.' anglais parlé');
            $this->assertNotEmpty($unit->pitfalls, $label.' difficultés des francophones');
            $this->assertCount(3, $unit->situations, $label.' situations');

            foreach ($unit->vocabulary as $english => $french) {
                $this->assertNotSame('', trim($english), $label);
                $this->assertNotSame('', trim($french), $label.' '.$english);
            }

            foreach ($unit->situations as $situation) {
                $this->assertNotSame('', trim($situation->title), $label);
                $this->assertNotSame('', trim($situation->description), $label);
                $this->assertNotEmpty($situation->tags, $label.' '.$situation->title);
                $this->assertSame([], array_diff($situation->tags, Curriculum::TAGS), $label.' thèmes inconnus');
            }
        }

        $this->assertSame($titles, array_unique($titles), 'Titres d\'unités en double');
    }

    public function test_the_programme_covers_the_requested_african_contexts(): void
    {
        $text = '';

        foreach (Level::cases() as $level) {
            foreach (Curriculum::units($level) as $unit) {
                foreach ($unit->situations as $situation) {
                    $text .= ' '.$situation->title.' '.$situation->description;
                }
                $text .= ' '.implode(' ', $unit->pitfalls).' '.implode(' ', $unit->pronunciation);
            }
        }

        foreach (['marché', 'Ghana', 'Nigeria', 'entretien', 'WhatsApp', 'université', 'réunion', 'négoci', 'th', 'faux ami', 'Faux ami', 'accent tonique'] as $expected) {
            $this->assertStringContainsStringIgnoringCase($expected, $text, $expected);
        }

        // Au Togo, on écrit « Mixx by Yas » et « Flooz », jamais l'ancien nom.
        $this->assertStringNotContainsString('T-Money', $text);
    }
}
