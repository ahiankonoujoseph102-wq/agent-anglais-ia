<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Réglages saisis par l'administrateur (page « Réglages », table settings) :
 * numéros de réception et titulaires Mobile Money, coordonnées du centre.
 * Rien de tout cela n'est dans le dépôt (public).
 *
 * Lus une seule fois par requête ; si la base est indisponible (page d'erreur),
 * tout est simplement vide.
 */
class SiteSettings
{
    /** @var array<string, string>|null */
    private static ?array $values = null;

    public const CONTACT_KEYS = ['contact.address', 'contact.phones', 'contact.email', 'contact.whatsapp'];

    public static function get(string $key): ?string
    {
        $value = trim((string) (self::all()[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            $value = trim((string) $value);
            Setting::put($key, $value === '' ? null : $value);
        }

        self::flush();
    }

    public static function flush(): void
    {
        self::$values = null;
    }

    /**
     * @return array<string, string>
     */
    private static function all(): array
    {
        if (self::$values === null) {
            try {
                self::$values = Setting::query()->whereNotNull('value')->pluck('value', 'key')->all();
            } catch (Throwable) {
                return [];
            }
        }

        return self::$values;
    }

    public static function operatorNumberKey(string $country, string $operator): string
    {
        return "payment.{$country}.{$operator}.number";
    }

    public static function operatorHolderKey(string $country, string $operator): string
    {
        return "payment.{$country}.{$operator}.holder";
    }

    public static function operatorNumber(string $country, string $operator): ?string
    {
        return self::get(self::operatorNumberKey($country, $operator));
    }

    public static function operatorHolder(string $country, string $operator): ?string
    {
        return self::get(self::operatorHolderKey($country, $operator));
    }

    /**
     * Coordonnées du centre : adresse, horaires (configuration), téléphones, e-mail, WhatsApp.
     *
     * @return array{address: ?string, hours: string, phones: list<string>, email: ?string, whatsapp: ?string}
     */
    public static function contact(): array
    {
        $phones = preg_split('/[\r\n,;]+/', (string) self::get('contact.phones')) ?: [];

        return [
            'address' => self::get('contact.address'),
            'hours' => (string) config('platform.contact.hours'),
            'phones' => array_values(array_filter(array_map('trim', $phones))),
            'email' => self::get('contact.email'),
            'whatsapp' => self::get('contact.whatsapp'),
        ];
    }

    public static function whatsapp(): ?string
    {
        return self::get('contact.whatsapp');
    }
}
