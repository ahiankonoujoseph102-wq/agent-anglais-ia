<?php

namespace App\Models\Concerns;

use App\Support\Live\LiveProviders;
use App\Support\Live\OpenAiRealtime;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use Illuminate\Support\Carbon;

/**
 * Règles de temps communes à l'évaluation et aux séances (conversations vocales).
 *
 * Une conversation se déroule d'un seul tenant : le temps consommé est le temps
 * écoulé depuis son début, MOINS les coupures de connexion, plafonné à la durée
 * autorisée. Si la connexion est coupée, le temps de séance est mis en pause :
 * l'élève ne perd pas de minutes (signal explicite du navigateur, ou plus de
 * « live.disconnect_after_seconds » sans aucun signal ; au plus
 * « live.disconnect_pause_max_seconds » de coupure par séance). La fin autorisée,
 * les jetons et le raccrochage de l'appel OpenAI sont repoussés d'autant. Le
 * bouton « Pause » de l'élève n'est pas une coupure : le temps continue.
 *
 * Colonnes attendues : started_at, ended_at, allowed_seconds, used_seconds,
 * last_seen_at, token_expires_at, connections, input_tokens, output_tokens,
 * live_provider (gemini ou openai), live_call_id (appel OpenAI en cours),
 * paused_seconds (coupures déjà comptées), disconnected_at (coupure en cours), teacher (professeur de la séance).
 */
trait IsLiveConversation
{
    /** Séance abandonnée en cours de clôture : la coupure en cours ne compte plus. */
    protected bool $pauseClosed = false;

    /**
     * Chez OpenAI, le serveur raccroche lui-même l'appel dès que la conversation est close (limite de
     * temps, fin, refus du paiement, navigateur disparu) : la coupure ne dépend pas du navigateur.
     */
    public static function bootIsLiveConversation(): void
    {
        // Professeur de la séance, fixé à sa création (sa voix est figée dans le jeton) : celui donné par le contrôleur
        // (séance du jour, « Mes cours », bilan de niveau : celui de l'apprenant), sinon le professeur par défaut
        // (évaluation gratuite : toujours Teacher Joe pour l'instant).
        static::creating(function (self $conversation) {
            if (blank($conversation->getAttributes()['teacher'] ?? null)) {
                $conversation->setAttribute('teacher', Teachers::default()->key);
            }
        });

        static::updated(function (self $conversation) {
            $callId = $conversation->getAttributes()['live_call_id'] ?? null;

            if (filled($callId) && $conversation->wasChanged('ended_at') && $conversation->ended_at !== null) {
                app(OpenAiRealtime::class)->hangup((string) $callId);
            }
        });
    }

    /**
     * Professeur de la conversation (Teacher Joe pour les conversations d'avant les professeurs).
     */
    public function teacher(): Teacher
    {
        return Teachers::find($this->getAttributes()['teacher'] ?? null) ?? Teachers::default();
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
        return $this->started_at->copy()->addSeconds($this->allowed_seconds + $this->pausedSeconds() + $this->openPauseSeconds(now()));
    }

    /** Coupures déjà comptées, en secondes. */
    public function pausedSeconds(): int
    {
        return (int) ($this->getAttributes()['paused_seconds'] ?? 0);
    }

    /** Ce qui reste de coupure possible dans cette séance, en secondes. */
    public function pauseAllowance(): int
    {
        return max(0, (int) config('platform.live.disconnect_pause_max_seconds') - $this->pausedSeconds());
    }

    /**
     * Début de la coupure en cours : signalée par le navigateur, ou, sans signal explicite, son dernier signal
     * s'il se tait depuis plus de « disconnect_after_seconds » à l'instant donné (plus d'internet du tout).
     */
    public function pauseStart(?Carbon $at = null): ?Carbon
    {
        if ($this->pauseClosed || ! $this->isInProgress()) {
            return null;
        }

        if ($this->disconnected_at !== null) {
            return $this->disconnected_at;
        }

        $at ??= now();
        $lastSeen = $this->last_seen_at ?? $this->started_at;

        return $lastSeen->diffInSeconds($at, false) > (int) config('platform.live.disconnect_after_seconds') ? $lastSeen : null;
    }

    /** Durée de la coupure en cours jusqu'à l'instant donné (0 sans coupure), dans la limite permise. */
    public function openPauseSeconds(Carbon $at): int
    {
        $start = $this->pauseStart($at);

        if ($start === null) {
            return 0;
        }

        return max(0, min($this->pauseAllowance(), (int) floor($start->diffInSeconds($at, false))));
    }

    public function isDisconnected(): bool
    {
        return $this->pauseStart() !== null;
    }

    /**
     * La connexion avec le professeur est perdue (signal explicite du navigateur) : le temps est mis en pause.
     */
    public function markDisconnected(?Carbon $at = null): void
    {
        if ($this->isInProgress() && $this->pauseStart($at) === null && $this->pauseAllowance() > 0) {
            $this->disconnected_at = $at ?? now();
        }
    }

    /**
     * La connexion est revenue (signal du navigateur ou nouvelle connexion) : la coupure en cours, signalée ou
     * déduite d'un long silence, est ajoutée aux coupures comptées.
     */
    public function markReconnected(?Carbon $at = null): void
    {
        $at ??= now();

        if (! $this->isInProgress()) {
            return;
        }

        $this->paused_seconds = $this->pausedSeconds() + $this->openPauseSeconds($at);
        $this->disconnected_at = null;
        $this->last_seen_at = $at;
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
        // Les coupures de connexion ne comptent pas.
        $seconds = (int) floor($this->started_at->diffInSeconds($at, false)) - $this->pausedSeconds() - $this->openPauseSeconds($at);

        return max(0, min($this->allowed_seconds, $seconds));
    }

    /**
     * Le navigateur ne donne plus de nouvelles et son dernier jeton a expiré.
     */
    public function isStale(): bool
    {
        $lastSeen = $this->last_seen_at ?? $this->started_at;
        // Le navigateur peut revenir après une coupure (ce qui reste de coupure possible dans la séance) : la
        // séance n'est close qu'ensuite.
        $wait = (int) config('platform.live.stale_after_seconds') + $this->pauseAllowance();

        return $lastSeen->lt(now()->subSeconds($wait))
            && ($this->token_expires_at === null || $this->token_expires_at->isPast());
    }

    /**
     * Enregistre un signal du navigateur : temps consommé et jetons utilisés.
     * Les jetons arrivés après la clôture sont tout de même comptés.
     */
    /**
     * @param  array<string, int>  $details  jetons par nature, tours et plus grand contexte (SignalRequest::tokenDetails)
     */
    public function recordSignal(int $inputTokens = 0, int $outputTokens = 0, ?bool $connected = null, array $details = []): void
    {
        if ($this->isInProgress()) {
            // Connexion avec le professeur perdue ou revenue (signal du navigateur), ou long silence du navigateur.
            if ($connected === false) {
                $this->markDisconnected();
            } elseif ($connected === true || $this->disconnected_at === null) {
                $this->markReconnected();
            }

            $this->last_seen_at = now();
            $this->used_seconds = $this->elapsedUntil(now());
        }

        $this->input_tokens += $inputTokens;
        $this->output_tokens += $outputTokens;
        $this->addTokenDetails($details);
        $this->save();
    }

    /**
     * Ajoute le détail des jetons (texte, audio, cache), le nombre de tours et le plus grand contexte d'un tour.
     *
     * @param  array<string, int>  $details
     */
    private function addTokenDetails(array $details): void
    {
        foreach ($details as $field => $value) {
            match ($field) {
                'max_context_tokens' => $this->max_context_tokens = max((int) $this->max_context_tokens, $value),
                'turns' => $this->turns = (int) $this->turns + $value,
                default => $this->{$field} = (int) $this->{$field} + $value,
            };
        }
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
            // Le navigateur n'est jamais revenu : décompté jusqu'à son dernier signal ou à l'expiration du dernier
            // jeton (on ne parle pas sans être compté en coupant simplement les signaux). La coupure en cours ne
            // compte que si l'élève revient.
            $lastSeen = $this->last_seen_at ?? $this->started_at;
            $tokenEnd = $this->token_expires_at ?? $this->started_at;
            $this->disconnected_at = null;
            $this->pauseClosed = true;

            $this->finish($lastSeen->max($tokenEnd)->min($this->deadline()), interrupted: true);

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
