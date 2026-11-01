<?php

namespace App\Models;

use App\Enums\Level;
use App\Enums\ReviewStatus;
use App\Support\Countries;
use App\Support\LearnerName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Avis d'un élève (App\Support\Reviews\Reviews) : un par compte, note de 1 à 5 et texte court.
 * Le prénom et l'initiale du nom seulement sont montrés, jamais le numéro.
 */
#[Fillable(['user_id', 'rating', 'body', 'kind', 'level', 'country'])]
class Review extends Model
{
    /** Ce que l'avis montre de l'élève (figé à l'envoi). */
    public const KINDS = [
        'subscriber' => 'Abonné',
        'ambassador' => 'Ambassadeur',
        'granted' => 'Accès offert',
        'free' => 'Évaluation gratuite',
    ];

    /** Raisons de l'attente, avec leur libellé pour l'administration. */
    public const FLAGS = [
        'low_rating' => 'Note très basse',
        'link' => 'Lien ou adresse d\'un site',
        'phone' => 'Numéro de téléphone',
        'email' => 'Adresse e-mail',
        'words' => 'Mot grossier ou insultant',
        'brand' => 'Nom de marque',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'level' => Level::class,
            'flags' => 'array',
            'rating' => 'integer',
            'replied_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ReviewStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === ReviewStatus::Published;
    }

    public function isPending(): bool
    {
        return $this->status === ReviewStatus::Pending;
    }

    /** « Kofi M. » : prénom et initiale du dernier nom, jamais le nom complet ni le numéro. */
    public function authorName(): string
    {
        $user = $this->user;
        $first = $user === null ? null : LearnerName::first($user);
        $words = $user === null ? [] : (preg_split('/\s+/u', trim((string) $user->name)) ?: []);
        $last = count($words) > 1 ? (string) end($words) : '';
        $initial = $last !== '' ? Str::upper(Str::substr($last, 0, 1)).'.' : '';

        return $first === null ? 'Un élève' : trim($first.' '.$initial);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS['free'];
    }

    public function countryName(): ?string
    {
        return $this->country === null || $this->country === 'ZZ' ? null : Countries::name($this->country);
    }

    /**
     * @return list<string>
     */
    public function flagLabels(): array
    {
        return array_values(array_map(fn (string $flag) => self::FLAGS[$flag] ?? $flag, (array) $this->flags));
    }
}
