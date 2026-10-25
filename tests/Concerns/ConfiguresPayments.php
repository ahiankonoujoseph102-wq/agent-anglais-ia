<?php

namespace Tests\Concerns;

use App\Support\SiteSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Réglages de paiement FICTIFS et lecture de capture simulée (aucun appel réel à Gemini).
 * withoutDefaults() retire les valeurs par défaut de la configuration (numéros,
 * titulaires, coordonnées) pour tester un site où rien n'est configuré.
 */
trait ConfiguresPayments
{
    /** Lecture simulée en cours (false : simulation pas encore enregistrée). */
    protected array|false|null $proofReadingFake = false;

    protected function withoutDefaults(): void
    {
        foreach (config('payment.countries') as $code => $country) {
            foreach (array_keys($country['operators']) as $key) {
                config(["payment.countries.{$code}.operators.{$key}.number" => null, "payment.countries.{$code}.operators.{$key}.holder" => null]);
            }
        }

        config(['platform.contact.address' => null, 'platform.contact.phones' => [], 'platform.contact.email' => null, 'platform.contact.whatsapp' => null]);
    }

    /**
     * Le bloc de paiement VISIBLE de la page /abonnement/{formule} (les autres sont rendus
     * avec l'attribut hidden, pour l'enchaînement sans rechargement), ou '' s'il n'y en a pas.
     */
    protected function visiblePayment(string $html): string
    {
        // Le bloc affiché (sans « hidden ») : choix, étape 1 (numéro), étape 2 (montant), jusqu'à la consigne de l'étape 2.
        return preg_match('/<div class="pay-block" data-pay-block="[^"]+"\s*>(.*?class="pay-step-text">[^<]*<\/p>)/s', $html, $match) ? $match[1] : '';
    }

    protected function configurePayments(): void
    {
        SiteSettings::save([
            SiteSettings::operatorNumberKey('TG', 'mixx') => '+228 90 00 00 11',
            SiteSettings::operatorHolderKey('TG', 'mixx') => 'Titulaire Fictif Mixx',
            SiteSettings::operatorNumberKey('TG', 'flooz') => '+228 90 00 00 22',
            SiteSettings::operatorHolderKey('TG', 'flooz') => 'Titulaire Fictif Flooz',
            SiteSettings::operatorNumberKey('BJ', 'celtiis') => '+229 01 00 00 00 33',
            'contact.whatsapp' => '+228 90 00 00 99',
        ]);
    }

    /**
     * Ce que l'IA « lit » sur la capture : une confirmation Flooz valable par défaut.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function proofReading(array $overrides = []): array
    {
        return array_merge([
            'readable' => true,
            'operator' => 'Flooz',
            'amount' => 7500,
            'currency' => 'FCFA',
            'date_time' => now()->subMinutes(10)->format('Y-m-d\TH:i'),
            'transaction_id' => 'TX 2026.00123',
            'recipient_number' => '90000022',
            'recipient_name' => 'Titulaire Fictif Flooz',
        ], $overrides);
    }

    /**
     * Réponse simulée de l'API Interactions (null : le modèle ne répond pas).
     *
     * @param  array<string, mixed>|null  $reading
     */
    protected function fakeProofReader(?array $reading): void
    {
        config(['services.gemini.key' => 'cle-de-test']);

        // Une seule simulation enregistrée (Http::fake les empile) : elle lit la dernière lecture voulue.
        $first = $this->proofReadingFake === false;
        $this->proofReadingFake = $reading;

        if ($first) {
            Http::fake(['*/interactions' => fn () => $this->proofReadingFake === null
                ? Http::response(['error' => ['message' => 'Indisponible']], 503)
                : Http::response(['steps' => [
                    ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode($this->proofReadingFake)]]],
                ]]),
            ]);
        }
    }

    protected function proofImage(string $name = 'capture.png', int $width = 600, int $height = 1200): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }
}
