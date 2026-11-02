<?php

namespace App\Mail;

use App\Models\User;
use App\Support\LearnerName;
use App\Support\Teachers\Teachers;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * E-mail de relance d'un inscrit sans formule payée (App\Support\Reminders\FollowUps) : texte écrit par le code, un
 * bouton, et le lien « Ne plus recevoir ces e-mails ».
 *
 * @param  array{subject: string, title: string, lines: list<string>, button: string, url: string, review: ?string}  $content
 */
class FollowUpMail extends Mailable
{
    public function __construct(public User $user, public array $body, public string $stopUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->body['subject']);
    }

    /** Désinscription en un geste dans les messageries qui la proposent (Gmail…). */
    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->stopUrl.'>']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.follow-up', with: [
            'firstName' => LearnerName::first($this->user),
            'teacherName' => Teachers::forUser($this->user)->name(),
            'title' => $this->body['title'],
            'lines' => $this->body['lines'],
            'button' => $this->body['button'],
            'url' => $this->body['url'],
            'review' => $this->body['review'] ?? null,
            'reviewUrl' => route('reviews.edit'),
            'stopUrl' => $this->stopUrl,
        ]);
    }
}
