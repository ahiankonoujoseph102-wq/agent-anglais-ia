<?php

namespace App\Support\Curriculum;

use App\Enums\Difficulty;
use App\Enums\Goal;
use App\Enums\Level;
use App\Enums\Profile;
use App\Enums\ProgramStatus;
use App\Models\Program;
use App\Models\User;
use App\Support\Gemini\TextClient;
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
     * Présentation personnalisée du programme, rédigée par le modèle texte (ou texte type).
     */
    private function intro(User $user, Program $program): string
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
