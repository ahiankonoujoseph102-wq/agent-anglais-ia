<?php

namespace App\Support;

use App\Enums\Level;
use App\Models\Attestation;

/**
 * Partages VOLONTAIRES sur WhatsApp (page de résultat de l'évaluation, attestation de niveau) : un lien wa.me SANS
 * numéro (l'apprenant choisit le contact) et un message prérempli qui dit la vérité : le niveau réellement trouvé par
 * l'évaluation, ou le niveau d'une attestation délivrée, avec le lien vers le site (APP_URL, Seo::url). Rien ne part
 * sans un toucher de l'apprenant, et rien n'est reçu par le site. Le paramètre utm_source=whatsapp permet de voir,
 * dans l'administration, les inscrits arrivés par un partage. Aucune promesse, aucun chiffre ajouté.
 */
final class ShareLinks
{
    public static function levelMessage(Level $level): string
    {
        return strtr(
            'J\'ai passé l\'évaluation orale gratuite de :platform avec :teacher : mon niveau d\'anglais est :level (:label). Et toi, quel est ton niveau ? L\'évaluation est gratuite, :minutes minutes à l\'oral depuis ton téléphone : :url',
            [
                ':platform' => (string) config('platform.name'),
                ':teacher' => (string) config('avatar.model.name'),
                ':level' => $level->value,
                ':label' => mb_strtolower($level->label()),
                ':minutes' => (string) config('platform.assessment.max_minutes'),
                ':url' => self::withSource(Seo::url(), 'niveau'),
            ],
        );
    }

    public static function levelUrl(Level $level): string
    {
        return WhatsApp::url('', self::levelMessage($level));
    }

    public static function attestationMessage(Attestation $attestation): string
    {
        return strtr(
            'J\'ai validé le niveau :level (:label) en anglais avec :platform. Mon attestation de niveau se vérifie ici : :url',
            [
                ':level' => $attestation->level->value,
                ':label' => mb_strtolower($attestation->level->label()),
                ':platform' => (string) config('platform.name'),
                ':url' => self::withSource(self::verifyUrl($attestation), 'attestation'),
            ],
        );
    }

    public static function attestationUrl(Attestation $attestation): string
    {
        return WhatsApp::url('', self::attestationMessage($attestation));
    }

    /** Adresse complète de la page de vérification, toujours sur APP_URL. */
    public static function verifyUrl(Attestation $attestation): string
    {
        return Seo::url(route('attestation.verify', $attestation->code, false));
    }

    /** Image d'aperçu de la page de vérification (public/images/partage/attestation-{niveau}.jpg), sinon null. */
    public static function attestationImage(Level $level): ?string
    {
        $path = 'images/partage/attestation-'.strtolower($level->value).'.jpg';

        return is_file(public_path($path)) ? $path : null;
    }

    private static function withSource(string $url, string $campaign): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'utm_source' => 'whatsapp',
            'utm_medium' => 'partage',
            'utm_campaign' => $campaign,
        ]);
    }
}
