<?php

namespace Tests\Concerns;

use App\Enums\LessonStatus;
use App\Models\ConversationLine;
use App\Models\CourseLesson;
use App\Models\CourseSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Aides des tests des documents de révision : séance « Mes cours » finie avec son texte, réponses simulées du
 * modèle texte (aucun appel réel). À utiliser avec Tests\Concerns\HasCourses.
 */
trait HasCourseReviews
{
    /** Réponse simulée de Gemini (tableau JSON, ou code d'erreur HTTP) et d'OpenAI. */
    protected mixed $geminiReview = null;

    protected mixed $openaiReview = 500;

    protected function fakeReviewProviders(): void
    {
        config(['services.text.providers' => ['gemini', 'openai'], 'services.gemini.key' => 'cle-gemini', 'services.openai.key' => 'cle-openai']);
        $this->geminiReview ??= $this->reviewContent();

        Http::fake([
            '*/interactions' => fn () => $this->reviewReply($this->geminiReview, 'gemini'),
            'api.openai.com/v1/responses' => fn () => $this->reviewReply($this->openaiReview, 'openai'),
        ]);
    }

    private function reviewReply(mixed $answer, string $provider): mixed
    {
        if (is_int($answer)) {
            return Http::response(['error' => ['code' => $answer, 'message' => 'Erreur interne']], $answer);
        }

        $text = json_encode($answer);

        return $provider === 'gemini'
            ? Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]], 'usage' => ['total_input_tokens' => 9000, 'total_output_tokens' => 3000]])
            : Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text]]]], 'usage' => ['input_tokens' => 9000, 'output_tokens' => 3000]]);
    }

    /**
     * Ce que le modèle texte « rédige » : un document complet par défaut.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function reviewContent(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Le présent simple',
            'lesson' => [[
                'heading' => 'Le -s de la troisième personne',
                'explanation' => 'Au présent simple, on ajoute -s au verbe avec he, she et it.',
                'examples' => [['en' => 'She works in a bank.', 'fr' => 'Elle travaille dans une banque.'], ['en' => 'He plays soccer.', 'fr' => 'Il joue au football.']],
            ]],
            'exercises' => [[
                'instruction' => 'Complete: She ___ (to work) in a bank.',
                'method' => 'Le sujet est « she » : on ajoute -s au verbe.',
                'answer' => 'She works in a bank.',
                'explanation' => 'Troisième personne du singulier : works.',
            ]],
            'errors' => [['said' => 'She work in a bank.', 'correct' => 'She works in a bank.', 'explanation' => 'Il manque le -s.']],
            'phrases' => [
                ['en' => 'She works in a bank.', 'fr' => 'Elle travaille dans une banque.'],
                ['en' => 'I go to the market every day.', 'fr' => 'Je vais au marché tous les jours.'],
                ['en' => 'He plays soccer.', 'fr' => 'Il joue au football.'],
            ],
            'practice' => [
                'instruction' => 'Mettez le verbe au présent simple.',
                'items' => ['He ___ (to eat) rice.', 'My mother ___ (to cook) every day.', 'They ___ (to play) soccer.'],
                'answers' => ['eats', 'cooks', 'play'],
            ],
        ], $overrides);
    }

    /**
     * Une séance « Mes cours » finie il y a quelques minutes, avec son texte (répliques de l'élève et du professeur).
     */
    protected function finishedSession(User $user, CourseLesson $lesson, int $seconds = 480, int $minutesAgo = 5, ?string $learner = null): CourseSession
    {
        $session = $user->courseSessions()->create([
            'course_lesson_id' => $lesson->id,
            'date' => today(),
            'allowed_seconds' => 600,
            'used_seconds' => $seconds,
            'started_at' => now()->subMinutes($minutesAgo)->subSeconds($seconds),
            'ended_at' => now()->subMinutes($minutesAgo),
            'status' => LessonStatus::Completed,
        ]);

        $lines = [
            ['teacher', 'Bonjour ! Que voulez-vous faire aujourd\'hui ?'],
            ['learner', $learner ?? 'Je veux comprendre le présent simple et faire l\'exercice un. She work in a bank, je crois.'],
            ['teacher', 'Presque ! Avec she, on ajoute -s. Redites la phrase.'],
            ['learner', $learner === null ? 'She works in a bank. Merci beaucoup, c\'est plus clair maintenant.' : ''],
        ];

        foreach ($lines as $index => [$speaker, $text]) {
            if ($text !== '') {
                ConversationLine::query()->create([
                    'user_id' => $user->id, 'conversation_type' => 'course', 'conversation_id' => $session->id,
                    'position' => $index + 1, 'speaker' => $speaker, 'text' => $text, 'spoken_at' => $session->started_at->copy()->addSeconds(10 * ($index + 1)),
                ]);
            }
        }

        return $session;
    }
}
