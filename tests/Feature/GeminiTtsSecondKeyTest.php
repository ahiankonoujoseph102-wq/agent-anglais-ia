<?php

namespace Tests\Feature;

use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\SpeechLog;
use App\Support\Gemini\VoiceSampler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Synthèse vocale Gemini (extraits de « Essai des voix », messages, vidéos) : la deuxième clé (GEMINI_API_KEY_2,
 * autre projet Google, autre limite de demandes par jour) prend le relais de la clé principale. Jamais la clé dans
 * un journal.
 */
class GeminiTtsSecondKeyTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN = 'cle-gemini-principale';

    private const SECOND = 'cle-gemini-deuxieme';

    /** @var array<string, int> réponse de Google selon la clé */
    private array $answers = [self::MAIN => 503, self::SECOND => 200];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => self::MAIN, 'services.gemini.key_2' => self::SECOND]);

        Http::fake(['*/interactions' => fn (Request $request) => match ($this->answers[$request->header('x-goog-api-key')[0] ?? ''] ?? 500) {
            200 => Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/l16;codec=pcm;rate=24000', 'data' => base64_encode(str_repeat("\x10\x00", 2400))]]]]]),
            429 => Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded for metric: generate_requests_per_model_per_day, limit: 10', 'details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]]], 429),
            503 => Http::response(['error' => ['code' => 503, 'message' => 'The model is currently experiencing high demand.']], 503),
            default => Http::response([], 500),
        }]);
    }

    private function keysSent(): array
    {
        return Http::recorded()->map(fn (array $pair) => $pair[0]->header('x-goog-api-key')[0] === self::SECOND ? 'deuxième' : 'principale')->values()->all();
    }

    public function test_the_second_key_takes_over_when_google_is_overloaded(): void
    {
        $wav = app(VoiceSampler::class)->synthesize('Achird', 'Bonjour !', 'calme', 'Essai des voix : Achird');

        $this->assertStringStartsWith('RIFF', $wav);
        $this->assertSame(['principale', 'deuxième'], $this->keysSent());
        $this->assertStringContainsString('(clé principale)', json_encode(SpeechLog::recent(), JSON_UNESCAPED_UNICODE));

        foreach (SpeechLog::recent() as $entry) {
            $this->assertStringNotContainsString(self::MAIN, json_encode($entry));
        }
    }

    public function test_a_main_key_at_its_daily_limit_is_skipped_until_tomorrow(): void
    {
        $this->answers[self::MAIN] = 429;
        $sampler = app(VoiceSampler::class);

        $sampler->synthesize('Achird', 'Un.', 'calme');
        $sampler->synthesize('Achird', 'Deux.', 'calme');

        // Première demande : principale (refusée pour la journée), puis deuxième ; ensuite : deuxième directement.
        $this->assertSame(['principale', 'deuxième', 'deuxième'], $this->keysSent());
    }

    public function test_a_key_at_its_limit_comes_back_when_google_resets_the_quota_at_midnight_pacific_time(): void
    {
        config(['app.timezone' => 'Africa/Lome']);
        $this->answers[self::MAIN] = 429;
        $sampler = app(VoiceSampler::class);

        // 2 heures du matin à Lomé (19 heures la veille en Californie) : clé principale à sa limite.
        $this->travelTo(Carbon::parse('2026-10-29 02:00', 'Africa/Lome'));
        $sampler->synthesize('Achird', 'Un.', 'calme');

        // 6 heures 59 à Lomé : la journée de quota de Google n'est pas finie, la clé reste de côté.
        $this->travelTo(Carbon::parse('2026-10-29 06:59', 'Africa/Lome'));
        $sampler->synthesize('Achird', 'Deux.', 'calme');

        // 7 heures 01 à Lomé (minuit passé en Californie) : la clé principale est essayée de nouveau, la même journée à
        // Lomé (avant : seulement le lendemain à minuit, une journée de limite perdue).
        $this->answers[self::MAIN] = 200;
        $this->travelTo(Carbon::parse('2026-10-29 07:01', 'Africa/Lome'));
        $sampler->synthesize('Achird', 'Trois.', 'calme');

        $this->assertSame(['principale', 'deuxième', 'deuxième', 'principale'], $this->keysSent());
    }

    public function test_without_a_second_key_the_failure_is_reported_as_before(): void
    {
        config(['services.gemini.key_2' => null]);

        try {
            app(VoiceSampler::class)->synthesize('Achird', 'Bonjour !', 'calme');
            $this->fail('Échec attendu.');
        } catch (SpeechFailed $e) {
            $this->assertSame(503, $e->status);
        }

        $this->assertSame(['principale'], $this->keysSent());
    }
}
