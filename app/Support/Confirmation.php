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
        'course.delete' => [
            'title' => 'Supprimer cette leçon ?',
            'message' => 'La leçon et ses photos ou son PDF sont effacés pour toujours, fichiers compris. Les leçons du jour déjà faites avec Teacher Joe restent comptées.',
            'confirm' => 'Supprimer la leçon',
        ],
        'conversation.stop' => [
            'title' => 'Terminer maintenant ?',
            'message' => 'La conversation s\'arrête tout de suite. Le temps déjà utilisé est décompté.',
            'confirm' => 'Terminer la conversation',
        ],
    ];

    /**
     * @return array{title: string, message: string, confirm: string}
     */
    public static function for(string $action): array
    {
        return self::ACTIONS[$action];
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
