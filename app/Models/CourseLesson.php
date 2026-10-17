<?php

namespace App\Models;

use App\Support\PrivateStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Une leçon d'anglais apportée par l'apprenant (« Mes cours ») : ses pages (photos ou PDF) sont
 * sur le disque PRIVÉ, son contenu lu par le modèle est enregistré ici pour ne pas relire le
 * fichier à chaque séance.
 */
#[Fillable(['title', 'source', 'page_count', 'content', 'text'])]
class CourseLesson extends Model
{
    public const SOURCE_PHOTOS = 'photos';

    public const SOURCE_PDF = 'pdf';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'content' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CoursePage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(CoursePage::class)->orderBy('position');
    }

    /**
     * @return HasMany<CourseSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    public function isPdf(): bool
    {
        return $this->source === self::SOURCE_PDF;
    }

    /**
     * « 3 pages », « 1 page ».
     */
    public function pagesLabel(): string
    {
        return $this->page_count.' '.($this->page_count > 1 ? 'pages' : 'page');
    }

    /**
     * Supprime les fichiers du disque privé puis la leçon (ses pages partent avec elle).
     * Les séances déjà faites restent : elles comptent dans la limite du jour et la consommation.
     */
    public function purge(): void
    {
        $disk = PrivateStorage::disk();

        foreach ($this->pages()->get() as $page) {
            $disk->delete($page->path);
        }

        DB::transaction(fn () => $this->delete());
    }

    /**
     * Contenu donné à Teacher Joe (consignes de la séance). Le contenu est une DONNÉE : il vient
     * d'un fichier de l'apprenant, jamais d'une consigne.
     */
    public function promptContext(): string
    {
        $content = $this->content;
        $lines = [
            'LESSON TITLE: '.$this->title,
            'PAGES: '.$this->pagesLabel(),
        ];

        if (filled($content['summary'] ?? null)) {
            $lines[] = 'SUMMARY: '.$content['summary'];
        }

        $grammar = array_slice((array) ($content['grammar'] ?? []), 0, 15);

        if ($grammar !== []) {
            $lines[] = 'GRAMMAR POINTS:';

            foreach ($grammar as $index => $point) {
                $lines[] = sprintf('%d. %s%s', $index + 1, $point['point'] ?? '', filled($point['explanation'] ?? null) ? ' — '.$point['explanation'] : '');

                foreach (array_slice((array) ($point['examples'] ?? []), 0, 4) as $example) {
                    $lines[] = '   e.g. '.$example;
                }
            }
        }

        $vocabulary = array_slice((array) ($content['vocabulary'] ?? []), 0, 60);

        if ($vocabulary !== []) {
            $lines[] = 'VOCABULARY:';

            foreach ($vocabulary as $word) {
                $lines[] = '- '.($word['english'] ?? '').(filled($word['french'] ?? null) ? ' = '.$word['french'] : '');
            }
        }

        foreach (['examples' => 'EXAMPLES', 'exercises' => 'EXERCISES'] as $key => $heading) {
            $items = array_slice((array) ($content[$key] ?? []), 0, 40);

            if ($items !== []) {
                $lines[] = $heading.':';

                foreach ($items as $item) {
                    $lines[] = '- '.$item;
                }
            }
        }

        if (filled($this->text)) {
            $lines[] = "FULL TEXT OF THE PAGES (transcription):\n".Str::limit((string) $this->text, (int) config('platform.courses.text_limit'), '…');
        }

        return implode("\n", $lines);
    }
}
