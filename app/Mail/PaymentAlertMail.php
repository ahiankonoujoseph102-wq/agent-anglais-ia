<?php

namespace App\Mail;

use App\Support\Alerts\AlertMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail d'alerte pour l'administrateur : simple, aux couleurs de la marque, lisible sur téléphone.
 */
class PaymentAlertMail extends Mailable
{
    public function __construct(public AlertMessage $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alert->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-alert');
    }
}
