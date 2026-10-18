<?php

namespace App\Support\Text;

/**
 * Un fournisseur de modèle texte (lecture d'images et de PDF, réponse JSON imposée, rédaction).
 */
interface TextProvider
{
    /** Nom court, celui de TEXT_PROVIDERS (« gemini », « openai »). */
    public function name(): string;

    /** Nom affiché dans l'administration (« Gemini », « OpenAI »). */
    public function label(): string;

    /** La clé est-elle réglée ? Sans clé, le fournisseur n'est jamais appelé. */
    public function hasKey(): bool;

    public function model(): string;

    /** Prix estimés en dollars US par million de mots-unités (entrée, sortie). */
    public function inputPrice(): float;

    public function outputPrice(): float;

    /**
     * @throws TextFailed
     */
    public function generate(TextRequest $request): TextResult;
}
