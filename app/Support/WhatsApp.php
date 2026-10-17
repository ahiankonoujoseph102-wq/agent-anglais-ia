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
}
