<?php

namespace App\Support;

use App\Models\AdminAlert;
use App\Models\CourseReview;

/**
 * Limite d'envoi d'e-mails du jour (offre gratuite de Brevo : mail.daily_limit, 300 par jour), UN seul compteur pour
 * les documents de révision des élèves et les alertes de l'administrateur : courses.review.daily_emails (280) pour les
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

    public static function alertsToday(): int
    {
        return AdminAlert::query()->where('channel', 'mail')->where('created_at', '>=', today())->count();
    }

    public static function usedToday(): int
    {
        return self::reviewsToday() + self::alertsToday();
    }

    public static function limit(): int
    {
        return (int) config('mail.daily_limit');
    }

    /** Un document de révision peut-il encore partir aujourd'hui ? */
    public static function allowsReview(): bool
    {
        return self::reviewsToday() < (int) config('platform.courses.review.daily_emails') && self::usedToday() < self::limit();
    }

    /** Une alerte de l'administrateur peut-elle encore partir par e-mail aujourd'hui ? */
    public static function allowsAlert(): bool
    {
        return self::usedToday() < self::limit();
    }
}
