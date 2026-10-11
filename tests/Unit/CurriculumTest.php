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

    /**
     * Tout le texte du programme officiel (pour vérifier les contextes).
     */
    private static function allText(): string
    {
        $text = '';

        foreach (Level::cases() as $level) {
            $text .= ' '.Curriculum::title($level);

            foreach (Curriculum::units($level) as $unit) {
                $text .= ' '.$unit->title.' '.implode(' ', $unit->objectives).' '.implode(' ', array_keys($unit->vocabulary))
                    .' '.implode(' ', $unit->vocabulary).' '.implode(' ', $unit->grammar).' '.implode(' ', $unit->functions)
                    .' '.implode(' ', $unit->pronunciation).' '.implode(' ', $unit->spoken).' '.implode(' ', $unit->pitfalls);

                foreach ($unit->situations as $situation) {
                    $text .= ' '.$situation->title.' '.$situation->description;
                }
            }
        }

        return $text;
    }

    public function test_situations_take_place_in_togo_with_ghana_as_the_only_neighbour(): void
    {
        $text = self::allText();

        foreach ([
            'Grand Marché', 'port autonome de Lomé', 'zémidjan', 'taxi', 'université de Lomé', 'université de Kara',
            'WhatsApp', 'Aflao', 'Accra', 'entretien', 'réunion', 'négoci', 'faux ami', 'accent tonique', '« th »',
        ] as $expected) {
            $this->assertStringContainsStringIgnoringCase($expected, $text, $expected);
        }

        // Le Ghana est le seul autre pays cité ; aucun autre pays ni aucune autre ville étrangère.
        foreach ([
            'Nigeria', 'Nigérian', 'Lagos', 'Abuja', 'Bénin', 'Benin', 'Cotonou', 'Burkina', 'Ouagadougou', 'Ivoire', 'Abidjan',
            'Sénégal', 'Senegal', 'Dakar', 'Cameroun', 'Cameroon', 'Kenya', 'Afrique du Sud', 'South Africa', 'Royaume-Uni',
            'États-Unis', 'tats-Unis', 'Royaume', 'Angleterre', 'England', 'Europe', 'Chine', 'China', 'France', 'Kumasi', 'okada', 'pidgin',
        ] as $forbidden) {
            $this->assertDoesNotMatchRegularExpression('/\\b'.preg_quote($forbidden, '/').'\\b/iu', $text, $forbidden);
        }

        // Au Togo, on écrit « Mixx by Yas » et « Flooz », jamais l'ancien nom.
        $this->assertStringNotContainsString('T-Money', $text);
    }

    public function test_every_situation_is_set_in_togo_or_in_ghana(): void
    {
        $places = ['Lomé', 'Kara', 'Togo', 'togolais', 'togolaise', 'Accra', 'Ghana', 'ghanéen', 'ghanéenne', 'Aflao', 'Kpalimé', 'Aného', 'Savanes', 'Plateaux', 'région Centrale', 'Grand Marché', 'CHU', 'Hédzranawoé'];
        $without = [];

        foreach (Level::cases() as $level) {
            foreach (Curriculum::units($level) as $unit) {
                foreach ($unit->situations as $situation) {
                    $text = $situation->title.' '.$situation->description;
                    $found = array_filter($places, fn ($place) => stripos($text, $place) !== false);

                    if ($found === []) {
                        $without[] = $unit->code().' '.$situation->title;
                    }
                }
            }
        }

        // Quelques situations restent volontairement neutres (entretien individuel, oral surprise…) :
        // au moins 97 % des 216 situations nomment un lieu du Togo ou d'Accra.
        $this->assertLessThanOrEqual(6, count($without), implode("\n", $without));
    }
}
