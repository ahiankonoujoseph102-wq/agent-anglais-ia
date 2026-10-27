<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Une alerte envoyée à l'administrateur : son canal (notification ou e-mail), son résultat et, en cas d'échec,
 * une raison courte. Jamais de clé, de capture ni de numéro de transaction.
 */
#[Fillable(['kind', 'channel', 'status', 'title', 'reason', 'payment_id'])]
class AdminAlert extends Model
{
    public const UPDATED_AT = null;

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** Types d'alerte, avec leur libellé pour la page « Alertes ». */
    public const KINDS = [
        'payment_captured' => 'Capture de paiement envoyée',
        'payment_expiring' => 'Accès provisoire bientôt terminé',
        'whatsapp_click' => 'Paiement demandé sur WhatsApp',
        'cost_high' => 'Coût élevé d\'un abonné',
        'test' => 'Alerte d\'essai',
    ];

    public const CHANNELS = ['push' => 'Notification', 'mail' => 'E-mail'];
}
