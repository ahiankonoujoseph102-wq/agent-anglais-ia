<?php

namespace App\Mail;

use App\Models\LearnerReminder;
use App\Models\User;
use App\Support\LearnerName;
use App\Support\Reminders\LessonReminders;
use App\Support\Teachers\Teacher;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Rappel de la séance du jour (30 minutes avant) ou relance d'une séance manquée, au nom du professeur de l'élève,
 * avec un bouton qui mène directement à la séance (connexion demandée).
 */
class LessonReminderMail extends Mailable
{
    public function __construct(public User $user, public Teacher $teacher, public string $kind, public string $time) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: LessonReminders::message($this->kind, $this->teacher, $this->time)['title']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lesson-reminder', with: [
            'firstName' => LearnerName::first($this->user),
            'teacherName' => $this->teacher->name(),
            'missed' => $this->kind === LearnerReminder::MISSED,
            'minutes' => (int) config('platform.reminders.soon_minutes'),
            'url' => route('lesson.show'),
        ]);
    }
}
