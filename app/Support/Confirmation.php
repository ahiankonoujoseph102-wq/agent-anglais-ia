<?php

namespace App\Support;

/**
 * Confirmation des actions graves ou irréversibles, sans fenêtre du navigateur
 * (confirm(), alert(), prompt() sont interdits sur tout le site).
 *
 * - Avec JavaScript : le formulaire porte ces textes (attributes()) et public/js/app.js
 *   ouvre une fenêtre intégrée à la page ; « Confirmer » ajoute confirmed=1 et envoie.
 * - Sans JavaScript : le formulaire arrive sans confirmed=1 ; le middleware
 *   RequireConfirmation affiche une page de confirmation simple (même texte, mêmes boutons).
 */
final class Confirmation
{
    /**
     * @var array<string, array{title: string, message: string, confirm: string}>
     */
    public const ACTIONS = [
        'payment.approve' => [
            'title' => 'Valider ce paiement ?',
            'message' => 'La formule de l\'apprenant devient active (définitive après un accès provisoire). Cette action ne peut pas être annulée.',
            'confirm' => 'Confirmer la validation',
        ],
        'payment.reject' => [
            'title' => 'Refuser ce paiement ?',
            'message' => 'L\'accès de l\'apprenant est coupé aussitôt, séance en cours comprise. Il sera invité à vous écrire sur WhatsApp.',
            'confirm' => 'Confirmer le refus',
        ],
        'assessment.reset' => [
            'title' => 'Réinitialiser l\'évaluation ?',
            'message' => 'L\'évaluation et le niveau actuels sont effacés. L\'apprenant devra repasser l\'évaluation gratuite, que son espace lui proposera en premier.',
            'confirm' => 'Confirmer la réinitialisation',
        ],
        'password.temporary' => [
            'title' => 'Générer un mot de passe provisoire ?',
            'message' => 'L\'ancien mot de passe ne fonctionnera plus et l\'apprenant sera déconnecté de tous ses appareils.',
            'confirm' => 'Confirmer',
        ],
        'access.grant' => [
            'title' => 'Offrir cet accès ?',
            'message' => 'L\'apprenant aura la formule {plan} gratuitement, jusqu\'au {end}. Ce n\'est pas un paiement : il n\'apparaît dans aucun revenu. Vous pourrez le retirer à tout moment.',
            'confirm' => 'Offrir l\'accès',
        ],
        'access.revoke' => [
            'title' => 'Retirer l\'accès offert ?',
            'message' => 'L\'accès de l\'apprenant s\'arrête tout de suite, séance en cours comprise. Il sera invité à choisir une formule.',
            'confirm' => 'Retirer l\'accès',
        ],
        'ambassador.appoint' => [
            'title' => 'Faire de ce compte un ambassadeur ?',
            'message' => 'Son code personnel est créé et son premier mois gratuit s\'ouvre (formule {plan}, {days} jours). Il prend une place d\'ambassadeur.',
            'confirm' => 'Faire ambassadeur',
        ],
        'ambassador.renew' => [
            'title' => 'Renouveler le mois gratuit ?',
            'message' => 'L\'ambassadeur garde son accès gratuit {days} jours de plus (formule {plan}). Ce n\'est pas un paiement : il n\'apparaît dans aucun revenu.',
            'confirm' => 'Renouveler 1 mois',
        ],
        'ambassador.remove' => [
            'title' => 'Retirer cet ambassadeur du programme ?',
            'message' => 'Son accès gratuit s\'arrête tout de suite, séance en cours comprise, et sa place se libère. Son code et ses mois déjà offerts sont gardés si vous le réintégrez.',
            'confirm' => 'Retirer du programme',
        ],
        'application.reject' => [
            'title' => 'Refuser cette candidature ?',
            'message' => 'La personne garde son compte. Pensez à la prévenir sur WhatsApp ; elle pourra poser une nouvelle candidature plus tard.',
            'confirm' => 'Refuser la candidature',
        ],
        'commission.pay' => [
            'title' => 'Commission payée ?',
            'message' => 'Confirmez seulement après avoir envoyé l\'argent à l\'ambassadeur par Mobile Money. Cette action ne peut pas être annulée.',
            'confirm' => 'Commission payée',
        ],
        'commission.cancel' => [
            'title' => 'Annuler cette commission ?',
            'message' => 'L\'ambassadeur ne la touchera pas. Réservé aux cas de triche : le motif est gardé. Cette action ne peut pas être annulée.',
            'confirm' => 'Annuler la commission',
        ],
        'commission.add' => [
            'title' => 'Ajouter cette commission ?',
            'message' => 'Une commission due est créée pour l\'ambassadeur de ce compte, d\'après le prix de la formule choisie. Une seule commission par compte.',
            'confirm' => 'Ajouter la commission',
        ],
        'course.delete' => [
            'title' => 'Supprimer cette leçon ?',
            'message' => 'La leçon et ses photos ou son PDF sont effacés pour toujours, fichiers compris. Le temps du jour déjà utilisé avec le professeur reste compté.',
            'confirm' => 'Supprimer la leçon',
        ],
        'transcripts.delete' => [
            'title' => 'Supprimer les conversations de cet apprenant ?',
            'message' => 'Le texte de toutes ses conversations avec le professeur est effacé pour toujours. Ses séances, son niveau et son suivi restent.',
            'confirm' => 'Supprimer les conversations',
        ],
        'conversation.stop' => [
            'title' => 'Terminer maintenant ?',
            'message' => 'La conversation s\'arrête tout de suite. Le temps déjà utilisé est décompté.',
            'confirm' => 'Terminer la conversation',
        ],
        // « Enregistrer ma séance » : quitter la page avant de télécharger la vidéo (public/js/video-guard.js).
        'video.leave' => [
            'title' => 'Quitter sans votre vidéo ?',
            'message' => 'La vidéo de votre séance n\'est gardée que sur cette page : si vous la quittez avant de toucher « Télécharger ma vidéo », elle sera perdue.',
            'confirm' => 'Quitter la page',
        ],
    ];

    /**
     * @return array{title: string, message: string, confirm: string}
     */
    public static function for(string $action): array
    {
        $texts = self::ACTIONS[$action];

        // Ambassadeurs : formule et durée du mois gratuit lues dans la configuration (jamais écrites à la main).
        if (str_starts_with($action, 'ambassador.')) {
            $texts['message'] = strtr($texts['message'], [
                '{plan}' => Plan::find((string) config('platform.ambassadors.plan'))?->name ?? '',
                '{days}' => (string) config('platform.ambassadors.month_days'),
            ]);
        }

        return $texts;
    }

    /**
     * Textes d'une confirmation dont le message dépend de ce que l'administrateur a choisi dans
     * le formulaire : les marqueurs {plan} et {end} sont remplis par le serveur (repli sans
     * JavaScript) ou par public/js/app.js (fenêtre intégrée, d'après les champs du formulaire).
     *
     * @param  array<string, string>  $values
     * @return array{title: string, message: string, confirm: string}
     */
    public static function filled(string $action, array $values): array
    {
        $texts = self::for($action);
        $pairs = [];

        foreach ($values as $key => $value) {
            $pairs['{'.$key.'}'] = $value;
        }

        $texts['message'] = strtr($texts['message'], $pairs);

        return $texts;
    }

    /**
     * Attributs HTML d'un formulaire à confirmer (lus par public/js/app.js).
     */
    public static function attributes(string $action): string
    {
        $texts = self::for($action);

        return sprintf(
            'data-confirm="%s" data-confirm-title="%s" data-confirm-message="%s" data-confirm-button="%s"',
            e($action), e($texts['title']), e($texts['message']), e($texts['confirm']),
        );
    }
}
