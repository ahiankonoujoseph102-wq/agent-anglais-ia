<?php

namespace App\Support;

use App\Models\AdminAlert;
use App\Models\CourseReview;
use App\Models\LearnerInvitation;
use App\Models\LearnerReminder;
use App\Models\LessonVideo;

/**
 * Limite d'envoi d'e-mails du jour (offre gratuite de Brevo : mail.daily_limit, 300 par jour), UN seul compteur pour
 * les documents de révision, les rappels de séance, les vidéos et les alertes de l'administrateur : courses.review.daily_emails (280) pour les
 * documents, le reste toujours gardé pour les alertes. Comptés dans la base (aucun cache) : chaque essai d'envoi du
 * jour (heure de l'application), réussi ou non.
 */
class MailQuota
{
    /** L'envoi d'e-mails est-il réglé (transport smtp, hôte, identifiant, mot de passe, expéditeur) ? */
    public static function configured(): bool
    {
        $mailer = (array) config('mail.mailers.'.config('mail.default'));

        return filled(config('mail.from.address'))
            && ($mailer['transport'] ?? null) === 'smtp'
            && filled($mailer['host'] ?? null)
            && filled($mailer['username'] ?? null)
            && filled($mailer['password'] ?? null);
    }

    public static function reviewsToday(): int
    {
        return CourseReview::query()
            ->whereIn('mail_status', ['sent', 'failed'])
            ->where('mailed_at', '>=', today())
            ->count();
    }

    /** Invitations à refaire l'évaluation (remise à zéro) parties aujourd'hui, réussies ou non. */
    public static function invitationsToday(): int
    {
        return LearnerInvitation::query()
            ->whereIn('status', [LearnerInvitation::SENT, LearnerInvitation::FAILED])
            ->where('sent_at', '>=', today())
            ->count();
    }

    public static function alertsToday(): int
    {
        return AdminAlert::query()->where('channel', 'mail')->where('created_at', '>=', today())->count();
    }

    /** Vidéos envoyées par e-mail au fondateur aujourd'hui (videos:mail). */
    public static function videosToday(): int
    {
        return LessonVideo::query()->where('mail_status', LessonVideo::MAIL_SENT)->where('mailed_at', '>=', today())->count();
    }

    /** Rappels de séance partis aujourd'hui par e-mail (learners:remind), réussis ou non. */
    public static function remindersToday(): int
    {
        return LearnerReminder::query()->whereIn('mail_status', ['sent', 'failed'])->where('created_at', '>=', today())->count();
    }

    public static function usedToday(): int
    {
        return self::reviewsToday() + self::invitationsToday() + self::alertsToday() + self::videosToday() + self::remindersToday();
    }

    /**
     * Un rappel de séance peut-il partir par e-mail aujourd'hui ? Au plus platform.reminders.daily_emails, et
     * seulement tant que platform.reminders.mail_reserve e-mails restent libres pour les documents de révision,
     * les alertes et les vidéos envoyées au fondateur. Au-delà : la notification seulement.
     */
    public static function allowsReminder(): bool
    {
        return self::remindersToday() < (int) config('platform.reminders.daily_emails')
            && self::usedToday() < self::limit() - (int) config('platform.reminders.mail_reserve');
    }

    /** Une vidéo peut-elle encore partir par e-mail aujourd'hui (10 e-mails toujours gardés pour les alertes) ? */
    public static function allowsVideo(): bool
    {
        return self::usedToday() < self::limit() - 10;
    }

    public static function limit(): int
    {
        return (int) config('mail.daily_limit');
    }

    /** Un document de révision peut-il encore partir aujourd'hui ? */
    public static function allowsReview(): bool
    {
        return self::learnerMailsToday() < (int) config('platform.courses.review.daily_emails') && self::usedToday() < self::limit();
    }

    /** Une invitation peut-elle encore partir aujourd'hui (même marge que les documents de révision) ? */
    public static function allowsInvitation(): bool
    {
        return self::allowsReview();
    }

    /** E-mails aux élèves aujourd'hui : documents de révision et invitations, dans la même marge. */
    public static function learnerMailsToday(): int
    {
        return self::reviewsToday() + self::invitationsToday();
    }

    /** Une alerte de l'administrateur peut-elle encore partir par e-mail aujourd'hui ? */
    public static function allowsAlert(): bool
    {
        return self::usedToday() < self::limit();
    }
}
