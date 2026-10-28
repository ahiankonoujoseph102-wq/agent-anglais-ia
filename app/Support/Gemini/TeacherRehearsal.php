<?php

namespace App\Support\Gemini;

use App\Enums\Level;
use App\Models\User;
use App\Support\Duration;
use App\Support\Live\AssessmentMiniLesson;
use App\Support\Live\AssessmentVersion;
use App\Support\Plan;
use App\Support\Text\TextProviders;
use App\Support\Text\TextRequest;
use Illuminate\Support\Str;
use NumberFormatter;

/**
 * Banc d'essai de Teacher Joe (« php artisan teacher:rehearse ») : des scénarios INVENTÉS
 * (resources/teacher/rehearsal/scenarios.php) sont joués avec les vraies consignes du professeur, par le modèle
 * texte (Gemini, puis OpenAI en secours), qui répond comme s'il parlait ; chaque réponse est vérifiée
 * automatiquement (mots attendus, mots interdits, longueur). Avec « avant », les consignes d'avant l'amélioration
 * d'octobre 2026 (resources/teacher/rehearsal/previous.php) remplacent le temps d'un scénario celles de
 * config/teacher.php : mêmes scénarios, même modèle, seules les consignes changent.
 *
 * Limite assumée : la séance réelle se fait à l'oral, avec un autre modèle (en direct) ; le banc d'essai mesure
 * ce que les CONSIGNES font dire, pas la voix ni le délai de réponse.
 */
final class TeacherRehearsal
{
    /** Leçon inventée donnée au professeur pour les scénarios de séance du jour. */
    private const LESSON_CONTEXT = <<<'TXT'
        REHEARSAL CONTEXT (invented): TODAY'S BOARD, title « Au Grand Marché de Lomé ». Key phrases: "How much is it?" (C'est combien ?), "It's two thousand francs." (C'est deux mille francs.), "I think so." (Je pense que oui.). Words: market, price, cheap, expensive. Rule: "How much…?" asks a price.
        TODAY'S PLAN: lesson 5 minutes, practice 3 minutes, conversation 5 minutes, summary 2 minutes.
        TXT;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function scenarios(): array
    {
        return require resource_path('teacher/rehearsal/scenarios.php');
    }

    /**
     * Joue un scénario. Renvoie la réponse (null si aucun fournisseur n'a répondu), le fournisseur et les vérifications.
     *
     * @param  array<string, mixed>  $scenario
     * @return array{reply: ?string, provider: ?string, checks: list<array{label: string, ok: bool}>}
     */
    public static function run(array $scenario, bool $before = false): array
    {
        $instructions = self::instructions($scenario, $before);

        $result = TextProviders::generate(new TextRequest(
            kind: 'rehearsal',
            instructions: $instructions,
            parts: [TextRequest::text(self::transcript($scenario, $before))],
            maxTokens: 400,
            timeout: 30,
            effort: 'minimal',
        ));

        $reply = $result === null ? null : trim($result->text);

        return [
            'reply' => $reply,
            'provider' => $result?->provider,
            'checks' => $reply === null ? [] : self::check($reply, $scenario),
        ];
    }

    /**
     * Consignes complètes du professeur pour ce scénario (celles d'avant si $before), plus la règle du banc d'essai.
     *
     * @param  array<string, mixed>  $scenario
     */
    public static function instructions(array $scenario, bool $before = false): string
    {
        $kind = (string) $scenario['kind'];
        $user = new User(['name' => 'Afi']);
        $user->level = isset($scenario['level']) ? Level::from($scenario['level']) : null;

        // Scénarios de la nouvelle évaluation : « après » = nouvelle évaluation ; « avant » = évaluation actuelle (consignes
        // d'aujourd'hui, sans previous.php). Les autres scénarios passent toujours l'évaluation actuelle.
        $newAssessment = (bool) ($scenario['new_assessment'] ?? false);
        $saved = [];

        if ($before && ! $newAssessment) {
            foreach (self::previous() as $key => $value) {
                $saved[$key] = config("teacher.{$key}");
                config(["teacher.{$key}" => $value]);
            }
        }

        try {
            $extra = $kind === 'lesson' ? ['context' => self::LESSON_CONTEXT] : [];
            $text = AssessmentVersion::forcing($newAssessment && ! $before, fn () => TeacherSetup::instructions($kind, $user, $kind === 'assessment' ? (int) config('platform.assessment.max_minutes') : 15, $extra));
        } finally {
            foreach ($saved as $key => $value) {
                config(["teacher.{$key}" => $value]);
            }
        }

        return $text."\n\nWRITTEN REHEARSAL (not a real session): you are in the middle of the conversation below"
            .(isset($scenario['moment']) ? ' ('.$scenario['moment'].')' : '').'. '
            .'Reply with ONLY the words you would say aloud in your next turn, as plain text: no stage directions, no notes, no function call.';
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    public static function transcript(array $scenario, bool $before = false): string
    {
        $teacher = (string) config('avatar.model.name');
        $lines = collect($scenario['history'])
            ->map(fn (array $turn) => ($turn[0] === 'teacher' ? $teacher : 'Learner').': '.$turn[1]);

        // Évaluation : verdict déjà enregistré ; la suite renvoyée par la fonction (mini-leçon), comme en vraie séance.
        // Avant : la phrase fixe de la première leçon du niveau ; après : les vraies erreurs de l'apprenant.
        if (isset($scenario['verdict'])) {
            $next = AssessmentMiniLesson::for(Level::from($scenario['verdict']['level']), $before ? [] : $scenario['verdict']['lesson'])['next_step'] ?? '';
            $lines->push('(Result of record_assessment_result: '.$next.')');
        }

        return $lines->implode("\n")."\n".$teacher.':';
    }

    /**
     * Vérifications automatiques d'une réponse.
     *
     * @param  array<string, mixed>  $scenario
     * @return list<array{label: string, ok: bool}>
     */
    public static function check(string $reply, array $scenario): array
    {
        $haystack = ' '.self::normalize($reply).' ';
        $checks = [];

        foreach ($scenario['must'] ?? [] as $group) {
            $variants = collect($group)->flatMap(fn (string $item) => self::variants($item))->unique()->values();
            $checks[] = [
                'label' => 'dit « '.$variants->first().' »',
                'ok' => $variants->contains(fn (string $variant) => str_contains($haystack, self::normalize($variant))),
            ];
        }

        foreach ($scenario['never'] ?? [] as $word) {
            $checks[] = ['label' => 'ne dit pas « '.trim($word).' »', 'ok' => ! str_contains($haystack, self::normalize($word))];
        }

        if (isset($scenario['max_questions'])) {
            $questions = substr_count($reply, '?');
            $checks[] = ['label' => 'une seule question à la fois ('.$questions.' point'.($questions > 1 ? 's' : '').' d\'interrogation, '.$scenario['max_questions'].' au plus)', 'ok' => $questions <= $scenario['max_questions']];
        }

        if (isset($scenario['max_words'])) {
            $words = str_word_count(Str::ascii($reply));
            $checks[] = ['label' => 'réponse courte ('.$words.' mots, '.$scenario['max_words'].' au plus)', 'ok' => $words <= $scenario['max_words']];
        }

        return $checks;
    }

    /**
     * Formes acceptées d'un repère : {price:clé} en chiffres (« 2 000 », « 2000 ») et en lettres (« deux mille »),
     * {minutes:least} (« 15 minutes », « quinze minutes »), {teacher}, {platform} ; sinon le texte tel quel.
     *
     * @return list<string>
     */
    public static function variants(string $item): array
    {
        if (preg_match('/^\{price:(\w+)\}$/', $item, $match)) {
            $price = Plan::find($match[1])?->price;

            return $price === null ? [$item] : array_values(array_filter([
                number_format($price, 0, ',', ' '), (string) $price, self::words($price),
            ]));
        }

        if ($item === '{minutes:least}') {
            $minutes = (int) collect(Plan::all())->min('dailyMinutes');

            return array_values(array_filter([Duration::format($minutes * 60), $minutes.' min', self::words($minutes).' minutes']));
        }

        return [strtr($item, ['{teacher}' => (string) config('avatar.model.name'), '{platform}' => (string) config('platform.name')])];
    }

    /** Minuscules, sans accents, espaces simples (insécables compris). */
    public static function normalize(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{202F}", '’'], [' ', ' ', "'"], $text);

        return (string) preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($text)));
    }

    /**
     * Consignes d'avant l'amélioration (clés de config/teacher.php).
     *
     * @return array<string, string>
     */
    public static function previous(): array
    {
        return require resource_path('teacher/rehearsal/previous.php');
    }

    private static function words(int $number): ?string
    {
        if (! class_exists(NumberFormatter::class)) {
            return null;
        }

        return (string) (new NumberFormatter('fr', NumberFormatter::SPELLOUT))->format($number);
    }
}
