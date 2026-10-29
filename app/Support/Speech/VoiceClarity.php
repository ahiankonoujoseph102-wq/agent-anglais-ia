<?php

namespace App\Support\Speech;

use App\Support\Gemini\VoiceSampler;
use App\Support\PrivateStorage;
use App\Support\Teachers\Teacher;
use App\Support\Text\GeminiText;
use App\Support\Text\TextFailed;
use App\Support\Text\TextRequest;
use App\Support\Text\TextUsage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Clarté d'une voix, mesurée sans oreille humaine : l'extrait du test en français et en anglais (« Essai des voix »)
 * est retranscrit par un modèle qui écoute (Gemini, sinon la transcription d'OpenAI), puis comparé mot à mot au
 * texte attendu : part des mots bien reconnus, en français et en anglais. Un mot déformé ou mal prononcé est mal
 * reconnu. Limite : un modèle de transcription corrige un peu ce qu'il entend ; un bon score ne remplace pas
 * l'écoute, un mauvais score signale une voix à écarter.
 */
final class VoiceClarity
{
    /** Repère qui sépare le français de l'anglais dans le texte du test (config teacher.voice_trial.bilingual_text). */
    public const ENGLISH_MARK = 'maintenant en anglais';

    public const INSTRUCTIONS = 'You are a strict speech-to-text transcriber. Write down EXACTLY the words you hear, in the language they are spoken (French, then English), word for word. Never correct, complete or guess a word: a badly pronounced or unclear word is written as you hear it. Write numbers as words, as spoken. Output only the transcript, nothing else.';

    /** Fichier du résultat, à côté de l'extrait (même empreinte : un nouveau texte ou une nouvelle voix, une nouvelle mesure). */
    public static function path(string $provider, string $voice, Teacher $teacher): string
    {
        return VoiceSampler::bilingualPath($provider, $voice, $teacher).'.clarity.json';
    }

    /**
     * @return array{french: int, english: int, total: int, transcript: string, by: string}|null
     */
    public static function result(string $provider, string $voice, Teacher $teacher): ?array
    {
        try {
            $disk = PrivateStorage::disk();
            $path = self::path($provider, $voice, $teacher);

            return $disk->exists($path) ? json_decode((string) $disk->get($path), true) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mesure la clarté d'un extrait déjà préparé et garde le résultat ; null si aucun transcripteur n'a répondu.
     *
     * @return array{french: int, english: int, total: int, transcript: string, by: string}|null
     */
    public function measure(string $provider, string $voice, Teacher $teacher): ?array
    {
        $wav = app(VoiceSampler::class)->cachedBilingual($provider, $voice, $teacher);

        if ($wav === null) {
            return null;
        }

        [$transcript, $by] = $this->transcribe($wav);

        if ($transcript === null) {
            return null;
        }

        $result = self::score(VoiceSampler::bilingualScript($teacher)['text'], $transcript) + ['transcript' => Str::limit($transcript, 2000), 'by' => $by];
        PrivateStorage::disk()->put(self::path($provider, $voice, $teacher), (string) json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result;
    }

    /**
     * Transcription : Gemini (modèle texte, l'extrait en entrée), sinon la transcription d'OpenAI.
     *
     * @return array{0: ?string, 1: string}
     */
    private function transcribe(string $wav): array
    {
        $request = new TextRequest(
            kind: 'clarity',
            instructions: self::INSTRUCTIONS,
            parts: [TextRequest::text('Transcribe this recording.'), TextRequest::audio($wav, 'audio/wav')],
            maxTokens: 800,
            timeout: 45,
            effort: 'minimal',
        );

        try {
            $result = app(GeminiText::class)->generate($request);
            TextUsage::record($result, $request);

            return [trim($result->text), 'gemini'];
        } catch (TextFailed) {
            // Essayé ensuite chez OpenAI.
        }

        $key = config('services.openai.key');

        if (blank($key)) {
            return [null, ''];
        }

        try {
            $response = Http::withToken((string) $key)
                ->timeout(45)
                ->attach('file', $wav, 'extrait.wav')
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/audio/transcriptions', [
                    'model' => (string) config('services.openai.realtime_transcription_model'),
                    'prompt' => 'Un extrait en français, puis en anglais. Transcris exactement les mots entendus, sans les corriger.',
                ]);
        } catch (ConnectionException) {
            return [null, ''];
        }

        $text = trim((string) $response->json('text', ''));

        return $response->successful() && $text !== '' ? [$text, 'openai'] : [null, ''];
    }

    /**
     * Part des mots attendus bien reconnus (alignement mot à mot), en français, en anglais et en tout, en pour cent.
     *
     * @return array{french: int, english: int, total: int}
     */
    public static function score(string $expected, string $heard): array
    {
        $words = self::words($expected);
        $heardWords = self::words($heard);

        // Le français s'arrête au repère « Maintenant, en anglais » (compté avec le français).
        $mark = self::words(self::ENGLISH_MARK);
        $split = count($words);

        for ($i = 0; $i + count($mark) <= count($words); $i++) {
            if (array_slice($words, $i, count($mark)) === $mark) {
                $split = $i + count($mark);
                break;
            }
        }

        $matched = self::matched($words, $heardWords);
        $count = fn (int $from, int $to) => $to > $from ? (int) round(100 * count(array_filter(array_slice($matched, $from, $to - $from))) / ($to - $from)) : 100;

        return [
            'french' => $count(0, $split),
            'english' => $count($split, count($words)),
            'total' => $count(0, count($words)),
        ];
    }

    /**
     * Mots en minuscules, sans accents ni ponctuation (« l'anglais » : « l », « anglais »).
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $text = Str::lower(Str::ascii($text));
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);

        return array_values(array_filter(explode(' ', $text), fn ($word) => $word !== ''));
    }

    /**
     * Pour chaque mot attendu : reconnu ou non, d'après l'alignement de moindre coût (distance d'édition par mots).
     *
     * @param  list<string>  $expected
     * @param  list<string>  $heard
     * @return list<bool>
     */
    private static function matched(array $expected, array $heard): array
    {
        $n = count($expected);
        $m = count($heard);
        $cost = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = 0; $i <= $n; $i++) {
            $cost[$i][0] = $i;
        }

        for ($j = 0; $j <= $m; $j++) {
            $cost[0][$j] = $j;
        }

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $same = $expected[$i - 1] === $heard[$j - 1] ? 0 : 1;
                $cost[$i][$j] = min($cost[$i - 1][$j] + 1, $cost[$i][$j - 1] + 1, $cost[$i - 1][$j - 1] + $same);
            }
        }

        $matched = array_fill(0, $n, false);
        [$i, $j] = [$n, $m];

        while ($i > 0 && $j > 0) {
            if ($expected[$i - 1] === $heard[$j - 1] && $cost[$i][$j] === $cost[$i - 1][$j - 1]) {
                $matched[$i - 1] = true;
                [$i, $j] = [$i - 1, $j - 1];
            } elseif ($cost[$i][$j] === $cost[$i - 1][$j - 1] + 1) {
                [$i, $j] = [$i - 1, $j - 1];
            } elseif ($cost[$i][$j] === $cost[$i - 1][$j] + 1) {
                $i--;
            } else {
                $j--;
            }
        }

        return $matched;
    }
}
