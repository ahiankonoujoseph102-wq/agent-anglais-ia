<?php

namespace App\Support\Reminders;

use App\Enums\ErrorStatus;
use App\Enums\ProgramLessonStatus;
use App\Enums\Skill;
use App\Models\Subscription;
use App\Support\LearnerName;
use App\Support\Live\WorkingTogether;
use App\Support\Plan;
use App\Support\Reviews\Reviews;
use App\Support\Reviews\TimeBonuses;

/**
 * Bilan d'une formule qui se termine : UNIQUEMENT des faits lus dans la base (séances tenues, minutes parlées,
 * niveau, programme, compétences notées, erreurs encore à revoir), jamais un compliment inventé. Sert à l'e-mail
 * de fin de formule (au nom du professeur) et au message WhatsApp prérempli de l'administration.
 */
final class SubscriptionReport
{
    /**
     * @param  list<array{wrong: string, correct: string}>  $errors
     */
    public function __construct(
        public readonly ?string $firstName,
        public readonly int $sessions,
        public readonly int $minutes,
        public readonly ?string $level,
        public readonly ?int $lessonsDone,
        public readonly ?int $lessonsTotal,
        public readonly ?string $strength,
        public readonly ?string $toImprove,
        public readonly array $errors,
        public readonly bool $inviteReview,
        public readonly int $reviewMinutes,
    ) {}

    public static function for(Subscription $subscription): self
    {
        $user = $subscription->user;
        $from = $subscription->starts_at;
        $to = $subscription->ends_at;
        $held = WorkingTogether::heldSeconds();

        $lessons = $user->lessons()->whereBetween('created_at', [$from, $to])->get();
        $courses = $user->courseSessions()->whereBetween('created_at', [$from, $to])->get();
        $seconds = (int) $lessons->sum('used_seconds') + (int) $courses->sum('used_seconds');
        $sessions = $lessons->where('used_seconds', '>=', $held)->count() + $courses->where('used_seconds', '>=', $held)->count();

        // Compétences notées par le professeur pendant la formule : la meilleure et la plus faible (si elles diffèrent).
        $averages = [];

        foreach (Skill::cases() as $skill) {
            $scores = $lessons->pluck($skill->column())->filter(fn ($score) => $score !== null);

            if ($scores->isNotEmpty()) {
                $averages[$skill->label()] = $scores->avg();
            }
        }

        arsort($averages);
        $strength = $toImprove = null;

        if (count($averages) >= 2 && max($averages) - min($averages) >= 0.5) {
            $strength = array_key_first($averages);
            $toImprove = array_key_last($averages);
        }

        $program = $user->activeProgram;

        $errors = $user->learnerErrors()->where('status', ErrorStatus::Open)->latest()->limit(2)->get()
            ->map(fn ($error) => ['wrong' => (string) $error->wrong, 'correct' => (string) $error->correct])
            ->filter(fn (array $error) => $error['wrong'] !== '' && $error['correct'] !== '')
            ->values()->all();

        return new self(
            firstName: LearnerName::first($user),
            sessions: $sessions,
            minutes: intdiv($seconds, 60),
            level: $user->level ? $user->level->value.' ('.$user->level->label().')' : null,
            lessonsDone: $program?->programLessons()->where('status', ProgramLessonStatus::Completed)->count(),
            lessonsTotal: $program?->programLessons()->count(),
            strength: $strength,
            toImprove: $toImprove,
            errors: $errors,
            // L'avis est demandé une seule fois : jamais à un élève qui en a déjà laissé un.
            inviteReview: $user->review()->doesntExist() && Reviews::canReview($user),
            reviewMinutes: intdiv(TimeBonuses::seconds(), 60),
        );
    }

    /** L'élève a vraiment travaillé pendant cette formule : sinon, jamais de félicitations. */
    public function worked(): bool
    {
        return $this->sessions > 0;
    }

    /**
     * Phrases du bilan, dans l'ordre (sans salutation ni signature), communes à l'e-mail et au message WhatsApp.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = [];

        if ($this->worked()) {
            $lines[] = 'Bravo pour votre travail : '.$this->sessions.' '.($this->sessions === 1 ? 'séance' : 'séances')
                .($this->minutes > 0 ? ', environ '.$this->minutes.' '.($this->minutes === 1 ? 'minute' : 'minutes').' à parler anglais' : '').'.';
        } else {
            $lines[] = 'Nous n\'avons pas eu l\'occasion de travailler ensemble pendant cette formule. Votre programme vous attend toujours.';
        }

        if ($this->level !== null) {
            $lines[] = 'Votre niveau : '.$this->level.'.';
        }

        if ($this->lessonsTotal) {
            $lines[] = 'Dans votre programme, vous avez terminé '.$this->lessonsDone.' '.($this->lessonsDone === 1 ? 'leçon' : 'leçons').' sur '.$this->lessonsTotal.'.';
        }

        if ($this->worked() && $this->strength !== null) {
            $lines[] = 'Votre point fort : '.mb_strtolower($this->strength).'. À travailler en priorité : '.mb_strtolower($this->toImprove).'.';
        }

        foreach ($this->errors as $error) {
            $lines[] = 'À retenir : on ne dit pas « '.$error['wrong'].' », on dit « '.$error['correct'].' ».';
        }

        return $lines;
    }

    /** Phrase d'encouragement à continuer : vraie, sans pression ni promesse. */
    public function encouragement(): string
    {
        return $this->worked()
            ? 'Pour garder ces progrès, le mieux est de ne pas vous arrêter : votre niveau, votre programme et vos erreurs à revoir vous attendent, là où vous les avez laissés.'
            : 'Quelques minutes chaque jour suffisent pour commencer à progresser : votre niveau et votre programme vous attendent.';
    }

    /** Invitation à donner un avis honnête, avec les minutes offertes (quelle que soit la note), ou null. */
    public function reviewInvitation(): ?string
    {
        if (! $this->inviteReview) {
            return null;
        }

        // « Une journée » seulement si c'est vrai : une formule dont le temps par jour est exactement ces minutes.
        $day = collect(Plan::all())->first(fn (Plan $plan) => $plan->dailyMinutes === $this->reviewMinutes);

        return 'Votre avis honnête, positif ou négatif, nous aide beaucoup. Donnez-le ici : '.route('reviews.edit').'. Pour vous remercier, '
            .$this->reviewMinutes.' minutes de séance vous sont offertes'.($day ? ' (une journée de la formule '.$day->name.')' : '')
            .', quelle que soit votre note : elles s\'ajoutent à votre première séance avec votre prochaine formule.';
    }
}
