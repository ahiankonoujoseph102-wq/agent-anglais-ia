<?php

namespace App\Support\Videos;

use App\Enums\Role;
use App\Models\CourseSession;
use App\Models\LearnerError;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Models\User;
use App\Support\Greeting;
use App\Support\SubscribersClub;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Vidéo quotidienne du Club des abonnés (groupe WhatsApp des abonnés payants) : UNE par jour, thème selon le jour de
 * la semaine (config videos.club.themes), en plus des vidéos de promotion. Même chemin que les vidéos de leçon (table
 * lesson_videos, kind « club » : script, voix, fabrication par GitHub, en priorité), mais un script écrit pour les
 * abonnés (ClubScriptWriter) et aucune fin publicitaire. Rien n'est publié par le site : l'équipe télécharge la vidéo
 * et copie le texte dans la page « Vidéos » (bloc « Club des abonnés », visible aussi par un assistant).
 *
 * Anonymat : une erreur n'est retenue que si au moins « min_people » personnes différentes l'ont faite, jamais avec un
 * nom ni une phrase telle qu'elle a été dite ; les progrès ne sont que des chiffres globaux. Pas de répétition : un
 * élément déjà présenté (expression, son, dialogue, erreur, piège) n'est pas repris avant « repeat_weeks » semaines.
 */
class ClubVideos
{
    public function __construct(private ClubScriptWriter $writer) {}

    /** Abonnés payants actifs (mêmes règles que le lien du groupe : SubscribersClub::isMember). */
    public function members(): Collection
    {
        return User::query()
            ->where('role', Role::Learner)
            ->whereHas('activeSubscription')
            ->with('activeSubscription')
            ->get()
            ->filter(fn (User $user) => SubscribersClub::isMember($user))
            ->values();
    }

    /** Thème de ce jour : ['key' => …, 'label' => …]. */
    public static function theme(CarbonInterface $day): array
    {
        return config('videos.club.themes')[$day->dayOfWeekIso];
    }

    /** Vidéo du Club de ce jour, ou null. */
    public function forDay(CarbonInterface $day): ?LessonVideo
    {
        return LessonVideo::query()->club()->whereDate('day', $day)->first();
    }

    /**
     * Ajoute la vidéo du Club d'aujourd'hui si elle manque (au moins un abonné payant actif). Renvoie la vidéo créée,
     * ou null (déjà là, ou aucun abonné).
     */
    public function ensureToday(): ?LessonVideo
    {
        if ($this->forDay(today()) !== null || $this->members()->isEmpty()) {
            return null;
        }

        $theme = self::theme(today());

        try {
            return LessonVideo::query()->create([
                'kind' => LessonVideo::CLUB,
                'theme' => $theme['key'],
                'topic_key' => 'club-'.today()->format('Y-m-d'),
                'day' => today()->toDateString(),
                'slot' => 0,
                'level' => (string) config('videos.club.level'),
                'title' => $theme['label'],
                'status' => LessonVideo::PLANNED,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** Dernières vidéos du Club, la plus récente d'abord. */
    public function recent(int $count = 7): Collection
    {
        return LessonVideo::query()->club()->orderByDesc('day')->limit($count)->get();
    }

    /**
     * Écrit le script : données du thème, script du modèle texte, éléments gardés dans l'historique. Faux si aucun
     * fournisseur n'a donné un script utilisable (nouvel essai plus tard).
     */
    public function writeScript(LessonVideo $video): bool
    {
        $brief = $this->brief($video);
        $written = $this->writer->write($brief, $this->firstNames());

        if ($written === null) {
            $attempts = $video->attempts + 1;
            $video->update([
                'attempts' => $attempts,
                'error' => 'Script du Club : aucun fournisseur du modèle texte n\'a répondu correctement.',
                'next_attempt_at' => now()->addMinutes((int) config('videos.retry_minutes')),
                'status' => $attempts >= (int) config('videos.max_attempts') ? LessonVideo::FAILED : LessonVideo::PLANNED,
            ]);

            return false;
        }

        $script = $written['script'];
        $video->update([
            'status' => LessonVideo::SCRIPTED,
            'script' => $script,
            'title' => $script['title'],
            'caption' => ClubScriptWriter::publication($script['caption']),
            'hashtags' => null,
            'items' => $brief['items'],
            'theme' => $brief['theme'],
            'text_provider' => $written['provider'],
            'attempts' => 0,
            'error' => null,
            'next_attempt_at' => null,
        ]);

        return true;
    }

    /**
     * Ce que la vidéo doit traiter : thème (repli honnête du lundi : « pitfall »), consigne, données et éléments de
     * l'historique.
     *
     * @return array{theme: string, data: string, items: list<array{type: string, key: string, text: string}>, figures: string|null}
     */
    public function brief(LessonVideo $video): array
    {
        $day = $video->day;
        $used = $this->used($video);

        return match ($video->theme) {
            'errors' => $this->errorsBrief($used),
            'expression' => $this->catalogBrief('expression', 'expressions', $used, fn ($item) => $item['en'].' — '.$item['fr']),
            'sound' => $this->catalogBrief('sound', 'sounds', $used, fn ($item) => $item['label'].' (mots : '.implode(', ', $item['words']).')'),
            'dialogue' => $this->catalogBrief('dialogue', 'situations', $used, fn ($item) => $item['label']),
            'challenge' => $this->challengeBrief($day),
            'progress' => $this->progressBrief($day),
            default => $this->revisionBrief($day),
        };
    }

    /**
     * Éléments déjà présentés depuis « repeat_weeks » semaines : « type:clé » => dernier jour. Les vidéos en échec ne
     * comptent pas (rien n'a été montré).
     *
     * @return array<string, string>
     */
    public function used(?LessonVideo $except = null): array
    {
        $since = today()->subWeeks((int) config('videos.club.repeat_weeks'));
        $used = [];

        $videos = LessonVideo::query()->club()
            ->whereDate('day', '>=', $since)
            ->where('status', '!=', LessonVideo::FAILED)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))
            ->orderBy('day')
            ->get();

        foreach ($videos as $video) {
            foreach ($video->items ?? [] as $item) {
                $used[$item['type'].':'.$item['key']] = $video->day->format('Y-m-d');
            }
        }

        return $used;
    }

    /** @param array<string, string> $used */
    private function catalogBrief(string $theme, string $catalog, array $used, callable $describe): array
    {
        $prefix = $theme.':';
        $usedKeys = [];

        foreach ($used as $key => $date) {
            if (str_starts_with($key, $prefix)) {
                $usedKeys[substr($key, strlen($prefix))] = $date;
            }
        }

        $item = ClubCatalog::next(ClubCatalog::all($catalog), $usedKeys);
        $text = $describe($item);

        return ['theme' => $theme, 'data' => $text, 'items' => [['type' => $theme, 'key' => $item['key'], 'text' => $text]], 'figures' => null];
    }

    /**
     * Lundi : erreurs faites par au moins « min_people » abonnés différents (regroupées par le modèle texte, comptées
     * par le code), sinon un piège des francophones présenté comme tel.
     *
     * @param  array<string, string>  $used
     */
    private function errorsBrief(array $used): array
    {
        $members = $this->members();
        $errors = LearnerError::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('created_at', '>=', now()->subDays((int) config('videos.club.errors_days')))
            ->latest()
            ->limit(300)
            ->get(['user_id', 'wrong', 'correct', 'explanation']);

        $recentLabels = [];

        foreach ($used as $key => $date) {
            if (str_starts_with($key, 'error:')) {
                $recentLabels[] = substr($key, 6);
            }
        }

        $groups = $errors->pluck('user_id')->unique()->count() >= (int) config('videos.club.min_people')
            ? $this->writer->groupErrors($errors, $recentLabels, $this->firstNames())
            : [];

        if ($groups !== []) {
            $lines = [];
            $items = [];

            foreach ($groups as $group) {
                $lines[] = '- '.$group['label'].' : on entend « '.$group['wrong_example'].' » ; la bonne forme : « '.$group['right_example'].' » ; pourquoi : '.$group['rule'];
                $items[] = ['type' => 'error', 'key' => ClubScriptWriter::labelKey($group['label']), 'text' => $group['label'].' : « '.$group['right_example'].' »'];
            }

            return ['theme' => 'errors', 'data' => implode("\n", $lines), 'items' => $items, 'figures' => null];
        }

        $usedKeys = [];

        foreach ($used as $key => $date) {
            if (str_starts_with($key, 'pitfall:')) {
                $usedKeys[substr($key, 8)] = $date;
            }
        }

        $pitfall = ClubCatalog::next(ClubCatalog::pitfalls(), $usedKeys);

        return ['theme' => 'pitfall', 'data' => $pitfall['text'], 'items' => [['type' => 'pitfall', 'key' => $pitfall['key'], 'text' => $pitfall['text']]], 'figures' => null];
    }

    /** Vendredi : un défi à relever en message vocal, à partir de ce que le Club a vu cette semaine. */
    private function challengeBrief(CarbonInterface $day): array
    {
        $week = $this->weekItems($day);
        $recent = LessonVideo::query()->club()->where('theme', 'challenge')
            ->whereDate('day', '>=', today()->subWeeks((int) config('videos.club.repeat_weeks')))
            ->whereDate('day', '<', $day)
            ->pluck('title')->all();

        $data = 'CE QUE LE CLUB A VU CETTE SEMAINE :'."\n".($week !== [] ? '- '.implode("\n- ", $week) : '- (rien encore : choisissez une situation de la vie quotidienne au Togo)');

        if ($recent !== []) {
            $data .= "\n\nDÉFIS DÉJÀ DONNÉS (ne pas les refaire) :\n- ".implode("\n- ", $recent);
        }

        return ['theme' => 'challenge', 'data' => $data, 'items' => [['type' => 'challenge', 'key' => $day->format('o-W'), 'text' => 'Défi de la semaine']], 'figures' => null];
    }

    /**
     * Samedi : minutes parlées et séances du Club sur les derniers jours, chiffres globaux seulement, et seulement si
     * au moins « min_people » abonnés ont pratiqué (sinon : un encouragement sans chiffre).
     */
    private function progressBrief(CarbonInterface $day): array
    {
        $stats = $this->progress($day);
        $figures = null;

        if ($stats['people'] >= (int) config('videos.club.min_people') && $stats['sessions'] > 0) {
            $figures = strtr((string) config('videos.club.progress_sentence'), [
                ':days' => (string) config('videos.club.stats_days'),
                ':minutes' => (string) $stats['minutes'],
                ':sessions' => (string) $stats['sessions'],
            ]);
        }

        return ['theme' => 'progress', 'data' => $figures !== null ? 'Les chiffres sont dits juste avant votre texte.' : 'Pas de chiffres cette semaine : encouragez sans aucun chiffre.', 'items' => [['type' => 'progress', 'key' => $day->format('o-W'), 'text' => 'Progrès du Club']], 'figures' => $figures];
    }

    /**
     * Minutes parlées, séances et nombre de personnes (jamais qui) du Club sur « stats_days » jours.
     *
     * @return array{minutes: int, sessions: int, people: int}
     */
    public function progress(CarbonInterface $day): array
    {
        $ids = $this->members()->pluck('id');
        $from = $day->copy()->subDays((int) config('videos.club.stats_days'))->toDateString();
        $to = $day->copy()->subDay()->toDateString();
        $min = (int) config('videos.club.min_session_seconds');
        $seconds = 0;
        $sessions = 0;
        $people = collect();

        foreach ([Lesson::class, CourseSession::class] as $model) {
            $rows = $model::query()->whereIn('user_id', $ids)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->where('used_seconds', '>=', $min)->get(['user_id', 'used_seconds']);
            $seconds += (int) $rows->sum('used_seconds');
            $sessions += $rows->count();
            $people = $people->merge($rows->pluck('user_id'));
        }

        return ['minutes' => intdiv($seconds, 60), 'sessions' => $sessions, 'people' => $people->unique()->count()];
    }

    /** Dimanche : révision de ce que le Club a vu cette semaine (sinon des vidéos de leçon récentes). */
    private function revisionBrief(CarbonInterface $day): array
    {
        $week = $this->weekItems($day);

        if ($week === []) {
            $week = LessonVideo::query()->promo()->where('status', LessonVideo::READY)
                ->whereDate('day', '>=', $day->copy()->subDays(7))->orderBy('day')->pluck('title')->all();
        }

        if ($week === []) {
            $week = array_map(fn ($item) => $item['en'].' — '.$item['fr'], array_slice(ClubCatalog::all('expressions'), 0, 3));
        }

        return ['theme' => 'revision', 'data' => 'POINTS À RÉVISER :'."\n- ".implode("\n- ", $week), 'items' => [['type' => 'revision', 'key' => $day->format('o-W'), 'text' => 'Révision de la semaine']], 'figures' => null];
    }

    /**
     * Ce que le Club a vu cette semaine (du lundi à la veille) : expressions, sons, dialogues, erreurs.
     *
     * @return list<string>
     */
    private function weekItems(CarbonInterface $day): array
    {
        $texts = [];
        $videos = LessonVideo::query()->club()
            ->whereDate('day', '>=', $day->copy()->startOfWeek())
            ->whereDate('day', '<', $day)
            ->where('status', '!=', LessonVideo::FAILED)
            ->orderBy('day')->get();

        foreach ($videos as $video) {
            foreach ($video->items ?? [] as $item) {
                if (in_array($item['type'], ['expression', 'sound', 'dialogue', 'error', 'pitfall'], true)) {
                    $texts[] = $item['text'];
                }
            }
        }

        return $texts;
    }

    /**
     * Prénoms des abonnés (jamais envoyés au modèle : le script qui en contient un est refusé).
     *
     * @return list<string>
     */
    private function firstNames(): array
    {
        return $this->members()->map(fn (User $user) => Greeting::firstName($user->name))->filter(fn ($name) => mb_strlen((string) $name) >= 3)->values()->all();
    }
}
