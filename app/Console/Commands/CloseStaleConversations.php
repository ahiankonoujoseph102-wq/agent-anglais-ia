<?php

namespace App\Console\Commands;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\LevelTestStatus;
use App\Models\Assessment;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;
use App\Support\Live\OpenAiRealtime;
use App\Support\Live\TrialCalls;
use App\Support\Trial;
use Illuminate\Console\Command;

class CloseStaleConversations extends Command
{
    protected $signature = 'live:close-stale';

    protected $description = 'Clôture les évaluations, séances (orales et « Mes cours ») et bilans de niveau dont le temps est écoulé ou dont le navigateur a disparu';

    public function handle(): int
    {
        $closed = 0;

        $models = [
            Assessment::query()->where('status', AssessmentStatus::InProgress),
            Lesson::query()->where('status', LessonStatus::InProgress),
            LevelTest::query()->where('status', LevelTestStatus::InProgress),
            CourseSession::query()->where('status', LessonStatus::InProgress),
        ];

        foreach ($models as $query) {
            $query->lazyById()->each(function (Assessment|CourseSession|Lesson|LevelTest $conversation) use (&$closed) {
                if (! $conversation->closeIfOver()) {
                    $closed++;
                }
            });
        }

        // Essais de l'administrateur (diagnostic, « Écouter en séance ») : appels OpenAI raccrochés à leur durée.
        $hungUp = TrialCalls::hangUpOverdue(app(OpenAiRealtime::class));

        // Séances d'essai gratuites dont le temps est pris : terminées (« Mon espace » propose alors les formules).
        $trials = Trial::expireUsed();

        $this->info($closed.' conversation(s) clôturée(s)'.($hungUp > 0 ? ', '.$hungUp.' essai(s) raccroché(s)' : '')
            .($trials > 0 ? ', '.$trials.' séance(s) d\'essai terminée(s)' : '').'.');

        return self::SUCCESS;
    }
}
