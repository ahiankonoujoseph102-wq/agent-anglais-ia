<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Guides gratuits (partie « Guides », /guides) : métadonnées dans resources/content/guides.php (ordre d'affichage),
 * texte de chaque guide dans resources/views/guides/articles/{slug}.blade.php. Chaque guide mène à l'évaluation
 * gratuite. Règles de texte : celles des pages publiques (aucun chiffre inventé, aucune promesse, anglais américain,
 * aucun autre pays que ceux du paiement, aucune mention de l'intelligence artificielle).
 */
final readonly class Guides
{
    /**
     * @param  list<string>  $related
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $heading,
        public string $lead,
        public string $icon,
        public Carbon $published,
        public array $related,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $guides = [];

        foreach (self::content() as $slug => $guide) {
            $guides[$slug] = self::make($slug, $guide);
        }

        return $guides;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::content());
    }

    public static function find(string $slug): ?self
    {
        $guide = self::content()[$slug] ?? null;

        return $guide === null ? null : self::make($slug, $guide);
    }

    /**
     * Guides liés (fin de chaque guide), dans l'ordre du fichier.
     *
     * @return list<self>
     */
    public function relatedGuides(): array
    {
        return array_values(array_filter(array_map(fn (string $slug) => self::find($slug), $this->related)));
    }

    public function view(): string
    {
        return 'guides.articles.'.$this->slug;
    }

    /**
     * @param  array<string, mixed>  $guide
     */
    private static function make(string $slug, array $guide): self
    {
        $fill = fn (string $text) => strtr($text, [
            ':platform' => (string) config('platform.name'),
            ':teacher' => (string) config('avatar.model.name'),
        ]);

        return new self(
            $slug,
            $fill($guide['title']),
            $fill($guide['description']),
            $fill($guide['heading']),
            $fill($guide['lead']),
            $guide['icon'],
            Carbon::parse($guide['published'], config('app.timezone')),
            $guide['related'] ?? [],
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function content(): array
    {
        static $content = null;

        return $content ??= require resource_path('content/guides.php');
    }
}
