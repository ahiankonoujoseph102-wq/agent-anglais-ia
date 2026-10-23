<?php

namespace App\Support;

/**
 * « Inviter un ami sur WhatsApp » : lien de partage WhatsApp SANS numéro (l'apprenant choisit le contact) et message
 * prérempli. Le message ne contient ni le nom ni le niveau de l'apprenant ; l'adresse du site vient de APP_URL
 * (Seo::url), le nom du professeur, la plateforme et la durée de l'évaluation de la configuration. Rien n'est envoyé
 * à la plateforme : c'est un simple lien.
 */
class FriendInvite
{
    public static function message(): string
    {
        return strtr(
            'Bonjour ! J\'apprends à parler anglais avec :teacher, le professeur de :platform. Toi aussi, passe l\'évaluation orale gratuite : :minutes minutes à l\'oral pour connaître ton niveau d\'anglais, depuis ton téléphone. C\'est ici : :url',
            [
                ':teacher' => (string) config('avatar.model.name'),
                ':platform' => (string) config('platform.name'),
                ':minutes' => (string) config('platform.assessment.max_minutes'),
                ':url' => Seo::url(),
            ],
        );
    }

    public static function url(): string
    {
        return WhatsApp::url('', self::message());
    }
}
