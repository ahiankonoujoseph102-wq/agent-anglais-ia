<?php

namespace App\Models\Concerns;

use App\Support\Live\LiveProviders;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Support\Carbon;

/**
 * Règles de temps communes à l'évaluation et aux séances (conversations vocales).
 *
 * Une conversation se déroule d'un seul tenant : le temps consommé est le temps
 * écoulé depuis son début, plafonné à la durée autorisée. Chaque instant pendant
 * lequel un jeton éphémère valide existait est décompté, ce qui empêche de
 * parler sans être compté en coupant simplement les signaux du navigateur.
 *
 * Colonnes attendues : started_at, ended_at, allowed_seconds, used_seconds,
 * last_seen_at, token_expires_at, connections, input_tokens, output_tokens,
 * live_provider (gemini ou openai), live_call_id (appel OpenAI en cours).
 */
trait IsLiveConversation
{
    /**
     * Chez OpenAI, le serveur raccroche lui-même l'appel dès que la conversation est close (limite de
     * temps, fin, refus du paiement, navigateur disparu) : la coupure ne dépend pas du navigateur.
     */
    public static function bootIsLiveConversation(): void
    {
        static::updated(function (self $conversation) {
            $callId = $conversation->getAttributes()['live_call_id'] ?? null;

            if (filled($callId) && $conversation->wasChanged('ended_at') && $conversation->ended_at !== null) {
                app(OpenAiRealtime::class)->hangup((string) $callId);
            }
        });
    }

    /**
     * Fournisseur de la conversation (Gemini pour les conversations d'avant le secours).
     */
    public function liveProvider(): string
    {
        return (string) ($this->getAttributes()['live_provider'] ?? LiveProviders::GEMINI);
    }

    /**
     * La conversation peut-elle encore commencer chez un autre fournisseur ? Seulement juste après son début,
     * quand le navigateur n'a pas pu se connecter (aucune parole échangée) : jamais en cours de séance.
     */
    public function canSwitchProvider(): bool
    {
        return $this->isInProgress()
            && $this->connections <= 2
            && $this->started_at->gt(now()->subSeconds((int) config('services.live.switch_window_seconds')));
    }

    abstract public function isInProgress(): bool;

    /**
     * Clôture la conversation au moment indiqué (fin normale ou interruption).
     */
    abstract public function finish(Carbon $at, bool $interrupted = false): void;

    /**
     * Instant où la conversation doit s'arrêter au plus tard.
     */
    public function deadline(): Carbon
    {
        return $this->started_at->copy()->addSeconds($this->allowed_seconds);
    }

    public function remainingSeconds(): int
    {
        if (! $this->isInProgress()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->deadline(), false));
    }

    /**
     * Secondes écoulées entre le début et l'instant donné, dans les limites autorisées.
     */
    public function elapsedUntil(Carbon $at): int
    {
        $seconds = (int) floor($this->started_at->diffInSeconds($at, false));

        return max(0, min($this->allowed_seconds, $seconds));
    }

    /**
     * Le navigateur ne donne plus de nouvelles et son dernier jeton a expiré.
     */
    public function isStale(): bool
    {
        $lastSeen = $this->last_seen_at ?? $this->started_at;

        return $lastSeen->lt(now()->subSeconds((int) config('platform.live.stale_after_seconds')))
            && ($this->token_expires_at === null || $this->token_expires_at->isPast());
    }

    /**
     * Enregistre un signal du navigateur : temps consommé et jetons utilisés.
     * Les jetons arrivés après la clôture sont tout de même comptés.
     */
    public function recordSignal(int $inputTokens = 0, int $outputTokens = 0): void
    {
        if ($this->isInProgress()) {
            $this->last_seen_at = now();
            $this->used_seconds = $this->elapsedUntil(now());
        }

        $this->input_tokens += $inputTokens;
        $this->output_tokens += $outputTokens;
        $this->save();
    }

    /**
     * Clôture la conversation si le temps est écoulé ou si le navigateur a disparu.
     * Retourne true si la conversation est encore ouverte.
     */
    public function closeIfOver(): bool
    {
        if (! $this->isInProgress()) {
            return false;
        }

        if (! $this->deadline()->isFuture()) {
            $this->finish($this->deadline());

            return false;
        }

        if ($this->isStale()) {
            // Décompté jusqu'au dernier signal ou à l'expiration du dernier jeton.
            $lastSeen = $this->last_seen_at ?? $this->started_at;
            $tokenEnd = $this->token_expires_at ?? $this->started_at;

            $this->finish($lastSeen->max($tokenEnd), interrupted: true);

            return false;
        }

        return true;
    }

    /**
     * Le serveur peut-il encore délivrer un jeton pour cette conversation ?
     */
    public function canConnectAgain(): bool
    {
        return $this->connections < (int) config('services.gemini.max_connections');
    }
}
