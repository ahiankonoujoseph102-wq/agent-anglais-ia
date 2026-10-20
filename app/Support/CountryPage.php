<?php

namespace App\Support;

use App\Models\Assessment;

/**
 * Page « Apprendre l'anglais » d'un pays de paiement (resources/content/pays.php) : textes propres au
 * pays, marqueurs remplis depuis la configuration (nom, professeur, moyens de paiement, durées).
 */
final readonly class CountryPage
{
    /**
     * @param  list<array{icon: string, title: string, text: string}>  $sections
     * @param  list<array{question: string, answer: string}>  $faq
     */
    public function __construct(
        public string $slug,
        public string $code,
        public string $name,
        public string $title,
        public string $description,
        public string $heading,
        public string $lead,
        public string $photo,
        public array $sections,
        public array $faq,
        public ?PaymentCountry $country,
    ) {}

    /**
     * Adresses (slugs) des pages, dans l'ordre du fichier.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::content());
    }

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $pages = [];

        foreach (self::slugs() as $slug) {
            $pages[$slug] = self::find($slug);
        }

        return $pages;
    }

    public static function find(string $slug): ?self
    {
        $page = self::content()[$slug] ?? null;

        if ($page === null) {
            return null;
        }

        $country = PaymentCountry::findAvailable($page['code']);
        $all = PaymentCountry::find($page['code']);
        $markers = [
            ':platform' => (string) config('platform.name'),
            ':teacher' => (string) config('avatar.model.name'),
            ':minutes' => (string) config('platform.assessment.max_minutes'),
            ':total' => (string) Assessment::totalMinutes(),
            ':operators' => self::operators($country),
            ':daily' => self::dailyMinutes(),
            ':course_photos' => (string) CourseFiles::photos(),
            ':course_pages' => (string) CourseFiles::pdfPages(),
            ':dial' => (string) ($all?->dialCode ?? ''),
        ];
        $fill = fn (string $text) => strtr($text, $markers);

        return new self(
            $slug,
            $page['code'],
            (string) ($all?->name ?? $page['code']),
            $fill($page['title']),
            $fill($page['description']),
            $fill($page['heading']),
            $fill($page['lead']),
            $page['photo'],
            array_map(fn (array $section) => ['icon' => $section['icon'], 'title' => $fill($section['title']), 'text' => $fill($section['text'])], $page['sections']),
            array_map(fn (array $item) => ['question' => $fill($item['question']), 'answer' => $fill($item['answer'])], $page['faq']),
            $country,
        );
    }

    /**
     * « Mixx by Yas ou Flooz », « MTN, Moov ou Celtiis » ; « Mobile Money » si aucun numéro n'est encore saisi.
     */
    private static function operators(?PaymentCountry $country): string
    {
        $names = array_values(array_map(fn (PaymentOperator $operator) => $operator->name, $country?->operators ?? []));

        return match (count($names)) {
            0 => 'Mobile Money',
            1 => $names[0],
            default => implode(', ', array_slice($names, 0, -1)).' ou '.end($names),
        };
    }

    /**
     * « 15 minutes avec Semaine et Essentiel, 30 minutes avec Intensif » (formules de la configuration).
     */
    private static function dailyMinutes(): string
    {
        $groups = [];

        foreach (Plan::all() as $plan) {
            $groups[$plan->dailyMinutes][] = $plan->name;
        }

        ksort($groups);
        $parts = [];

        foreach ($groups as $minutes => $names) {
            $last = array_pop($names);
            $parts[] = $minutes.' minutes avec '.($names === [] ? $last : implode(', ', $names).' et '.$last);
        }

        return implode(', ', $parts);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function content(): array
    {
        static $content = null;

        return $content ??= require resource_path('content/pays.php');
    }
}
