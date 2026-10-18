<?php

namespace App\Support\Alerts;

/**
 * Une alerte à envoyer à l'administrateur : le même contenu pour la notification (titre, texte court, lien) et
 * pour l'e-mail (lignes de détail, bouton). Jamais de numéro de transaction, de capture ni de clé.
 */
final class AlertMessage
{
    /**
     * @param  list<array{0: string, 1: string}>  $details  paires « libellé », « valeur » (e-mail)
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly string $buttonLabel,
        public readonly array $details = [],
        public readonly ?int $paymentId = null,
        public readonly string $tag = 'alerte',
    ) {}

    /**
     * Contenu de la notification, tel que le service worker le reçoit (public/sw.js).
     *
     * @return array<string, mixed>
     */
    public function pushPayload(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
        ];
    }
}
