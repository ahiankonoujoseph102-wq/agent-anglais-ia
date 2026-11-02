<?php

namespace App\Support\Alerts;

use App\Models\AdminAlert;
use App\Models\MailJournal;
use App\Support\Duration;
use Illuminate\Support\Collection;

/**
 * Résumé, au fondateur (ADMIN_ALERT_EMAIL), des e-mails partis vers les élèves depuis le dernier résumé : UN e-mail
 * par heure au plus (mail:digest), et seulement s'il y a eu des envois, pour ne pas doubler la limite d'e-mails du
 * jour. Ce qui n'a pas pu partir (limite atteinte, envoi non réglé) attend le résumé suivant.
 */
final class MailDigest
{
    /** Lignes de détail au plus dans un résumé (le reste est compté). */
    public const MAX_LINES = 60;

    /** Le journal garde ce nombre de jours. */
    public const KEEP_DAYS = 30;

    /** @return int e-mails résumés (0 : rien à dire, ou résumé non envoyé) */
    public static function send(): int
    {
        MailJournal::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

        $alerts = app(AdminAlerts::class);
        $rows = MailJournal::query()->whereNull('reported_at')->with('user')->orderBy('id')->limit(500)->get();

        if ($rows->isEmpty() || ! $alerts->mailConfigured()) {
            return 0;
        }

        $result = $alerts->send(self::message($rows), ['mail']);

        if (($result['mail']['status'] ?? null) !== AdminAlert::SENT) {
            return 0;
        }

        MailJournal::query()->whereKey($rows->modelKeys())->update(['reported_at' => now()]);

        return $rows->count();
    }

    /**
     * @param  Collection<int, MailJournal>  $rows
     */
    public static function message($rows): AlertMessage
    {
        $count = $rows->count();
        $byKind = $rows->countBy(fn (MailJournal $row) => strtok($row->kind, ':') ?: $row->kind)->sortDesc();
        $summary = $byKind->map(fn (int $n, string $kind) => trim($kind).' : '.$n)->implode(' · ');

        $details = $byKind->map(fn (int $n, string $kind) => [trim($kind), $n.' '.($n === 1 ? 'e-mail' : 'e-mails')])->values()->all();

        foreach ($rows->take(self::MAX_LINES) as $row) {
            $details[] = [
                $row->user?->name ?? $row->recipient,
                $row->kind.' · « '.$row->subject.' » · '.Duration::at($row->created_at, false),
            ];
        }

        if ($count > self::MAX_LINES) {
            $details[] = ['Et encore', ($count - self::MAX_LINES).' e-mails'];
        }

        return new AlertMessage(
            kind: 'mail_digest',
            title: 'E-mails envoyés aux élèves : '.$count,
            body: $summary,
            url: route('admin.follow-ups.index'),
            buttonLabel: 'Voir les relances',
            details: $details,
            tag: 'resume-e-mails',
        );
    }
}
