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
