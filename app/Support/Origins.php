<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\User;
use App\Models\UserOrigin;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Provenance des inscrits, sans outil de mesure extérieur ni cookie publicitaire :
 * 1. à la première page vue par un VISITEUR (middleware RememberLanding), la session du site garde la page
 *    d'arrivée (chemin seulement), le nom de domaine du site d'origine (en-tête Referer, jamais l'adresse
 *    complète) et les paramètres utm_source / utm_medium / utm_campaign d'un lien partagé ;
 * 2. à l'inscription, ces informations sont copiées dans user_origins (une ligne par compte) ;
 * 3. l'administration (« Provenance ») compte, par canal et par page d'arrivée, les inscrits, ceux qui ont passé
 *    l'évaluation et ceux qui se sont abonnés (au moins un paiement validé).
 * Un visiteur qui ne s'inscrit pas ne laisse rien dans la base. Décrit sur la page « Confidentialité ».
 */
final class Origins
{
    public const SESSION = 'origin';

    /** Canaux, dans l'ordre d'affichage. */
    public const CHANNELS = [
        'google' => 'Google',
        'bing' => 'Bing',
        'whatsapp' => 'WhatsApp',
        'tiktok' => 'TikTok',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)',
        'other' => 'Autre site',
        'direct' => 'Accès direct ou inconnu',
    ];

    /** Mots qui désignent un canal, dans utm_source ou dans le nom de domaine (ou l'application Android) d'origine. */
    private const MARKERS = [
        'google' => ['google', 'googlequicksearchbox'],
        'bing' => ['bing'],
        'whatsapp' => ['whatsapp', 'wa.me'],
        'tiktok' => ['tiktok', 'musically', 'ss.android.ugc'],
        'facebook' => ['facebook', 'fb.com', 'fb.me', 'fbclid', 'messenger'],
        'instagram' => ['instagram'],
        'youtube' => ['youtube', 'youtu.be'],
        'linkedin' => ['linkedin', 'lnkd.in'],
        'x' => ['twitter', 't.co', 'x.com'],
    ];

    /** Routes qui ne sont pas des pages vues (fichiers, robots, vidéos). */
    private const IGNORED_ROUTES = ['seo.*', 'manifest', 'offline', 'how.poster', 'how.video', 'welcome.audio'];

    /**
     * Première page vue par un visiteur : gardée dans sa session (jamais remplacée ensuite).
     */
    public static function remember(Request $request): void
    {
        if (! $request->isMethod('GET') || $request->user() !== null || $request->expectsJson() || ! $request->hasSession()
            || $request->session()->has(self::SESSION) || $request->route() === null || $request->routeIs(...self::IGNORED_ROUTES)) {
            return;
        }

        $request->session()->put(self::SESSION, [
            'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 200),
            'referrer' => self::referrerHost($request),
            'utm_source' => self::param($request, 'utm_source'),
            'utm_medium' => self::param($request, 'utm_medium'),
            'utm_campaign' => self::param($request, 'utm_campaign'),
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * À l'inscription : copie de la provenance gardée en session (ou « accès direct ou inconnu » sans elle).
     *
     * @param  array<string, mixed>|null  $seen
     */
    public static function record(User $user, ?array $seen): UserOrigin
    {
        $seen = (array) $seen;

        return UserOrigin::query()->updateOrCreate(['user_id' => $user->id], [
            'landing_path' => $seen['path'] ?? null,
            'referrer_host' => $seen['referrer'] ?? null,
            'channel' => self::channel($seen['utm_source'] ?? null, $seen['referrer'] ?? null),
            'utm_source' => $seen['utm_source'] ?? null,
            'utm_medium' => $seen['utm_medium'] ?? null,
            'utm_campaign' => $seen['utm_campaign'] ?? null,
            'first_seen_at' => isset($seen['at']) ? rescue(fn () => now()->parse((string) $seen['at']), null, false) : null,
        ]);
    }

    /**
     * Canal d'une provenance : utm_source d'abord (lien partagé), sinon le site d'origine, sinon « direct ».
     */
    public static function channel(?string $source, ?string $host): string
    {
        foreach ([$source, $host] as $value) {
            $value = strtolower((string) $value);

            if ($value === '') {
                continue;
            }

            foreach (self::MARKERS as $channel => $markers) {
                foreach ($markers as $marker) {
                    if (str_contains($value, $marker)) {
                        return $channel;
                    }
                }
            }

            return 'other';
        }

        return 'direct';
    }

    public static function label(string $channel): string
    {
        return self::CHANNELS[$channel] ?? self::CHANNELS['other'];
    }

    /**
     * Chiffres de l'administration depuis une date (null : depuis toujours), d'après les inscrits qui ont une
     * provenance : par canal, par page d'arrivée, et les dernières inscriptions.
     *
     * @return array{total: int, channels: Collection<int, object>, pages: Collection<int, object>, recent: Collection<int, UserOrigin>}
     */
    public static function stats(?CarbonInterface $since): array
    {
        $base = fn () => DB::table('user_origins')
            ->join('users', 'users.id', '=', 'user_origins.user_id')
            ->leftJoin('assessments', function ($join) {
                $join->on('assessments.user_id', '=', 'users.id')->whereNotNull('assessments.level');
            })
            ->leftJoinSub(
                DB::table('payments')->select('user_id')->where('status', PaymentStatus::Validated->value)->groupBy('user_id'),
                'paid',
                'paid.user_id',
                '=',
                'users.id',
            )
            ->when($since, fn ($query) => $query->where('users.created_at', '>=', $since));

        $counts = 'COUNT(DISTINCT users.id) AS registered, COUNT(DISTINCT assessments.user_id) AS assessed, COUNT(DISTINCT paid.user_id) AS subscribed';

        $channels = $base()->selectRaw('user_origins.channel AS channel, '.$counts)->groupBy('user_origins.channel')->get()
            ->sortBy(fn ($row) => array_search($row->channel, array_keys(self::CHANNELS), true))->values();
        $pages = $base()->selectRaw('user_origins.landing_path AS path, '.$counts)->groupBy('user_origins.landing_path')
            ->orderByDesc('registered')->limit(20)->get();

        $recent = UserOrigin::query()
            ->with(['user' => fn ($query) => $query->withExists([
                'assessment as assessed' => fn ($assessment) => $assessment->whereNotNull('level'),
                'payments as subscribed' => fn ($payment) => $payment->where('status', PaymentStatus::Validated),
            ])])
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->latest()->limit(20)->get();

        return ['total' => (int) $channels->sum('registered'), 'channels' => $channels, 'pages' => $pages, 'recent' => $recent];
    }

    /** Nom de domaine du site d'origine (sans « www. »), ou de l'application Android ; null pour le site lui-même. */
    private static function referrerHost(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');
        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        if ($host === '' || $host === null) {
            return null;
        }

        $own = array_filter(array_map(
            fn ($value) => preg_replace('/^www\./', '', strtolower((string) $value)),
            [parse_url((string) config('app.url'), PHP_URL_HOST), $request->getHost(), ...(array) config('app.redirect_from_hosts')],
        ));

        return in_array($host, $own, true) ? null : mb_substr($host, 0, 120);
    }

    private static function param(Request $request, string $name): ?string
    {
        $value = strtolower(trim((string) $request->query($name, '')));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);

        return $value === '' || $value === '-' ? null : mb_substr($value, 0, 60);
    }
}
