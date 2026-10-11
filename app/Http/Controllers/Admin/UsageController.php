<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Durée réelle et jetons consommés par conversation, pour calculer le coût par apprenant.
 */
class UsageController extends Controller
{
    public function index(): View
    {
        $totals = [
            'Évaluations' => $this->totals(Assessment::query()),
            'Séances' => $this->totals(Lesson::query()->whereNotNull('started_at')),
        ];

        $learners = User::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('lessons', fn (Builder $query) => $query->whereNotNull('started_at'))
                ->orWhereHas('assessment'))
            ->with('assessment')
            ->withCount(['lessons' => fn (Builder $query) => $query->whereNotNull('started_at')])
            ->withSum('lessons', 'used_seconds')
            ->withSum('lessons', 'input_tokens')
            ->withSum('lessons', 'output_tokens')
            ->orderByDesc('lessons_sum_output_tokens')
            ->paginate(25);

        $recentLessons = Lesson::query()
            ->whereNotNull('started_at')
            ->with('user')
            ->latest('started_at')
            ->limit(30)
            ->get();

        $recentAssessments = Assessment::query()
            ->with('user')
            ->latest('started_at')
            ->limit(30)
            ->get();

        return view('admin.usage.index', [
            'totals' => $totals,
            'learners' => $learners,
            'recentLessons' => $recentLessons,
            'recentAssessments' => $recentAssessments,
        ]);
    }

    /**
     * @param  Builder<Assessment>|Builder<Lesson>  $query
     * @return array{count: int, seconds: int, input: int, output: int}
     */
    private function totals(Builder $query): array
    {
        $row = $query->toBase()
            ->selectRaw('count(*) as aggregate_count, coalesce(sum(used_seconds), 0) as seconds, coalesce(sum(input_tokens), 0) as input, coalesce(sum(output_tokens), 0) as output')
            ->first();

        return [
            'count' => (int) $row->aggregate_count,
            'seconds' => (int) $row->seconds,
            'input' => (int) $row->input,
            'output' => (int) $row->output,
        ];
    }
}
