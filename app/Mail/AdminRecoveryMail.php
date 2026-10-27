<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Lien à usage unique pour que l'administrateur retrouve l'accès (App\Support\Admin\AdminRecovery).
 */
class AdminRecoveryMail extends Mailable
{
    public function __construct(public User $admin, public string $link, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('platform.name').' : retrouver l\'accès administrateur');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-recovery');
    }
}
