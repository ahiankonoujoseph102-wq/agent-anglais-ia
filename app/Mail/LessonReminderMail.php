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
 * Rappel de la séance du jour (30 minutes avant), relance d'une séance manquée ou fin d'abonnement (la veille du
 * dernier jour), au nom du professeur de l'élève, avec un bouton vers la séance ou vers le paiement de la formule.
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
        $renew = $this->kind === LearnerReminder::RENEW;
        $subscription = $this->user->activeSubscription;

        return new Content(view: 'emails.lesson-reminder', with: [
            'firstName' => LearnerName::first($this->user),
            'teacherName' => $this->teacher->name(),
            'missed' => $this->kind === LearnerReminder::MISSED,
            'renew' => $renew,
            'planName' => $subscription?->plan_name,
            'endsAt' => $subscription?->ends_at,
            'methods' => $renew ? LessonReminders::paymentMethods($this->user) : null,
            'minutes' => (int) config('platform.reminders.soon_minutes'),
            'url' => $renew ? LessonReminders::renewUrl($this->user) : route('lesson.show'),
        ]);
    }
}
