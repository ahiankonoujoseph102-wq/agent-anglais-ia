<?php

namespace App\Support;

class TemporaryPassword
{
    /**
     * Caractères faciles à lire et à dicter (sans 0/O, 1/l/I).
     */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function generate(int $length = 10): string
    {
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $password;
    }
}
