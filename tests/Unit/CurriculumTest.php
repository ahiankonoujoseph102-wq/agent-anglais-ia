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

    public function test_situations_take_place_in_togo_or_abroad_without_naming_any_foreign_country(): void
    {
        $text = self::allText();

        foreach ([
            'Grand Marché', 'port autonome de Lomé', 'zémidjan', 'taxi', 'université de Lomé', 'université de Kara',
            'WhatsApp', 'entretien', 'réunion', 'négoci', 'faux ami', 'accent tonique', '« th »',
            'étranger', 'international', 'visioconférence', 'aéroport',
        ] as $expected) {
            $this->assertStringContainsStringIgnoringCase($expected, $text, $expected);
        }

        // Aucun pays étranger ni aucune ville étrangère : les situations hors du Togo restent
        // « à l'étranger », « internationales » ou « en ligne ». Le Ghana n'est plus cité non plus.
        foreach ([
            'Ghana', 'Ghanéen', 'Ghanéenne', 'Ghanaian', 'Accra', 'Aflao', 'Makola', 'cédi', 'cédis', 'cedi', 'cedis',
            'chale', 'trotro', 'Osu', 'Kumasi',
            'Nigeria', 'Nigérian', 'Lagos', 'Abuja', 'Bénin', 'Benin', 'Cotonou', 'Burkina', 'Ouagadougou', 'Ivoire', 'Abidjan',
            'Sénégal', 'Senegal', 'Dakar', 'Cameroun', 'Cameroon', 'Kenya', 'Afrique du Sud', 'South Africa', 'Royaume-Uni',
            'États-Unis', 'tats-Unis', 'Royaume', 'Angleterre', 'England', 'Europe', 'Chine', 'China', 'France',
            'Londres', 'London', 'New York', 'Washington', 'Canada', 'Australie', 'Australia', 'Amérique', 'America',
            'okada', 'pidgin',
        ] as $forbidden) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\p{L}])'.preg_quote($forbidden, '/').'(?![\p{L}])/iu', $text, $forbidden);
        }

        // Au Togo, on écrit « Mixx by Yas » et « Flooz », jamais l'ancien nom.
        $this->assertStringNotContainsString('T-Money', $text);
    }

    public function test_every_situation_is_set_in_togo_or_in_an_international_setting(): void
    {
        $places = [
            // Lieux du Togo
            'Lomé', 'Kara', 'Togo', 'togolais', 'togolaise', 'Kpalimé', 'Aného', 'Savanes', 'Plateaux', 'région Centrale', 'Grand Marché', 'CHU', 'Hédzranawoé',
            // Cadre international (sans nommer de pays)
            'étranger', 'étrangère', 'international', 'internationale', 'anglophone', 'en ligne', 'visioconférence', 'aéroport',
        ];
        $without = [];

        foreach (Level::cases() as $level) {
            foreach (Curriculum::units($level) as $unit) {
                foreach ($unit->situations as $situation) {
                    $text = $situation->title.' '.$situation->description;
                    $found = array_filter($places, fn ($place) => mb_stripos($text, $place) !== false);

                    if ($found === []) {
                        $without[] = $unit->code().' '.$situation->title;
                    }
                }
            }
        }

        // Quelques situations restent volontairement neutres (entretien individuel, oral surprise…) :
        // au plus 6 des 216 situations ne nomment ni un lieu du Togo ni un cadre international.
        $this->assertLessThanOrEqual(6, count($without), implode("\n", $without));
    }

    /**
     * Chaque élément du programme qui contient de l'anglais : le texte complet (pour savoir
     * s'il mentionne l'anglais britannique) et la seule partie anglaise (mots du vocabulaire,
     * passages entre guillemets).
     *
     * @return list<array{label: string, full: string, english: string}>
     */
    private static function englishEntries(): array
    {
        $entries = [];

        foreach (Level::cases() as $level) {
            foreach (Curriculum::units($level) as $unit) {
                foreach ($unit->vocabulary as $english => $french) {
                    $entries[] = [
                        'label' => $unit->code().' vocabulaire « '.$english.' »',
                        'full' => $english.' => '.$french,
                        'english' => $english.' '.implode(' ', self::quoted($french)),
                    ];
                }

                foreach ([$unit->grammar, $unit->functions, $unit->pronunciation, $unit->spoken, $unit->pitfalls] as $lines) {
                    foreach ($lines as $line) {
                        $entries[] = [
                            'label' => $unit->code().' « '.mb_substr($line, 0, 60).' »',
                            'full' => $line,
                            'english' => implode(' ', self::quoted($line)),
                        ];
                    }
                }
            }
        }

        return $entries;
    }

    /**
     * Passages entre guillemets français (« … »).
     *
     * @return list<string>
     */
    private static function quoted(string $text): array
    {
        preg_match_all('/«\s*(.*?)\s*»/u', $text, $matches);

        return $matches[1];
    }

    public function test_the_english_taught_is_american_english(): void
    {
        $text = self::allText();

        // Orthographe britannique : jamais, nulle part (aucun de ces mots n'existe en français).
        foreach ([
            'colour', 'colours', 'favourite', 'neighbour', 'neighbours', 'neighbourhood', 'realise', 'realised', 'organised',
            'organising', 'travelling', 'travelled', 'cancelled', 'cancelling', 'apologise', 'apologised', 'recognise',
            'recognised', 'criticise', 'summarise', 'emphasise', 'theatre', 'diarrhoea', 'enrol', 'grey', 'tyre', 'offence',
            'per cent', 'instalments', 'spilt', 'learnt',
        ] as $british) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\p{L}])'.preg_quote($british, '/').'(?![\p{L}])/iu', $text, $british);
        }

        $entries = self::englishEntries();

        // Mots qui existent aussi en français (« centre », « programme »…) : vérifiés dans la partie anglaise seulement.
        foreach (['centre', 'programme', 'organise', 'licence', 'metre', 'litre'] as $british) {
            foreach ($entries as $entry) {
                $this->assertDoesNotMatchRegularExpression('/(?<![\p{L}])'.$british.'(?![\p{L}])/iu', $entry['english'], $entry['label']);
            }
        }

        // Vocabulaire britannique : seulement cité comme équivalent, avec la mention « britannique ».
        foreach ([
            'Mum', 'lift', 'petrol', 'rubbish', 'holidays?', 'queue', 'lorry', 'trousers', 'booking', 'mobile phone',
            '(?<!fall )flat(?! tire)', 'timetable', 'full stop', 'have you got',
        ] as $british) {
            foreach ($entries as $entry) {
                if (preg_match('/(?<![\p{L}])'.$british.'(?![\p{L}])/iu', $entry['english']) === 1) {
                    $this->assertStringContainsString('britannique', $entry['full'], $entry['label']);
                }
            }
        }

        // Quelques formes américaines enseignées.
        foreach (['apartment', 'vacation', 'cell phone', 'line', 'trash', 'Mom', 'soccer', '[ˈwɑːɾɚ]'] as $american) {
            $this->assertStringContainsString($american, $text, $american);
        }
    }
}
