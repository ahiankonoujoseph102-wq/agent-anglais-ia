<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un e-mail parti vers un élève (relance, rappel, bilan, document…), en attente du résumé au fondateur. */
#[Fillable(['user_id', 'recipient', 'kind', 'subject', 'reported_at'])]
class MailJournal extends Model
{
    protected $table = 'mail_journal';

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
