<?php

namespace App\Support;

class WhatsApp
{
    /**
     * Lien qui ouvre WhatsApp vers ce numéro avec un message déjà rédigé.
     * Sans numéro (réglage pas encore saisi), WhatsApp s'ouvre pour choisir le destinataire.
     */
    public static function url(?string $number, string $message = ''): string
    {
        $url = 'https://wa.me/'.preg_replace('/\D+/', '', (string) $number);

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }

    /**
     * Bouton WhatsApp de la navigation : le groupe (platform.contact.whatsapp_group) s'il existe,
     * sinon une conversation avec le numéro WhatsApp de l'équipe ; null si aucun des deux.
     */
    public static function communityUrl(): ?string
    {
        $group = trim((string) config('platform.contact.whatsapp_group'));

        if (str_starts_with($group, 'https://chat.whatsapp.com/')) {
            return $group;
        }

        $number = SiteSettings::whatsapp();

        return $number ? self::url($number) : null;
    }
}
