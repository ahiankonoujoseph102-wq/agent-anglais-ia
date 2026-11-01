<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Minutes offertes, ajoutées au temps du jour d'UN jour (App\Support\Reviews\TimeBonuses) : le jour de l'envoi
 * pour un élève avec un accès en cours, sinon le premier jour où il ouvre une séance avec un accès.
 */
#[Fillable(['user_id', 'reason', 'seconds', 'applied_on'])]
class TimeBonus extends Model
{
    public const REVIEW = 'review';

    protected function casts(): array
    {
        return ['applied_on' => 'date', 'seconds' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
