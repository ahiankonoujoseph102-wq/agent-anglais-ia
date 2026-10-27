<?php

namespace App\Support\Live;

use App\Enums\Role;
use App\Models\ConversationLine;
use App\Models\User;
use App\Support\Duration;
use Illuminate\Support\Carbon;

/**
 * Export ANONYMISÉ du texte des conversations, pour analyser et améliorer Teacher Joe (page « Consommation »,
 * bouton « Télécharger l'export anonymisé »). Un fichier texte lisible : chaque conversation numérotée dans
 * l'export (jamais l'identifiant du compte), son type, son niveau, son verdict, la date du jour seulement, puis
 * les répliques nettoyées par TranscriptAnonymizer, avec le délai de réponse du professeur. Comptes
 * d'administrateur exclus. Rien n'est gardé : le fichier est fabriqué à la demande et envoyé tout de suite.
 */
final class TranscriptExport
{
    /** Périodes proposées, en jours. */
    public const PERIODS = [7, 30, 90];

    /** Conversations au plus dans un export (les plus récentes). */
    public const MAX_CONVERSATIONS = 300;

    public static function build(int $days): string
    {
        $since = now()->subDays($days);
        $teacher = (string) config('avatar.model.name');

        // Conversations récentes ayant du texte, d'apprenants seulement, les plus récentes d'abord.
        $keys = ConversationLine::query()
            ->whereIn('user_id', User::query()->where('role', Role::Learner)->select('id'))
            ->where('created_at', '>=', $since)
            ->selectRaw('conversation_type, conversation_id, user_id, MIN(spoken_at) as first_at')
            ->groupBy('conversation_type', 'conversation_id', 'user_id')
            ->orderByDesc('first_at')
            ->limit(self::MAX_CONVERSATIONS)
            ->get();

        $users = User::query()->whereIn('id', $keys->pluck('user_id')->unique())->get()->keyBy('id');

        $blocks = [];
        $counts = array_fill_keys(array_keys(Transcripts::TYPES), 0);

        foreach ($keys->values() as $index => $key) {
            $class = Transcripts::TYPES[$key->conversation_type] ?? null;
            $conversation = $class ? $class::query()->find($key->conversation_id) : null;
            $user = $users->get($key->user_id);

            if ($conversation === null || $user === null) {
                continue;
            }

            $counts[$key->conversation_type]++;
            $names = array_filter([$user->name, $user->email ? strstr($user->email, '@', true) : null]);

            $head = 'Conversation '.($index + 1).' · '.Transcripts::label($key->conversation_type)
                .' · '.Carbon::parse($key->first_at)->timezone(config('app.timezone'))->format('Y-m-d')
                .($user->level ? ' · niveau du compte '.$user->level->value : '')
                .(($verdict = Transcripts::verdict($conversation)) ? ' · '.$verdict : '')
                .($conversation->live_provider ? ' · fournisseur '.LiveProviders::label($conversation->live_provider) : '')
                .($conversation->used_seconds ? ' · durée '.Duration::format((int) $conversation->used_seconds) : '');

            $lines = Transcripts::lines($conversation)->map(function (ConversationLine $line) use ($names, $teacher) {
                $delay = $line->delayMs();

                return ($line->isLearner() ? 'Élève' : $teacher).' : '
                    .TranscriptAnonymizer::clean(trim($line->text), $names)
                    .($delay !== null ? ' [répond en '.Transcripts::seconds($delay).']' : '');
            });

            $blocks[] = $head."\n".$lines->implode("\n");
        }

        $summary = collect($counts)->filter()->map(fn ($count, $type) => Transcripts::label($type).' : '.$count)->implode(' · ');

        return implode("\n", [
            'Conversations de '.$teacher.', export anonymisé ('.config('platform.name').')',
            'Période : les '.$days.' derniers jours (au plus '.self::MAX_CONVERSATIONS.' conversations, les plus récentes). Fait le '.now()->timezone(config('app.timezone'))->format('Y-m-d').'.',
            'Anonymisation : prénoms et noms du compte, numéros, adresses e-mail et liens remplacés par '
                .TranscriptAnonymizer::NAME.', '.TranscriptAnonymizer::NUMBER.', '.TranscriptAnonymizer::EMAIL.', '.TranscriptAnonymizer::LINK.'. '
                .'Les autres noms propres dits pendant une séance ne sont pas reconnus : relisez le fichier avant de le partager.',
            $summary !== '' ? $summary : 'Aucune conversation sur cette période.',
            '',
            implode("\n\n", $blocks),
        ])."\n";
    }
}
