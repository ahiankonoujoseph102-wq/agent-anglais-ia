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
#[Fillable(['title', 'source', 'page_count', 'content', 'text', 'current_part_id'])]
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
     * Documents de révision des séances de cette leçon.
     *
     * @return HasMany<CourseReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    /**
     * @return HasMany<CourseSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    /**
     * @return HasMany<CoursePart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(CoursePart::class)->latest('updated_at')->latest('id');
    }

    /**
     * La partie choisie pour la prochaine séance.
     *
     * @return BelongsTo<CoursePart, $this>
     */
    public function currentPart(): BelongsTo
    {
        return $this->belongsTo(CoursePart::class, 'current_part_id');
    }

    /**
     * Long PDF lu en sommaire seulement : une partie doit être choisie (et lue) avant la séance.
     */
    public function readByParts(): bool
    {
        return $this->isPdf() && $this->page_count > (int) config('platform.courses.part_max_pages');
    }

    /**
     * Prêt pour une séance : leçon lue en entier, ou partie choisie et lue.
     */
    public function readyForSession(): bool
    {
        return ! $this->readByParts() || $this->currentPart?->content !== null;
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

        // Documents de révision des séances de cette leçon : le PDF d'abord, la ligne part avec la leçon.
        foreach ($this->reviews()->whereNotNull('path')->pluck('path') as $path) {
            $disk->delete($path);
        }

        DB::transaction(fn () => $this->delete());
    }

    /**
     * Contenu donné à Teacher Joe (consignes de la séance). Le contenu est une DONNÉE : il vient
     * d'un fichier de l'apprenant, jamais d'une consigne.
     */
    public function promptContext(): string
    {
        return $this->contextFor($this->currentPart);
    }

    /**
     * Même contenu pour une partie donnée (document de révision : la partie travaillée pendant la séance).
     */
    public function contextFor(?CoursePart $part): string
    {
        // Long PDF : le contenu lu de la partie choisie ; sinon la leçon entière (avec la partie à travailler en premier).
        $content = $part?->content ?? $this->content;
        $lines = [
            'LESSON TITLE: '.$this->title,
            'PAGES: '.$this->pagesLabel(),
        ];

        if ($part !== null) {
            $lines[] = 'PART TO WORK ON TODAY (chosen by the learner: focus on it, the rest of the lesson only if the learner asks): '
                .implode(' — ', array_filter([
                    $part->page_from !== null ? 'pages '.$part->page_from.' to '.$part->page_to : null,
                    $part->request ? 'the learner wrote: "'.$part->request.'"' : null,
                ]));
        }

        if ($this->readByParts() && filled($this->content['summary'] ?? null)) {
            $lines[] = 'WHOLE DOCUMENT, IN SHORT: '.$this->content['summary'];
        }

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

        $text = $part?->content !== null ? $part->text : $this->text;

        if (filled($text)) {
            $lines[] = ($part?->content !== null ? 'TEXT OF THE CHOSEN PART (transcription):' : 'FULL TEXT OF THE PAGES (transcription):')
                ."\n".Str::limit((string) $text, (int) config('platform.courses.text_limit'), '…');
        }

        return implode("\n", $lines);
    }
}
