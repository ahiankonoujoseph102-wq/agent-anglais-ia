<?php

namespace App\Support\Reminders;

use App\Enums\LessonStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Mail\LessonReminderMail;
use App\Models\LearnerPushSubscription;
use App\Models\LearnerReminder;
use App\Models\Program;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Alerts\PushTransport;
use App\Support\Alerts\Redact;
use App\Support\Alerts\WebPushTransport;
use App\Support\Countries;
use App\Support\DailyTime;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\WorkingTogether;
use App\Support\MailQuota;
use App\Support\PaymentCountry;
use App\Support\Plan;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Suivi des élèves PAYANTS, comme un vrai professeur : rappels avant la séance du jour, relance d'une séance
 * manquée, e-mail à ajouter et programme peu suivi dits au début de la séance (en français).
 *
 * Élève suivi : un abonnement payé en cours (définitif ou accès provisoire ; ni accès offert, ni mois
 * d'ambassadeur), jamais l'équipe. Rappels : seulement avec un programme actif qui a une heure de séance, les jours
 * de travail choisis, à l'heure du pays de l'élève (platform.reminders). Notifications gratuites (Web Push) ;
 * e-mails dans la limite gratuite de Brevo, avec une réserve pour le reste (MailQuota::allowsReminder).
 */
final class LessonReminders
{
    /** L'élève est-il suivi (abonné payant) ? */
    public static function follows(?User $user): bool
    {
        if ($user === null || $user->role !== Role::Learner) {
            return false;
        }

        $subscription = $user->activeSubscription;

        return $subscription !== null
            && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Provisional], true)
            && $subscription->granted_days === null
            && $subscription->ambassador_id === null;
    }

    /** Abonné payant sans adresse e-mail : le professeur la demande au début de la séance. */
    public static function needsEmail(?User $user): bool
    {
        return self::follows($user) && blank($user->email);
    }

    /** Heure des séances du programme actif (« 18:30 »), ou null. */
    public static function studyTime(User $user): ?string
    {
        $time = $user->activeProgram()->first()?->study_time;

        return filled($time) ? substr((string) $time, 0, 5) : null;
    }

    /** « 18 h 30 », « 9 h 00 ». */
    public static function timeLabel(string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time) + [1 => 0]);

        return $hour.' h '.str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Jours de travail manqués sur les platform.reminders.missed_days derniers jours (hier compris, aujourd'hui
     * non) : jour de travail du programme, couvert par un abonnement payé, sans séance du jour tenue (au moins
     * held_seconds). Les jours avant le programme ou avant l'abonnement ne comptent pas.
     */
    public static function missedDays(User $user): int
    {
        $program = $user->activeProgram()->first();

        if ($program === null) {
            return 0;
        }

        $days = array_map('intval', $program->study_days ?: []);
        $from = today()->subDays((int) config('platform.reminders.missed_days'));
        $subscriptions = $user->subscriptions()->paid()->whereNull('ambassador_id')
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Expired, SubscriptionStatus::Provisional])
            ->get(['starts_at', 'ends_at']);
        $held = $user->lessons()
            ->where('date', '>=', $from->toDateString())
            ->where('used_seconds', '>=', WorkingTogether::heldSeconds())
            ->pluck('date')
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->all();
        $missed = 0;

        for ($day = $from->copy(); $day->lt(today()); $day->addDay()) {
            $end = $day->copy()->endOfDay();

            if (! in_array($day->dayOfWeekIso, $days, true) || $program->created_at->gt($end) || in_array($day->toDateString(), $held, true)) {
                continue;
            }

            // Couvert toute la journée par un abonnement payé.
            if ($subscriptions->contains(fn ($subscription) => $subscription->starts_at->lte($day) && $subscription->ends_at->gte($end))) {
                $missed++;
            }
        }

        return $missed;
    }

    public static function missesOften(User $user): bool
    {
        return self::missedDays($user) >= (int) config('platform.reminders.missed_threshold');
    }

    /**
     * Ce que le professeur doit dire en français au début d'une NOUVELLE séance du jour, avant l'échauffement
     * (marqueur :followup_note de cues.lesson_start) : e-mail à ajouter, puis programme peu suivi (une fois par
     * semaine au plus). Vide sinon : l'indication de début est alors exactement celle d'avant.
     */
    public static function sessionNote(User $user): string
    {
        if (! self::follows($user)) {
            return '';
        }

        $parts = [];

        if (blank($user->email)) {
            $parts[] = TeacherSetup::cue('followup_email');
        }

        $key = 'missed-talk:'.$user->id;

        if (self::missesOften($user) && ! Cache::has($key)) {
            $parts[] = TeacherSetup::cue('followup_missed');
            Cache::put($key, true, now()->addDays((int) config('platform.reminders.missed_days')));
        }

        // Avant-dernier et dernier jour d'un abonnement payé pas encore renouvelé : en dernier, juste avant la leçon.
        $days = self::daysBeforeLastDay($user);

        if ($days !== null) {
            $parts[] = TeacherSetup::cue('followup_renew', ['when' => $days === 0 ? "aujourd'hui" : 'demain']);
        }

        return $parts === [] ? '' : TeacherSetup::cue('followup_intro').implode('', $parts).TeacherSetup::cue('followup_outro');
    }

    /**
     * Abonnement payé en cours qui se termine bientôt et n'est pas encore renouvelé : nombre de jours avant son
     * DERNIER jour (1 : la veille, 0 : le dernier jour, à l'heure du pays de l'élève), sinon null. Dernier jour :
     * jour de la fin de l'accès (ends_at, une seconde avant). Renouvelé : un abonnement payé qui finit plus tard,
     * ou un paiement déjà envoyé et en vérification.
     */
    public static function daysBeforeLastDay(User $user, ?CarbonImmutable $now = null): ?int
    {
        if (! self::follows($user)) {
            return null;
        }

        $subscription = $user->activeSubscription;
        $timezone = Countries::timezone($user->country);
        $today = ($now ?? CarbonImmutable::now())->setTimezone($timezone)->startOfDay();
        $last = CarbonImmutable::parse($subscription->ends_at)->setTimezone($timezone)->subSecond()->startOfDay();
        $days = (int) round($today->diffInDays($last, false));

        if ($days < 0 || $days > 1 || self::renewed($user, $subscription)) {
            return null;
        }

        return $days;
    }

    private static function renewed(User $user, Subscription $subscription): bool
    {
        return $user->subscriptions()->paid()->whereNull('ambassador_id')->whereKeyNot($subscription->id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Provisional])
            ->where('ends_at', '>', $subscription->ends_at)
            ->exists()
            || $user->payments()->where('status', PaymentStatus::Pending)->exists();
    }

    /** Adresse pour renouveler : la formule en cours si elle existe encore, sinon le choix des formules. */
    public static function renewUrl(User $user): string
    {
        $plan = $user->activeSubscription?->plan;

        return filled($plan) && Plan::find((string) $plan) !== null
            ? route('payment.create', $plan)
            : route('payment.plans');
    }

    /** Moyens de paiement du pays de l'élève (« Mixx by Yas ou Flooz »), ou null hors des pays de paiement. */
    public static function paymentMethods(User $user): ?string
    {
        $country = PaymentCountry::findAvailable($user->countryCode());

        if ($country === null) {
            return null;
        }

        $names = array_values(array_map(fn ($operator) => $operator->name, $country->operators));

        return count($names) > 1
            ? implode(', ', array_slice($names, 0, -1)).' ou '.end($names)
            : ($names[0] ?? null);
    }

    /**
     * Envoie les rappels dus à cet instant. Renvoie le nombre de rappels envoyés.
     */
    public function run(?CarbonImmutable $now = null): int
    {
        if (! config('platform.reminders.enabled')) {
            return 0;
        }

        $now ??= CarbonImmutable::now();
        $sent = 0;

        $users = User::query()
            ->where('role', Role::Learner)
            ->whereHas('subscriptions', fn ($query) => $query->paid()->whereNull('ambassador_id')
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Provisional])
                ->where('ends_at', '>', $now))
            ->whereHas('programs', fn ($query) => $query->where('is_active', true)->whereNotNull('study_time'))
            ->with('activeSubscription')
            ->get();

        foreach ($users as $user) {
            try {
                $kind = $this->dueKind($user, $now);

                if ($kind !== null && $this->send($user, $kind[0], $kind[1], $kind[2])) {
                    $sent++;
                }
            } catch (Throwable $e) {
                Log::warning('Rappel de séance : envoi impossible.', ['reason' => Redact::reason($e->getMessage())]);
            }
        }

        return $sent + $this->runRenewals($now);
    }

    /**
     * Fin d'abonnement : la veille du dernier jour, une fois, entre renew_hour et renew_until_hour (heure du pays),
     * e-mail et notification au nom du professeur. Aucun programme ni heure de séance n'est nécessaire.
     */
    private function runRenewals(CarbonImmutable $now): int
    {
        $sent = 0;

        $users = User::query()
            ->where('role', Role::Learner)
            ->whereHas('subscriptions', fn ($query) => $query->paid()->whereNull('ambassador_id')
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Provisional])
                ->where('ends_at', '>', $now)
                ->where('ends_at', '<=', $now->addDays(3)))
            ->with('activeSubscription')
            ->get();

        foreach ($users as $user) {
            try {
                $local = $now->setTimezone(Countries::timezone($user->country));

                if ($local->hour < (int) config('platform.reminders.renew_hour')
                    || $local->hour >= (int) config('platform.reminders.renew_until_hour')
                    || self::daysBeforeLastDay($user, $now) !== 1) {
                    continue;
                }

                if ($this->send($user, LearnerReminder::RENEW, $local->toDateString(), '')) {
                    $sent++;
                }
            } catch (Throwable $e) {
                Log::warning('Rappel de fin d\'abonnement : envoi impossible.', ['reason' => Redact::reason($e->getMessage())]);
            }
        }

        return $sent;
    }

    /**
     * Rappel dû maintenant pour cet élève : [sorte, jour, heure « 18:30 »], ou null.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function dueKind(User $user, CarbonImmutable $now): ?array
    {
        if (! self::follows($user)) {
            return null;
        }

        /** @var Program|null $program */
        $program = $user->activeProgram()->first();
        $time = $program !== null ? self::studyTime($user) : null;

        if ($time === null) {
            return null;
        }

        $local = $now->setTimezone(Countries::timezone($user->country));

        if (! in_array($local->dayOfWeekIso, array_map('intval', $program->study_days ?: []), true)) {
            return null;
        }

        $at = $local->setTimeFromTimeString($time.':00');
        $late = (int) config('platform.reminders.late_minutes');
        $soon = $at->subMinutes((int) config('platform.reminders.soon_minutes'));
        $last = $at->subMinutes((int) config('platform.reminders.now_minutes'));
        $missed = $at->addMinutes((int) config('platform.reminders.missed_after_minutes'));

        $kind = match (true) {
            $local->gte($soon) && $local->lt($last) && $local->lt($soon->addMinutes($late)) => LearnerReminder::SOON,
            $local->gte($last) && $local->lt($last->addMinutes($late)) => LearnerReminder::NOW,
            $local->gte($missed) && $local->lt($missed->addMinutes($late)) && $missed->isSameDay($at) => LearnerReminder::MISSED,
            default => null,
        };

        // Séance déjà faite (ou en cours) aujourd'hui, ou temps du jour déjà pris : aucun rappel.
        if ($kind === null || $this->lessonDoneToday($user) || $user->remainingSecondsToday(false) < DailyTime::minStartSeconds()) {
            return null;
        }

        return [$kind, $local->toDateString(), $time];
    }

    private function lessonDoneToday(User $user): bool
    {
        return $user->lessons()
            ->whereDate('date', today())
            ->where(fn ($query) => $query
                ->where('used_seconds', '>=', WorkingTogether::heldSeconds())
                ->orWhere('status', LessonStatus::InProgress))
            ->exists();
    }

    /**
     * Envoie un rappel (une seule fois : la ligne est réservée d'abord, index unique), notification puis e-mail.
     */
    private function send(User $user, string $kind, string $day, string $time): bool
    {
        $claimed = LearnerReminder::query()->insertOrIgnore([
            'user_id' => $user->id, 'day' => $day, 'kind' => $kind, 'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($claimed === 0) {
            return false;
        }

        $reminder = LearnerReminder::query()->where(['user_id' => $user->id, 'day' => $day, 'kind' => $kind])->firstOrFail();
        $teacher = Teachers::forUser($user);
        $renew = $kind === LearnerReminder::RENEW;
        $message = self::message($kind, $teacher, $renew ? '' : self::timeLabel($time));

        $reminder->push_status = $this->push($user, $message + ($renew
            ? ['url' => parse_url(self::renewUrl($user), PHP_URL_PATH) ?: '/abonnement', 'tag' => 'renouvellement']
            : ['url' => '/seance', 'tag' => 'seance-du-jour']));

        if ($kind === LearnerReminder::NOW) {
            $reminder->mail_status = 'skipped';
        } else {
            [$reminder->mail_status, $reminder->mail_error] = $this->mail($user, $kind, $teacher, $time);
        }

        $reminder->save();

        return true;
    }

    /**
     * Texte du rappel, au nom du professeur de l'élève.
     *
     * @return array{title: string, body: string}
     */
    public static function message(string $kind, Teacher $teacher, string $time): array
    {
        $name = $teacher->name();

        return match ($kind) {
            LearnerReminder::SOON => [
                'title' => "{$name} vous attend à {$time}",
                'body' => 'Votre séance d\'anglais commence dans '.config('platform.reminders.soon_minutes').' minutes. Installez-vous dans un coin calme : à tout à l\'heure !',
            ],
            LearnerReminder::RENEW => [
                'title' => 'Votre abonnement se termine demain',
                'body' => "Renouvelez-le pour continuer vos progrès sans interruption. Je compte sur vous ! {$name}",
            ],
            LearnerReminder::NOW => [
                'title' => 'C\'est l\'heure de votre séance !',
                'body' => "{$name} vous attend maintenant. Touchez ici pour commencer.",
            ],
            default => [
                'title' => 'Votre séance d\'aujourd\'hui vous attend',
                'body' => "Nous ne nous sommes pas vus aujourd'hui. Il vous reste du temps : touchez ici pour faire votre séance avec {$name}.",
            ],
        };
    }

    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $message
     */
    private function push(User $user, array $message): string
    {
        if (! WebPushTransport::configured()) {
            return 'skipped';
        }

        $devices = LearnerPushSubscription::query()->where('user_id', $user->id)->get();

        if ($devices->isEmpty()) {
            return 'none';
        }

        $transport = app(PushTransport::class);
        $payload = $message;
        $ok = false;

        foreach ($devices as $device) {
            $result = $transport->send($device, $payload);

            if ($result->sent) {
                $ok = true;
                $device->forceFill(['last_sent_at' => now()])->save();
            } elseif ($result->gone) {
                $device->delete();
            }
        }

        return $ok ? 'sent' : 'failed';
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function mail(User $user, string $kind, Teacher $teacher, string $time): array
    {
        if (blank($user->email)) {
            return ['no_email', null];
        }

        if (! MailQuota::configured()) {
            return ['skipped', null];
        }

        if (! MailQuota::allowsReminder()) {
            return ['quota', null];
        }

        try {
            Mail::to($user->email)->send(new LessonReminderMail($user, $teacher, $kind, $time === '' ? '' : self::timeLabel($time)));
        } catch (Throwable $e) {
            return ['failed', mb_strimwidth(Redact::reason($e->getMessage()), 0, 200, '…')];
        }

        return ['sent', null];
    }
}
