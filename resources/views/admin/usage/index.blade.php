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

    <h2 class="next-step">Par apprenant</h2>
    @if ($learners->isEmpty())
        <div class="card">Aucune conversation pour le moment.</div>
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
                </tr>
            </thead>
            <tbody>
                @foreach ($learners as $learner)
                    @php
                        $input = (int) $learner->lessons_sum_input_tokens + (int) $learner->assessment?->input_tokens;
                        $output = (int) $learner->lessons_sum_output_tokens + (int) $learner->assessment?->output_tokens;
                    @endphp
                    <tr>
                        <td data-label="Apprenant">{{ $learner->name }}<br><span class="muted small">{{ $learner->phone }}</span></td>
                        <td data-label="Évaluation">
                            @if ($learner->assessment)
                                {{ $learner->assessment->status->label() }}
                                ({{ Duration::format($learner->assessment->used_seconds) }})
                                <form method="POST" action="{{ route('admin.assessments.destroy', $learner) }}" class="inline-form"
                                      onsubmit="return confirm('Permettre à cet apprenant de repasser l\'évaluation ?')">
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
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $learners->links() }}
    @endif

    @foreach (['Dernières séances' => $recentLessons, 'Dernières évaluations' => $recentAssessments] as $heading => $conversations)
        <h2 class="next-step">{{ $heading }}</h2>
        @if ($conversations->isEmpty())
            <div class="card">Aucune pour le moment.</div>
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
                            <td data-label="Apprenant">{{ $conversation->user->name }}</td>
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
