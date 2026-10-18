<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Clic d'un apprenant d'un pays sans paiement en ligne sur « Écrire sur WhatsApp » (page de la formule).
 */
#[Fillable(['user_id', 'plan'])]
class WhatsappPaymentClick extends Model
{
    public const UPDATED_AT = null;
}
