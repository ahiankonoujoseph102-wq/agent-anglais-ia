<?php

namespace App\Mail;

use App\Models\CourseReview;
use App\Support\CourseReviews;
use App\Support\PrivateStorage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail du document personnel de révision : simple, aux couleurs de Talkami, le PDF en pièce jointe (aucun lien
 * public de téléchargement) et un bouton vers « Mes cours » (connexion demandée).
 */
class CourseReviewMail extends Mailable
{
    public function __construct(public CourseReview $review) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre document de révision : '.($this->review->lesson?->title ?? $this->review->content['title'] ?? config('platform.name')));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.course-review');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(PrivateStorage::name(), (string) $this->review->path)
                ->as(CourseReviews::filename($this->review))
                ->withMime('application/pdf'),
        ];
    }
}
