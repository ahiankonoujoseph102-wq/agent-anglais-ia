<?php

namespace App\Support;

class WhatsApp
{
    /**
     * Lien qui ouvre WhatsApp vers ce numéro avec un message déjà rédigé.
     */
    public static function url(string $number, string $message = ''): string
    {
        $url = 'https://wa.me/'.preg_replace('/\D+/', '', $number);

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }
}
