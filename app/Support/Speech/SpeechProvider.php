<?php

namespace App\Support\Speech;

use App\Support\Gemini\SpeechFailed;

/**
 * Un fournisseur de synthèse vocale pour les messages (bienvenue, résultat de l'évaluation) : Gemini, ou
 * OpenAI en secours. Chaque fichier gardé porte le nom du fournisseur et de la voix qui l'ont produit : un
 * message n'est jamais joué avec deux voix. La clé n'est jamais écrite, journalisée ni affichée.
 */
abstract class SpeechProvider
{
    /** Nom court, gardé dans les noms de fichiers et les journaux : « gemini », « openai ». */
    abstract public function name(): string;

    /** Nom affiché dans l'administration. */
    abstract public function label(): string;

    /** Variable d'environnement de la clé (le nom seulement, jamais la valeur). */
    abstract public function keyVariable(): string;

    abstract public function hasKey(): bool;

    abstract public function model(): string;

    abstract public function voice(): string;

    /** Pause entre deux demandes pendant la préparation, en secondes. */
    abstract public function spacing(): int;

    /** Coût estimé d'une minute de voix, en dollars US. */
    abstract public function costPerMinute(): float;

    /**
     * Fait dire un texte avec la voix de ce fournisseur. Renvoie un fichier WAV. Chaque échec est journalisé
     * avec ce fournisseur ; chaque réussite est comptée dans la consommation.
     *
     * @param  int|null  $timeout  délai d'attente en secondes
     *
     * @throws SpeechFailed
     */
    abstract public function synthesize(string $text, string $style, string $label = 'Synthèse vocale', ?int $timeout = null): string;
}
