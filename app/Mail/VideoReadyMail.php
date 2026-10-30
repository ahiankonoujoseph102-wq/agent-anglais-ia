<?php

namespace App\Mail;

use App\Models\LessonVideo;
use App\Support\PrivateStorage;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail au fondateur d'une vidéo de promotion ou du Club (App\Support\Videos\VideoMailer) : description, texte à
 * publier, version légère en pièce jointe et lien vers la pleine qualité tant qu'elle est gardée.
 */
class VideoReadyMail extends Mailable
{
    /** Chemin de la version légère jointe, relevé à la création (la vidéo est effacée juste après l'envoi). */
    public ?string $attachmentPath;

    public function __construct(public LessonVideo $video, public bool $withAttachment, public ?string $link, public ?CarbonInterface $keepUntil)
    {
        $this->attachmentPath = $withAttachment ? $video->mail_path : null;
    }

    public function envelope(): Envelope
    {
        $date = $this->video->day->locale('fr')->translatedFormat('j F Y');

        return new Envelope(subject: $this->video->isClub()
            ? 'Vidéo du Club du '.$date.' : '.($this->video->themeLabel() ?? $this->video->title)
            : 'Vidéo de promotion du '.$date.' : '.$this->video->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.video-ready');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->attachmentPath === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(PrivateStorage::name(), $this->attachmentPath)
                ->as($this->video->downloadName())
                ->withMime('video/mp4'),
        ];
    }
}
