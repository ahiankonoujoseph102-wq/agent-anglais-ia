@php
    use App\Support\Duration;
    use App\Support\Gemini\UsageCost;
@endphp

<x-layout title="Consommation">
    @include('admin.nav')

    <h1>Consommation du professeur IA</h1>
    <p class="muted small">
        Durée réelle et jetons rapportés par l'API Gemini. Coût estimé au tarif payant
        ({{ config('services.gemini.pricing.input_per_million') }} $ par million de jetons en entrée,
        {{ config('services.gemini.pricing.output_per_million') }} $ en sortie) ; il est nul tant que
        la formule gratuite de Google est utilisée.
    </p>

    <div class="stats">
        @foreach ($totals as $label => $total)
            @php
                $cost = UsageCost::usd($total['input'], $total['output']);
            @endphp
            <div class="stat">
                <div class="label">{{ $label }}</div>
                <div class="value">{{ $total['count'] }}</div>
                <div class="detail">
                    {{ Duration::format($total['seconds']) }} ·
                    {{ number_format($total['input'], 0, ',', ' ') }} / {{ number_format($total['output'], 0, ',', ' ') }} jetons ·
                    {{ UsageCost::format($cost) }}
                    @if ($total['seconds'] >= 60)
                        ({{ UsageCost::format($cost / ($total['seconds'] / 60)) }} par minute)
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Démonstrations : conversations démarrées pendant un accès offert (jamais un revenu). --}}
    <h2 class="next-step">Pendant un accès offert (démonstrations)</h2>
    <div class="stats">
        @foreach ($granted as $label => $total)
            @php
                $cost = UsageCost::usd($total['input'], $total['output']);
            @endphp
            <div class="stat">
                <div class="label">{{ $label }}</div>
                <div class="value">{{ $total['count'] }}</div>
                <div class="detail">
                    {{ Duration::format($total['seconds']) }} ·
                    {{ number_format($total['input'], 0, ',', ' ') }} / {{ number_format($total['output'], 0, ',', ' ') }} jetons ·
                    {{ UsageCost::format($cost) }}
                </div>
            </div>
        @endforeach
    </div>
    <p class="muted small">Ces conversations sont aussi comptées dans les totaux ci-dessus ; elles sont reconnaissables à « Accès offert » dans les listes.</p>

    {{-- Synthèse vocale des messages (bienvenue, résultat de l'évaluation, essais) : jamais mélangée aux conversations. --}}
    <h2 class="next-step">Synthèse vocale des messages</h2>
    <div class="stats">
        @foreach ($speechProviders as $provider)
            @php
                $total = $speech[$provider->name()];
            @endphp
            <div class="stat">
                <div class="label">{{ $provider->label() }}</div>
                <div class="value">{{ $total['count'] }}</div>
                <div class="detail">
                    {{ $total['count'] > 1 ? 'synthèses' : 'synthèse' }} ·
                    {{ Duration::formatPrecise(intdiv($total['milliseconds'], 1000)) }} de voix ·
                    {{ number_format($total['characters'], 0, ',', ' ') }} caractères ·
                    {{ UsageCost::format($total['cost']) }}
                </div>
            </div>
        @endforeach
    </div>
    <p class="muted small">
        Coût estimé à {{ UsageCost::format((float) config('services.gemini.tts_cost_per_minute')) }} par minute de voix pour Gemini (mode gratuit)
        et {{ UsageCost::format((float) config('services.openai.tts_cost_per_minute')) }} par minute pour OpenAI (tarif publié).
        Les séances orales en direct n'en font pas partie.
    </p>

    {{-- Modèle texte (captures, « Mes cours », estimation, rédactions) : Gemini d'abord, OpenAI en secours. --}}
    <h2 class="next-step">Analyse de texte et d'images</h2>
    <div class="stats">
        @foreach ($textProviders as $provider)
            @php
                $total = $text[$provider->name()];
            @endphp
            <div class="stat" data-text-usage="{{ $provider->name() }}">
                <div class="label">{{ $provider->label() }}</div>
                <div class="value">{{ $total['count'] }}</div>
                <div class="detail">
                    {{ $total['count'] > 1 ? 'demandes' : 'demande' }} ·
                    {{ number_format($total['input_tokens'], 0, ',', ' ') }} / {{ number_format($total['output_tokens'], 0, ',', ' ') }} jetons ·
                    {{ UsageCost::format($total['cost']) }}
                </div>
            </div>
        @endforeach
    </div>
    @if ($textKinds !== [])
        <table class="table">
            <thead>
                <tr>
                    <th>Usage</th>
                    @foreach ($textProviders as $provider)
                        <th>{{ $provider->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (\App\Support\Text\TextRequest::KINDS as $kind => $label)
                    @continue(! isset($textKinds[$kind]))
                    <tr>
                        <td>{{ $label }}</td>
                        @foreach ($textProviders as $provider)
                            <td>{{ $textKinds[$kind][$provider->name()] ?? 0 }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <p class="muted small">
        Coût estimé par million de jetons : Gemini {{ UsageCost::format((float) config('services.text.pricing.gemini.input')) }} en entrée
        et {{ UsageCost::format((float) config('services.text.pricing.gemini.output')) }} en sortie (palier gratuit),
        OpenAI {{ UsageCost::format((float) config('services.text.pricing.openai.input')) }} en entrée
        et {{ UsageCost::format((float) config('services.text.pricing.openai.output')) }} en sortie (tarif publié).
    </p>

    <h2 class="next-step">Par apprenant</h2>
    @if ($learners->isEmpty())
        <div class="card empty-state"><span class="icon-bubble"><x-icon name="chart" /></span><p>Aucune conversation pour le moment.</p></div>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Apprenant</th>
                    <th>Évaluation</th>
                    <th>Séances</th>
                    <th>Durée des séances</th>
                    <th>Jetons (entrée / sortie)</th>
                    <th>Coût estimé</th>
                    <th>« Mes cours » (séances, durée)</th>
                    <th>« Mes cours » : coût estimé</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($learners as $learner)
                    @php
                        $input = (int) $learner->lessons_sum_input_tokens + (int) $learner->assessment?->input_tokens;
                        $output = (int) $learner->lessons_sum_output_tokens + (int) $learner->assessment?->output_tokens;
                        $courseInput = (int) $learner->course_sessions_sum_input_tokens;
                        $courseOutput = (int) $learner->course_sessions_sum_output_tokens;
                    @endphp
                    <tr>
                        <td data-label="Apprenant">{{ $learner->name }}<br><span class="muted small">{{ $learner->phone }}</span></td>
                        <td data-label="Évaluation">
                            @if ($learner->assessment)
                                {{ $learner->assessment->status->label() }}
                                ({{ Duration::format($learner->assessment->used_seconds) }})
                                <form method="POST" action="{{ route('admin.assessments.destroy', $learner) }}" class="inline-form" {!! \App\Support\Confirmation::attributes('assessment.reset') !!}>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link small">Réinitialiser</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Séances">{{ $learner->lessons_count }}</td>
                        <td data-label="Durée des séances">{{ Duration::format((int) $learner->lessons_sum_used_seconds) }}</td>
                        <td data-label="Jetons">{{ number_format($input, 0, ',', ' ') }} / {{ number_format($output, 0, ',', ' ') }}</td>
                        <td data-label="Coût estimé">{{ UsageCost::format(UsageCost::usd($input, $output)) }}</td>
                        <td data-label="Mes cours">{{ $learner->course_sessions_count }} · {{ Duration::format((int) $learner->course_sessions_sum_used_seconds) }}</td>
                        <td data-label="Mes cours : coût estimé">{{ UsageCost::format(UsageCost::usd($courseInput, $courseOutput)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $learners->links() }}
    @endif

    @foreach (['Dernières séances' => $recentLessons, 'Dernières séances « Mes cours »' => $recentCourseSessions, 'Dernières évaluations' => $recentAssessments] as $heading => $conversations)
        <h2 class="next-step">{{ $heading }}</h2>
        @if ($conversations->isEmpty())
            <div class="card empty-state"><span class="icon-bubble"><x-icon name="chart" /></span><p>Aucune pour le moment.</p></div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Début</th>
                        <th>Apprenant</th>
                        <th>Statut</th>
                        <th>Durée réelle</th>
                        <th>Connexions</th>
                        <th>Jetons (entrée / sortie)</th>
                        <th>Coût estimé</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($conversations as $conversation)
                        <tr>
                            <td data-label="Début">{{ $conversation->started_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td data-label="Apprenant">{{ $conversation->user->name }}@if ($conversation->during_grant ?? false) <span class="badge badge-grant">Accès offert</span>@endif @if ($conversation instanceof \App\Models\CourseSession)<br><span class="muted small">{{ $conversation->lesson?->title ?? 'Leçon supprimée' }}</span>@endif</td>
                            <td data-label="Statut">
                                {{ $conversation->status->label() }}
                                @if ($conversation instanceof \App\Models\Assessment && $conversation->level)
                                    <span class="badge">{{ $conversation->level->value }}</span>
                                @endif
                            </td>
                            <td data-label="Durée réelle">{{ Duration::formatPrecise($conversation->used_seconds) }} / {{ Duration::formatPrecise($conversation->allowed_seconds) }}</td>
                            <td data-label="Connexions">{{ $conversation->connections }}</td>
                            <td data-label="Jetons">{{ number_format($conversation->input_tokens, 0, ',', ' ') }} / {{ number_format($conversation->output_tokens, 0, ',', ' ') }}</td>
                            <td data-label="Coût estimé">{{ UsageCost::format(UsageCost::usd($conversation->input_tokens, $conversation->output_tokens)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</x-layout>
