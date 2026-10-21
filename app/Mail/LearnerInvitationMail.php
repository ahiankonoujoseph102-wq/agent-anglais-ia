<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invitation à refaire l'évaluation orale gratuite, après une remise à zéro des apprenants (Teacher Joe amélioré).
 */
class LearnerInvitationMail extends Mailable
{
    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('avatar.model.name').' a été amélioré : refaites votre évaluation gratuite');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.learner-invitation');
    }
}
