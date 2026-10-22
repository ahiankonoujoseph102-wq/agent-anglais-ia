<?php

namespace App\Support\Videos;

use App\Models\LessonVideo;
use App\Support\Seo;
use App\Support\Text\TextProviders;
use App\Support\Text\TextRequest;
use App\Support\Text\TextResult;

/**
 * Écrit le script d'une vidéo de leçon (modèle texte : Gemini, puis OpenAI en secours, mêmes consignes et même
 * format imposé). La phrase de fin n'est jamais écrite par le modèle : elle est ajoutée telle quelle (config videos.closing).
 */
class VideoScriptWriter
{
    public const INSTRUCTIONS = <<<'TXT'
        You write the script of a short VERTICAL video (TikTok, WhatsApp channel) in which :teacher, the English teacher of :platform, teaches ONE useful English point to French-speaking Africans, beginners or intermediate (level :level). The teacher speaks alone, facing the camera.

        TOPIC (one topic only): :title
        WHAT TO SHOW: :angle

        LENGTH: about :words spoken words in total (about :seconds seconds), split into 6 to 10 short segments.

        LANGUAGE:
        - Explanations in simple, warm, spoken FRENCH, addressed to the viewer with « vous ».
        - Every example, word and sentence to learn in AMERICAN English (apartment, vacation, color…), short and useful in real life.
        - Situations in Togo (Lomé, Kara, the market, a zémidjan, the office, the university) or international WITHOUT naming any other country.

        STRUCTURE:
        1. A hook: a short question or a surprising fact (one sentence).
        2. The point, step by step: at most 3 key words or sentences. Each one: say it alone, give its meaning in French, then use it in a short example.
        3. At least one « Répétez après moi : … » with the English sentence, then « À vous ! ».
        4. ONE frequent mistake of French speakers, by contrast: « En français, on dit …, mais en anglais, on dit … ».
        5. A tiny exercise for the viewer (« À vous : comment dit-on … ? ») and its answer in the next segment.
        6. A one-sentence recap. Do NOT write any goodbye or call to action: the ending is added after your text.

        EACH SEGMENT:
        - "spoken": exactly what the teacher says (French with the English parts inside), written for the ear: short sentences, no lists, no emojis, no symbols, no brackets.
        - "subtitle": the same text, shortened if needed for the screen (at most 120 characters), with every English word or sentence wrapped in [en]…[/en]. Example: « On dit [en]I'm twenty-five[/en], pas [en]I have twenty-five years[/en]. »

        ALSO WRITE:
        - "title": the video title in French, 60 characters at most.
        - "caption": the publication text in French, 2 short sentences that make people want to watch (no hashtags in it).
        - "hashtags": 5 to 8 hashtags without spaces (French and English learning hashtags, like #anglais #apprendrelanglais #englishlesson).

        RULES: never invent a figure or a promise of result; never name any company, brand, application or artificial intelligence; never use the words « certificat » or « diplôme »; be accurate: every English sentence must be correct, natural American English.
        TXT;

    /** Mots interdits dans un script (marques, promesses, vocabulaire réservé). */
    private const FORBIDDEN = '/\b(gemini|openai|chatgpt|google|tiktok|whatsapp|certificat|diplôme|diplome|ghana|accra|aflao|nigeria|france)\b/iu';

    /**
     * Script nettoyé et fournisseur, ou null si aucun fournisseur n'a donné un script utilisable.
     *
     * @return array{script: array<string, mixed>, provider: string}|null
     */
    public function write(LessonVideo $video, int $timeout = 45): ?array
    {
        $topic = VideoTopics::find($video->topic_key) ?? ['title' => $video->title, 'angle' => '', 'level' => $video->level];
        $seconds = (int) config('videos.duration_seconds');
        $words = (int) round($seconds * (float) config('videos.words_per_second'));

        $instructions = strtr(self::INSTRUCTIONS, [
            ':teacher' => (string) config('avatar.model.name'),
            ':platform' => (string) config('platform.name'),
            ':level' => $topic['level'],
            ':title' => $topic['title'],
            ':angle' => $topic['angle'],
            ':words' => (string) $words,
            ':seconds' => (string) $seconds,
        ]);

        $result = TextProviders::generate(new TextRequest(
            kind: 'video',
            instructions: $instructions,
            parts: [TextRequest::text('Write the script now.')],
            schema: self::schema(),
            schemaName: 'video_script',
            maxTokens: 3000,
            timeout: $timeout,
            effort: 'low',
        ), accept: fn (TextResult $result) => ($data = $result->json()) !== null && self::normalize($data, $words) !== null);

        if ($result === null) {
            return null;
        }

        return ['script' => self::normalize($result->json(), $words), 'provider' => $result->provider];
    }

    /**
     * Script nettoyé, la phrase de fin ajoutée, ou null s'il est inutilisable (trop court, trop long, mot interdit).
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, segments: list<array{spoken: string, subtitle: string, closing?: bool}>, caption: string, hashtags: list<string>}|null
     */
    public static function normalize(array $data, int $words): ?array
    {
        $text = fn ($value, int $max) => is_string($value) && trim($value) !== '' ? mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $max) : null;

        $segments = [];

        foreach (is_array($data['segments'] ?? null) ? array_values($data['segments']) : [] as $item) {
            if (count($segments) >= 14 || ! is_array($item) || ($spoken = $text($item['spoken'] ?? null, 700)) === null) {
                continue;
            }

            $spoken = trim(str_replace(['[en]', '[/en]', '*', '#', '"'], ['', '', '', '', ''], $spoken));
            $subtitle = self::cleanSubtitle($text($item['subtitle'] ?? null, 400) ?? $spoken);
            $segments[] = ['spoken' => $spoken, 'subtitle' => $subtitle];
        }

        $title = $text($data['title'] ?? null, 80);
        $caption = $text($data['caption'] ?? null, 400);
        $count = array_sum(array_map(fn (array $segment) => count(preg_split('/\s+/u', $segment['spoken']) ?: []), $segments));
        $all = implode(' ', array_merge([$title, $caption], array_column($segments, 'spoken')));

        if ($title === null || $caption === null || count($segments) < 4 || $count < $words * 0.6 || $count > $words * 1.5 || preg_match(self::FORBIDDEN, $all)) {
            return null;
        }

        $hashtags = [];

        foreach (is_array($data['hashtags'] ?? null) ? $data['hashtags'] : [] as $tag) {
            $tag = preg_replace('/[^\p{L}\p{N}_]/u', '', (string) $tag);

            if ($tag !== '' && count($hashtags) < 8 && ! in_array('#'.$tag, $hashtags, true) && ! preg_match(self::FORBIDDEN, $tag)) {
                $hashtags[] = '#'.$tag;
            }
        }

        $segments[] = ['spoken' => self::closing(spoken: true), 'subtitle' => self::closing(), 'closing' => true];

        return ['title' => $title, 'segments' => $segments, 'caption' => $caption, 'hashtags' => $hashtags];
    }

    /** Adresse du site, sans « https:// » (talkami.laravel.cloud). */
    public static function site(): string
    {
        return (string) (parse_url(Seo::url(), PHP_URL_HOST) ?: Seo::url());
    }

    /** Phrase de fin, affichée (adresse telle quelle) ou dite (« talkami point laravel point cloud »). */
    public static function closing(bool $spoken = false): string
    {
        $site = self::site();

        return str_replace(':site', $spoken ? str_replace('.', ' point ', $site) : $site, (string) config('videos.closing')).'.';
    }

    /** Texte de publication complet : légende, appel à l'évaluation gratuite, hashtags. */
    public static function publication(string $caption, array $hashtags): string
    {
        return trim($caption."\n\n".self::closing()."\n\n".implode(' ', $hashtags));
    }

    /** Balises [en]…[/en] équilibrées, rien d'autre entre crochets. */
    public static function cleanSubtitle(string $subtitle): string
    {
        $subtitle = preg_replace('/\[(?!\/?en\])[^\]]*\]/u', '', $subtitle) ?? $subtitle;
        $out = '';
        $open = false;

        foreach (preg_split('/(\[\/?en\])/u', $subtitle, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            if ($piece === '[en]' || $piece === '[/en]') {
                $wantOpen = $piece === '[en]';

                if ($wantOpen !== $open) {
                    $out .= $piece;
                    $open = $wantOpen;
                }

                continue;
            }

            $out .= $piece;
        }

        return trim($open ? $out.'[/en]' : $out);
    }

    /**
     * Format imposé de la réponse (le même chez les deux fournisseurs ; tous les champs sont obligatoires).
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $string = fn (string $description) => ['type' => 'string', 'description' => $description];

        return [
            'type' => 'object',
            'properties' => [
                'title' => $string('French video title, 60 characters at most.'),
                'segments' => [
                    'type' => 'array',
                    'description' => '6 to 10 short segments, in order.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'spoken' => $string('Exactly what the teacher says (French with the English parts inside).'),
                            'subtitle' => $string('Screen text (120 characters at most), English parts wrapped in [en]…[/en].'),
                        ],
                        'required' => ['spoken', 'subtitle'],
                    ],
                ],
                'caption' => $string('French publication text, 2 short sentences, no hashtags.'),
                'hashtags' => ['type' => 'array', 'description' => '5 to 8 hashtags.', 'items' => ['type' => 'string']],
            ],
            'required' => ['title', 'segments', 'caption', 'hashtags'],
        ];
    }
}
