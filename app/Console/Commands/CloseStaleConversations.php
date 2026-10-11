<?php

namespace App\Console\Commands;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Models\Assessment;
use App\Models\Lesson;
use Illuminate\Console\Command;

class CloseStaleConversations extends Command
{
    protected $signature = 'live:close-stale';

    protected $description = 'Clôture les évaluations et séances dont le temps est écoulé ou dont le navigateur a disparu';

    public function handle(): int
    {
        $closed = 0;

        $models = [
            Assessment::query()->where('status', AssessmentStatus::InProgress),
            Lesson::query()->where('status', LessonStatus::InProgress),
        ];

        foreach ($models as $query) {
            $query->lazyById()->each(function (Assessment|Lesson $conversation) use (&$closed) {
                if (! $conversation->closeIfOver()) {
                    $closed++;
                }
            });
        }

        $this->info($closed.' conversation(s) clôturée(s).');

        return self::SUCCESS;
    }
}
