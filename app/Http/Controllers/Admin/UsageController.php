<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Speech\SpeechProviders;
use App\Support\Speech\SpeechUsage;
use App\Support\Text\TextProviders;
use App\Support\Text\TextUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Durée réelle et jetons consommés par conversation, pour calculer le coût par apprenant ; synthèse vocale
 * des messages et analyse de texte et d'images à part, par fournisseur.
 */
class UsageController extends Controller
{
    /** Vrai quand la conversation a démarré pendant un accès offert du même apprenant. */
    private const DURING_GRANT_SQL = '(exists (select 1 from subscriptions where subscriptions.user_id = lessons.user_id and subscriptions.granted_days is not null and lessons.started_at >= subscriptions.starts_at and lessons.started_at <= subscriptions.ends_at))';

    public function index(): View
    {
        $totals = [
            'Évaluations' => $this->totals(Assessment::query()),
            'Séances' => $this->totals(Lesson::query()->whereNotNull('started_at')),
            'Bilans de niveau' => $this->totals(LevelTest::query()),
            // Séances « Mes cours » : consommation à part, jamais mélangée aux séances orales.
            'Séances « Mes cours »' => $this->totals(CourseSession::query()->whereNotNull('started_at')),
        ];

        // Pendant un accès offert (démonstrations) : à part, pour mesurer ce que coûtent les démonstrations.
        $granted = [
            'Séances' => $this->totals(Lesson::query()->whereNotNull('started_at')->tap(fn (Builder $query) => Subscription::whereDuringGrant($query, 'lessons'))),
            'Séances « Mes cours »' => $this->totals(CourseSession::query()->whereNotNull('started_at')->tap(fn (Builder $query) => Subscription::whereDuringGrant($query, 'course_sessions'))),
        ];

        $learners = User::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('lessons', fn (Builder $query) => $query->whereNotNull('started_at'))
                ->orWhereHas('courseSessions')
                ->orWhereHas('assessment'))
            ->with('assessment')
            ->withCount(['lessons' => fn (Builder $query) => $query->whereNotNull('started_at')])
            ->withSum('lessons', 'used_seconds')
            ->withSum('lessons', 'input_tokens')
            ->withSum('lessons', 'output_tokens')
            ->withCount('courseSessions')
            ->withSum('courseSessions', 'used_seconds')
            ->withSum('courseSessions', 'input_tokens')
            ->withSum('courseSessions', 'output_tokens')
            ->orderByDesc('lessons_sum_output_tokens')
            ->paginate(25);

        $recentLessons = Lesson::query()
            ->whereNotNull('started_at')
            ->selectRaw('lessons.*, '.self::DURING_GRANT_SQL.' as during_grant')
            ->with('user')
            ->latest('started_at')
            ->limit(30)
            ->get();

        $recentCourseSessions = CourseSession::query()
            ->whereNotNull('started_at')
            ->selectRaw('course_sessions.*, '.str_replace('lessons', 'course_sessions', self::DURING_GRANT_SQL).' as during_grant')
            ->with(['user', 'lesson'])
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
            'granted' => $granted,
            'learners' => $learners,
            'recentLessons' => $recentLessons,
            'recentCourseSessions' => $recentCourseSessions,
            'recentAssessments' => $recentAssessments,
            // Synthèse vocale des messages (bienvenue, résultat, essais) : à part, par fournisseur.
            'speech' => SpeechUsage::totals(),
            'speechProviders' => SpeechProviders::all(),
            'text' => TextUsage::totals(),
            'textKinds' => TextUsage::byKind(),
            'textProviders' => TextProviders::all(),
        ]);
    }

    /**
     * @param  Builder<Assessment>|Builder<CourseSession>|Builder<Lesson>|Builder<LevelTest>  $query
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
