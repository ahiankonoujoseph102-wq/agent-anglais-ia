<?php

namespace Tests\Concerns;

use App\Enums\Level;
use App\Enums\SubscriptionStatus;
use App\Models\CourseLesson;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Aides des tests de « Mes cours » : apprenant avec accès, lecture simulée (aucun appel réel à
 * Gemini), fichiers de leçon fictifs.
 */
trait HasCourses
{
    protected function learner(string $plan = 'essentiel'): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
        Subscription::factory()->for($user)->forPlan($plan)->create();

        return $user;
    }

    protected function provisionalLearner(): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Kofi']);
        Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Provisional,
            'provisional_until' => now()->addHours(20),
        ]);

        return $user;
    }

    /**
     * Ce que l'IA « lit » sur la leçon : une leçon d'anglais lisible par défaut.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function lessonReading(array $overrides = []): array
    {
        return array_merge([
            'verdict' => 'lesson',
            'title' => 'Le présent simple',
            'summary' => 'Cette leçon explique le présent simple et donne des exercices.',
            'level' => 'A2',
            'grammar' => [[
                'point' => 'Present simple',
                'explanation' => 'On ajoute -s à la troisième personne du singulier.',
                'examples' => ['She works in Lomé.', 'He plays football.'],
            ]],
            'vocabulary' => [['english' => 'to work', 'french' => 'travailler'], ['english' => 'market', 'french' => 'marché']],
            'examples' => ['I go to the market every day.'],
            'exercises' => ['Complete: She ___ (to work) in a bank.'],
            'text' => 'Present simple. She works in a bank. Exercise 1: complete the sentences.',
        ], $overrides);
    }

    /**
     * Simule la réponse de lecture (API Interactions) et la délivrance d'un jeton de conversation.
     *
     * @param  array<string, mixed>|null  $reading  null : panne du service de lecture
     */
    protected function fakeGemini(?array $reading = [], int $tokenStatus = 200): void
    {
        $reading = $reading === [] ? $this->lessonReading() : $reading;

        Http::fake([
            '*/interactions' => $reading === null
                ? Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)
                : Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode($reading)]]]]]),
            '*/auth_tokens' => $tokenStatus === 200
                ? Http::response(['name' => 'auth_tokens/jeton-cours'])
                : Http::response(['error' => ['status' => 'UNAVAILABLE', 'message' => 'indisponible']], $tokenStatus),
        ]);
    }

    protected function photo(string $name = 'page.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 1100);
    }

    protected function pdfFile(int $pages = 3, bool $objectStream = false, string $name = 'lecon.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pdfBytes($pages, $objectStream));
    }

    /**
     * PDF minimal : arbre des pages en clair, ou rangé dans un flux compressé (« object stream »)
     * comme le font les PDF récents.
     */
    protected function pdfBytes(int $pages, bool $objectStream = false): string
    {
        $kids = implode(' ', array_map(fn (int $i) => (3 + $i).' 0 R', range(0, $pages - 1)));

        if ($objectStream) {
            $inner = "<< /Type /Pages /Kids [{$kids}] /Count {$pages} >>\n";

            foreach (range(0, $pages - 1) as $i) {
                $inner .= '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>'."\n";
            }

            $stream = gzcompress($inner);

            return "%PDF-1.5\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
                .'4 0 obj'."\n<< /Type /ObjStm /N ".($pages + 1).' /First 0 /Filter /FlateDecode /Length '.strlen($stream)." >>\nstream\n"
                .$stream."\nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        }

        $objects = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count {$pages} >>\nendobj\n";

        foreach (range(0, $pages - 1) as $i) {
            $objects .= (3 + $i)." 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n";
        }

        return "%PDF-1.4\n{$objects}trailer\n<< /Root 1 0 R >>\n%%EOF";
    }

    /**
     * Une leçon déjà lue, avec ses pages sur le disque privé.
     */
    protected function storedLesson(User $user, string $title = 'Le présent simple', int $pages = 2): CourseLesson
    {
        $lesson = $user->courseLessons()->create([
            'title' => $title,
            'source' => CourseLesson::SOURCE_PHOTOS,
            'page_count' => $pages,
            'content' => array_diff_key($this->lessonReading(), array_flip(['verdict', 'title', 'text'])),
            'text' => 'Present simple. She works in a bank.',
        ]);

        foreach (range(1, $pages) as $position) {
            $path = "courses/{$user->id}/{$lesson->id}/page-{$position}.jpg";
            Storage::disk('local')->put($path, 'octets-de-la-page-'.$position);
            $lesson->pages()->create(['position' => $position, 'path' => $path, 'mime' => 'image/jpeg', 'size' => 20]);
        }

        return $lesson;
    }
}
