<?php

namespace App\Support\Gemini;

use App\Enums\Level;

/**
 * Niveau estimé à partir de la transcription, quand l'évaluation se termine
 * (temps écoulé, page fermée) avant le verdict du professeur.
 * Modèle texte léger (TextClient : Gemini, OpenAI en secours) ; null si l'appel échoue ou si la réponse est illisible.
 */
class AssessmentEstimator
{
    public function __construct(private TextClient $client) {}

    /**
     * @return array{level: Level, feedback: string}|null
     */
    public function estimate(string $transcript): ?array
    {
        $answer = $this->client->write(
            'You are an experienced examiner of spoken English (CEFR). You read the transcript of a short oral '
            .'placement test between a teacher and a French-speaking learner. Judge ONLY the learner\'s lines: '
            .'comprehension, vocabulary, grammar, fluency. Answers in French or no answer mean a low level. '
            .'Reply with exactly two lines and nothing else:'."\n"
            .'LEVEL: <A1, A2, B1, B2, C1 or C2>'."\n"
            .'FEEDBACK: <'.TeacherSetup::FEEDBACK_RULE.'>',
            "TRANSCRIPT\n".$transcript,
            300,
            'estimate',
        );

        if ($answer === null || ! preg_match('/LEVEL:\s*\**\s*(A1|A2|B1|B2|C1|C2)\b/i', $answer, $level)) {
            return null;
        }

        preg_match('/FEEDBACK:\s*(.+)/is', $answer, $feedback);

        return [
            'level' => Level::from(strtoupper($level[1])),
            'feedback' => trim(mb_substr($feedback[1] ?? '', 0, 600)) ?: 'Niveau estimé à partir de votre conversation.',
        ];
    }
}
