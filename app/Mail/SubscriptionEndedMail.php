<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Support\Plan;
use App\Support\Reminders\LessonReminders;
use App\Support\Reminders\SubscriptionReport;
use App\Support\Teachers\Teacher;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Formule payée terminée sans renouvellement : bilan de l'élève (faits seulement), invitation à continuer et, s'il
 * n'en a pas encore laissé, à donner un avis honnête. Au nom de son professeur, un bouton vers la même formule.
 */
class SubscriptionEndedMail extends Mailable
{
    public function __construct(public Subscription $subscription, public Teacher $teacher, public SubscriptionReport $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre formule '.$this->subscription->plan_name.' est terminée : votre bilan');
    }

    public function content(): Content
    {
        $user = $this->subscription->user;

        return new Content(view: 'emails.subscription-ended', with: [
            'firstName' => $this->report->firstName,
            'teacherName' => $this->teacher->name(),
            'planName' => $this->subscription->plan_name,
            'endedOn' => $this->subscription->ends_at->copy()->subSecond()->translatedFormat('j F Y'),
            'lines' => $this->report->lines(),
            'encouragement' => $this->report->encouragement(),
            'review' => $this->report->reviewInvitation(),
            'reviewUrl' => route('reviews.edit'),
            'methods' => LessonReminders::paymentMethods($user),
            'url' => Plan::find((string) $this->subscription->plan) ? route('payment.create', $this->subscription->plan) : route('payment.plans'),
        ]);
    }
}
