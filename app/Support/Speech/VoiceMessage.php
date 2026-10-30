<?php

namespace App\Support\Speech;

use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\VoiceSampler;
use App\Support\Teachers\Teacher;
use App\Support\Videos\VideoVoice;
use App\Support\Videos\VoiceSplitter;

/**
 * Un message vocal commun (message de bienvenue d'une formule, ou message du résultat d'un niveau) : ses
 * morceaux, et le fichier de chaque morceau chez chaque fournisseur. Un message est toujours joué avec UN
 * seul fournisseur et une seule voix (SpeechProviders::choose).
 */
final class VoiceMessage
{
    /**
     * @param  list<array{label: string, text: string}>  $pieces
     * @param  \Closure(string, SpeechProvider): string  $path  fichier d'un texte chez un fournisseur
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $pieces,
        public readonly string $style,
        private readonly \Closure $path,
        public readonly ?Teacher $teacher = null,
    ) {}

    /** Le fournisseur avec la voix du professeur de ce message (null : professeur par défaut). */
    public function bind(SpeechProvider $provider): SpeechProvider
    {
        return $provider->forTeacher($this->teacher);
    }

    public function path(string $text, SpeechProvider $provider): string
    {
        return ($this->path)($text, $this->bind($provider));
    }

    /**
     * Fichiers déjà gardés dans les dossiers de ce message, chez tous les fournisseurs.
     *
     * @return array<string, true>
     */
    public function files(): array
    {
        $directories = [];

        foreach (SpeechProviders::all($this->teacher) as $provider) {
            foreach ($this->pieces as $piece) {
                $directories[] = dirname($this->path($piece['text'], $provider));
            }
        }

        return SpeechFiles::index($directories);
    }

    /**
     * Morceaux encore absents chez ce fournisseur.
     *
     * @param  array<string, true>|null  $files
     * @return list<array{label: string, text: string}>
     */
    public function missing(SpeechProvider $provider, ?array $files = null): array
    {
        $files ??= $this->files();

        return array_values(array_filter($this->pieces, fn (array $piece) => ! isset($files[$this->path($piece['text'], $provider)])));
    }

    /**
     * @param  array<string, true>|null  $files
     */
    public function readyCount(SpeechProvider $provider, ?array $files = null): int
    {
        return count($this->pieces) - count($this->missing($provider, $files));
    }

    /**
     * @param  array<string, true>|null  $files
     */
    public function isCompleteAt(SpeechProvider $provider, ?array $files = null): bool
    {
        return $this->missing($provider, $files) === [];
    }

    /**
     * Le fournisseur qui jouera ce message.
     *
     * @param  array<string, true>|null  $files
     */
    public function provider(?array $files = null): ?SpeechProvider
    {
        return SpeechProviders::choose($this, $files);
    }

    /**
     * Génère et garde un morceau chez ce fournisseur. Renvoie le fichier WAV.
     *
     * @param  array{label: string, text: string}  $piece
     *
     * @throws SpeechFailed
     */
    public function prepare(array $piece, SpeechProvider $provider, ?int $timeout = null): string
    {
        $provider = $this->bind($provider);
        $wav = $provider->synthesize($piece['text'], $this->style, $piece['label'], $timeout);

        return VoiceSampler::store($this->path($piece['text'], $provider), $wav, $piece['label'], $provider);
    }

    /**
     * Génère plusieurs morceaux en UNE demande (moins de demandes : 10 par jour chez Gemini en offre gratuite), puis
     * recoupe l'audio au silence le plus proche entre deux morceaux (VoiceSplitter, comme les vidéos) ; chaque
     * morceau est gardé dans son propre fichier, exactement comme s'il avait été demandé seul.
     *
     * @param  list<array{label: string, text: string}>  $pieces
     *
     * @throws SpeechFailed
     */
    public function prepareGroup(array $pieces, SpeechProvider $provider, ?int $timeout = null): void
    {
        if (count($pieces) === 1) {
            $this->prepare($pieces[0], $provider, $timeout);

            return;
        }

        $provider = $this->bind($provider);
        $texts = array_map(fn (array $piece) => $piece['text'], $pieces);
        $label = $pieces[0]['label'].' et '.(count($pieces) - 1).' autre(s)';
        $wav = $provider->synthesize(VoiceSplitter::join($texts), $this->style, $label, $timeout);
        $parsed = VideoVoice::parse($wav);

        if ($parsed === null) {
            throw new SpeechFailed(SpeechFailed::UNREADABLE, 'Audio illisible : impossible de le recouper en paragraphes.');
        }

        foreach (VoiceSplitter::split($parsed, $texts) as $position => $data) {
            VoiceSampler::store($this->path($texts[$position], $provider), VideoVoice::wav($data, $parsed['rate'], $parsed['channels']), $pieces[$position]['label'], $provider);
        }
    }
}
