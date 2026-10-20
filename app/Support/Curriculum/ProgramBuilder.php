<?php

namespace App\Support\Curriculum;

use App\Enums\Difficulty;
use App\Enums\Goal;
use App\Enums\Level;
use App\Enums\Profile;
use App\Enums\ProgramLessonStatus;
use App\Enums\ProgramStatus;
use App\Models\Program;
use App\Models\ProgramLesson;
use App\Models\User;
use App\Support\Gemini\TextClient;
use App\Support\Text\TextProviders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Construit le programme personnel d'un apprenant pour un niveau :
 * les 12 unités dans l'ordre, 3 leçons par unité (une par situation),
 * les situations liées à son objectif et à son métier en premier,
 * réparties sur ses jours de travail.
 */
class ProgramBuilder
{
    public function __construct(private TextClient $text) {}

    /**
     * @param  array{goal: string, profile?: string|null, occupation: string, interests?: string|null, study_days: list<int|string>, study_time?: string|null, difficulties: list<string>}  $answers
     */
    public function build(User $user, Level $level, array $answers): Program
    {
        $goal = Goal::from($answers['goal']);
        $profile = Profile::tryFrom((string) ($answers['profile'] ?? ''));
        $profileTags = array_values(array_unique([
            ...($profile?->tags() ?? []),
            ...self::profileTags(($answers['occupation'] ?? '').' '.($answers['interests'] ?? '')),
        ]));

        $program = DB::transaction(function () use ($user, $level, $answers, $goal, $profile, $profileTags) {
            // Un seul programme actif : l'ancien est archivé (ou reste « validé »).
            $user->programs()->where('is_active', true)->get()->each(function (Program $old) {
                $old->is_active = false;

                if ($old->status !== ProgramStatus::Completed) {
                    $old->status = ProgramStatus::Archived;
                }

                $old->save();
            });

            $program = $user->programs()->create([
                'level' => $level,
                'start_level' => $level,
                'target_level' => $level->next() ?? $level,
                'status' => ProgramStatus::Active,
                'goal' => $goal,
                'profile' => $profile,
                'occupation' => trim($answers['occupation']),
                'interests' => filled($answers['interests'] ?? null) ? trim($answers['interests']) : null,
                'study_days' => array_values(array_map('intval', $answers['study_days'])),
                'study_time' => $answers['study_time'] ?? null,
                'difficulties' => array_values($answers['difficulties']),
                'is_active' => true,
            ]);

            $position = 0;

            foreach (Curriculum::units($level) as $unit) {
                foreach (self::orderSituations($unit, $goal, $profileTags) as $lessonNumber => [$index, $highlighted]) {
                    $program->programLessons()->create([
                        'position' => ++$position,
                        'unit_number' => $unit->number,
                        'lesson_number' => $lessonNumber + 1,
                        'situation_index' => $index,
                        'highlighted' => $highlighted,
                    ]);
                }
            }

            // Une leçon par séance, une séance par jour de travail.
            $program->reschedule(today());

            return $program;
        });

        $program->content = ['intro' => $this->intro($user, $program)];
        $program->save();

        return $program;
    }

    /**
     * Nouvelles réponses au questionnaire (« Modifier mes objectifs ») pour un programme existant. La progression
     * est GARDÉE : niveau, leçons faites, leçons déjà commencées (séance en cours comprise) et leur situation ne
     * changent pas ; seules les leçons à venir reçoivent de nouvelles situations, choisies avec les nouvelles
     * réponses, et le calendrier suit les nouveaux jours. Renvoie false sans rien changer si la présentation
     * ne peut pas être rédigée (modèle texte en échec alors qu'un fournisseur est réglé).
     *
     * @param  array{goal: string, profile?: string|null, occupation: string, interests?: string|null, study_days: list<int|string>, study_time?: string|null, difficulties: list<string>}  $answers
     */
    public function update(User $user, Program $program, array $answers): bool
    {
        $goal = Goal::from($answers['goal']);
        $profile = Profile::tryFrom((string) ($answers['profile'] ?? ''));
        $profileTags = array_values(array_unique([
            ...($profile?->tags() ?? []),
            ...self::profileTags(($answers['occupation'] ?? '').' '.($answers['interests'] ?? '')),
        ]));

        // Les réponses sur une copie : rien n'est enregistré avant la fin.
        $draft = $program->replicate();
        $draft->fill([
            'goal' => $goal,
            'profile' => $profile,
            'occupation' => trim($answers['occupation']),
            'interests' => filled($answers['interests'] ?? null) ? trim($answers['interests']) : null,
            'study_days' => array_values(array_map('intval', $answers['study_days'])),
            'study_time' => $answers['study_time'] ?? null,
            'difficulties' => array_values($answers['difficulties']),
        ]);

        // Présentation rédigée AVANT d'enregistrer : en cas d'échec du modèle texte, l'ancien programme reste.
        $intro = $this->intro($user, $draft, strict: true);

        if ($intro === null) {
            return false;
        }

        DB::transaction(function () use ($program, $draft, $goal, $profileTags, $intro) {
            $program->fill($draft->only(['goal', 'profile', 'occupation', 'interests', 'study_days', 'study_time', 'difficulties']));
            $program->content = array_merge((array) $program->content, ['intro' => $intro]);
            $program->save();

            // Leçons déjà touchées : faites, commencées, ou liées à une séance (même en cours) : jamais changées.
            $started = $program->lessons()->whereNotNull('program_lesson_id')->pluck('program_lesson_id')->all();
            $byUnit = $program->programLessons()->orderBy('lesson_number')->get()->groupBy('unit_number');

            foreach ($byUnit as $unitNumber => $lessons) {
                $unit = Curriculum::unit($program->level, (int) $unitNumber);
                $fixed = $lessons->filter(fn (ProgramLesson $lesson) => $lesson->status !== ProgramLessonStatus::Pending
                    || $lesson->attempts > 0 || in_array($lesson->id, $started, true));
                $free = $lessons->diff($fixed)->values();
                $taken = $fixed->pluck('situation_index')->all();
                $order = array_values(array_filter(self::orderSituations($unit, $goal, $profileTags), fn (array $item) => ! in_array($item[0], $taken, true)));

                foreach ($free as $position => $lesson) {
                    if (isset($order[$position])) {
                        $lesson->update(['situation_index' => $order[$position][0], 'highlighted' => $order[$position][1]]);
                    }
                }
            }

            // Le calendrier suit les nouveaux jours de travail.
            $program->reschedule(today());
        });

        return true;
    }

    /**
     * Ordre des situations d'une unité : celles liées à l'objectif (2 points)
     * puis au métier ou aux centres d'intérêt (1 point) passent en premier.
     *
     * @param  list<string>  $profileTags
     * @return list<array{0: int, 1: bool}> [index de la situation, mise en avant]
     */
    public static function orderSituations(Unit $unit, Goal $goal, array $profileTags): array
    {
        $scored = [];

        foreach ($unit->situations as $index => $situation) {
            $score = (array_intersect($situation->tags, $goal->tags()) !== [] ? 2 : 0)
                + (array_intersect($situation->tags, $profileTags) !== [] ? 1 : 0);
            $scored[] = [$index, $score];
        }

        // Tri stable : à score égal, l'ordre du programme officiel est conservé.
        usort($scored, fn ($a, $b) => [$b[1], $a[0]] <=> [$a[1], $b[0]]);

        return array_map(fn ($item) => [$item[0], $item[1] > 0], $scored);
    }

    /**
     * Thèmes déduits du métier et des centres d'intérêt (config program.occupation_keywords).
     *
     * @return list<string>
     */
    public static function profileTags(string $text): array
    {
        $text = Str::lower(Str::ascii($text));
        $tags = [];

        foreach (config('program.occupation_keywords') as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return $tags;
    }

    /**
     * Présentation personnalisée du programme, rédigée par le modèle texte (ou texte type). Avec $strict : null
     * si un fournisseur du modèle texte est réglé mais qu'aucun ne répond (le texte type ne sert alors que
     * lorsqu'aucun fournisseur n'est réglé).
     */
    private function intro(User $user, Program $program, bool $strict = false): ?string
    {
        $units = array_map(fn (Unit $unit) => $unit->title, array_slice($program->units(), 0, 3));
        $difficulties = implode(', ', array_map(fn (Difficulty $d) => Str::lcfirst($d->label()), $program->difficultyCases()));

        $written = $this->text->write(
            'Tu es le professeur d\'anglais de '.config('platform.name').'. Tu écris en français simple et chaleureux, '
            .'au vouvoiement, pour un adulte d\'Afrique francophone. 3 ou 4 phrases, sans titre, sans liste, sans emoji. '
            .'N\'invente aucune information.',
            "Présente son programme personnel à l'apprenant.\n"
            ."Prénom : {$user->name}\nNiveau : {$program->level->value} ({$program->level->label()})\n"
            .'Objectif : '.Str::lcfirst($program->goal->label())."\n"
            .($program->profile ? 'Profil : '.Str::lcfirst($program->profile->label())."\n" : '')
            ."Métier ou situation : {$program->occupation}\n"
            .'Ce qu\'il trouve difficile : '.$difficulties."\n"
            .'Jours de travail : '.$program->studyDaysLabel()."\n"
            .'Premières unités : '.implode(' ; ', $units),
            kind: 'program',
        );

        if ($written === null && $strict && TextProviders::available() !== []) {
            return null;
        }

        return $written ?? sprintf(
            'Bienvenue dans votre programme %s, %s ! Vos %d unités sont prêtes, avec en priorité des situations liées à %s et à votre activité (%s). '
            .'Nous travaillerons ensemble le %s, en insistant sur ce que vous trouvez difficile : %s. Commencez par l\'unité « %s ».',
            $program->level->value,
            $user->name,
            Curriculum::UNITS_PER_LEVEL,
            Str::lcfirst($program->goal->label()),
            $program->occupation,
            $program->studyDaysLabel(),
            $difficulties,
            $units[0] ?? '',
        );
    }
}
