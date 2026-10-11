<?php

namespace App\Console\Commands;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\LevelTestStatus;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\LevelTest;
use Illuminate\Console\Command;

class CloseStaleConversations extends Command
{
    protected $signature = 'live:close-stale';

    protected $description = 'Clôture les évaluations, séances et bilans de niveau dont le temps est écoulé ou dont le navigateur a disparu';

    public function handle(): int
    {
        $closed = 0;

        $models = [
            Assessment::query()->where('status', AssessmentStatus::InProgress),
            Lesson::query()->where('status', LessonStatus::InProgress),
            LevelTest::query()->where('status', LevelTestStatus::InProgress),
        ];

        foreach ($models as $query) {
            $query->lazyById()->each(function (Assessment|Lesson|LevelTest $conversation) use (&$closed) {
                if (! $conversation->closeIfOver()) {
                    $closed++;
                }
            });
        }

        $this->info($closed.' conversation(s) clôturée(s).');

        return self::SUCCESS;
    }
}
