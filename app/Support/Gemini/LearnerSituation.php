<?php

namespace App\Support\Gemini;

use App\Enums\AssessmentStatus;
use App\Enums\ProgramStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;

/**
 * Situation de l'apprenant sur le site, donnée au professeur à chaque jeton :
 * prénom, page où il se trouve, étape actuelle du parcours.
 */
class LearnerSituation
{
    /** Page de la conversation, selon le type de consignes (config/teacher.php). */
    private const PAGES = [
        'assessment' => 'la page « Évaluation gratuite » (conversation vocale avec toi, en ce moment)',
        'lesson' => 'la page de la séance du jour (conversation vocale avec toi, en ce moment)',
        'level_test' => 'la page du bilan de fin de niveau (conversation vocale avec toi, en ce moment)',
        'course' => 'la page « Mes cours » : une séance avec toi sur une leçon d\'anglais de son école (conversation vocale, en ce moment)',
    ];

    public static function describe(User $user, string $kind): string
    {
        $lines = [
            'Prénom : '.$user->name,
            'Page actuelle : '.(self::PAGES[$kind] ?? 'une conversation vocale avec toi'),
            'Étape actuelle : '.self::step($user, $kind),
        ];

        if ($user->level !== null) {
            $lines[] = 'Niveau : '.$user->level->value.' ('.$user->level->label().')';
        }

        if ($user->exists && $subscription = $user->activeSubscription()->first()) {
            $lines[] = 'Formule active : '.$subscription->plan_name.', jusqu\'au '.$subscription->ends_at->format('d/m/Y').'.';
        }

        return implode("\n", $lines);
    }

    private static function step(User $user, string $kind): string
    {
        if ($kind === 'assessment') {
            return 'évaluation gratuite en cours. Prochaines étapes après l\'évaluation : choisir une formule, payer, construire son programme.';
        }

        if ($kind === 'level_test') {
            return 'bilan de fin de niveau en cours.';
        }

        if ($kind === 'course') {
            return 'séance « Mes cours » en cours : il t\'a apporté une leçon pour la comprendre et s\'exercer, en complément de son programme.';
        }

        if (! $user->exists) {
            return 'séance du jour en cours.';
        }

        $assessment = $user->assessment()->first();

        if ($assessment === null || $assessment->status === AssessmentStatus::InProgress) {
            return 'évaluation gratuite pas encore terminée.';
        }

        if ($payment = $user->pendingPayment()->first()) {
            if ($user->activeSubscription()->where('status', SubscriptionStatus::Provisional)->exists()) {
                return 'capture du paiement envoyée et lue (formule '.$payment->plan_name.') : accès provisoire de 24 heures ouvert, l\'équipe vérifie le paiement. Les séances sont possibles.';
            }

            return 'capture du paiement envoyée (formule '.$payment->plan_name.'), vérification par l\'équipe en cours ; l\'apprenant sera informé dans son espace.';
        }

        if ($user->activeSubscription()->doesntExist()) {
            return 'niveau obtenu ; aucune formule active : prochaine étape, choisir une formule et payer (« Mon espace », « Choisir ma formule »).';
        }

        $program = $user->activeProgram()->first();

        if ($program === null) {
            return 'formule active ; programme pas encore construit (questionnaire « Construire mon programme » dans « Mon espace »).';
        }

        if ($program->status === ProgramStatus::LevelTestDue) {
            return 'programme '.$program->level->value.' terminé ; bilan de fin de niveau à passer.';
        }

        return 'programme '.$program->level->value.' en cours ('.$program->progressPercent().' % fait) ; séance du jour en cours.';
    }
}
