<x-layout title="Mon espace">
    <h1>Bonjour {{ $user->name }}</h1>

    <div class="stats">
        <div class="stat">
            <div class="label">Mon niveau</div>
            @if ($user->level)
                <div class="value">{{ $user->level->value }}</div>
                <div class="detail">{{ $user->level->label() }}</div>
            @else
                <div class="value">—</div>
                <div class="detail">Pas encore évalué</div>
            @endif
        </div>

        <div class="stat">
            <div class="label">Ma formule</div>
            @if ($subscription)
                <div class="value">{{ $subscription->plan_name }}</div>
                <div class="detail">
                    {{ $subscription->daily_minutes }} min par jour,
                    jusqu'au {{ $subscription->ends_at->translatedFormat('j F Y') }}
                </div>
            @else
                <div class="value">Aucune</div>
                <div class="detail">Pas d'abonnement actif</div>
            @endif
        </div>

        <div class="stat">
            <div class="label">Temps restant aujourd'hui</div>
            @if ($remainingSeconds !== null)
                <div class="value">{{ \App\Support\Duration::format($remainingSeconds) }}</div>
                <div class="detail">sur {{ $subscription->daily_minutes }} min</div>
            @else
                <div class="value">—</div>
                <div class="detail">Disponible avec un abonnement</div>
            @endif
        </div>
    </div>

    @if ($rejectedPayment)
        <div class="alert alert-error next-step" role="alert">
            <strong>Votre paiement du {{ $rejectedPayment->created_at->translatedFormat('j F') }} a été refusé.</strong><br>
            Motif : {{ $rejectedPayment->rejection_reason }}<br>
            <a href="{{ route('payment.create', $rejectedPayment->plan) }}">Renvoyer un paiement corrigé</a>
        </div>
    @endif

    @if ($pendingPayment)
        <div class="alert alert-info next-step" role="status">
            <strong>Paiement en attente de validation</strong> ({{ $pendingPayment->plan_name }}, {{ $pendingPayment->formattedAmount() }}).
            <a href="{{ route('payment.show', $pendingPayment) }}">Voir mon paiement</a>
        </div>
    @elseif ($renewalDue)
        <div class="alert alert-warning next-step" role="status">
            <strong>Votre formule se termine le {{ $subscription->ends_at->translatedFormat('j F Y à H:i') }}.</strong>
            Renouvelez-la pour continuer vos séances sans interruption : la nouvelle période commencera à cette date.
            <a class="btn btn-block next-step" href="{{ route('payment.plans') }}">Renouveler</a>
        </div>
    @endif

    @if ($nextSubscription)
        <p class="muted next-step">
            Prochaine formule déjà payée : {{ $nextSubscription->plan_name }}, du
            {{ $nextSubscription->starts_at->translatedFormat('j F Y') }} au {{ $nextSubscription->ends_at->translatedFormat('j F Y') }}.
        </p>
    @endif

    @if (! $user->level && ! $canTakeAssessment)
        <div class="alert alert-error next-step">
            Votre évaluation gratuite s'est arrêtée avant que le professeur n'annonce votre niveau.
            Contactez-nous pour la repasser.
        </div>
    @endif

    @if ($assessment?->feedback)
        <section class="card next-step">
            <h2>Votre évaluation</h2>
            <p>{{ $assessment->feedback }}</p>
        </section>
    @endif

    <section class="card next-step">
        @if (! $user->level && $canTakeAssessment)
            <h2>Votre évaluation gratuite</h2>
            <p>
                Parlez {{ $assessmentMinutes }} minutes au maximum avec {{ config('platform.name') }}
                pour connaître votre niveau d'anglais.
            </p>
            <a href="{{ route('assessment.show') }}" class="btn">Passer mon évaluation</a>
        @elseif (! $subscription && $expiredSubscription)
            <h2>Votre abonnement a expiré</h2>
            <p>
                Votre formule {{ $expiredSubscription->plan_name }} a pris fin le
                {{ $expiredSubscription->ends_at->translatedFormat('j F Y') }}. Vos séances sont en pause :
                renouvelez votre abonnement pour les reprendre. Votre évaluation et votre niveau restent disponibles.
            </p>
            @unless ($pendingPayment)
                <a href="{{ route('payment.plans') }}" class="btn">Renouveler</a>
            @endunless
        @elseif (! $subscription)
            <h2>Continuez avec {{ config('platform.name') }}</h2>
            <p>Choisissez une formule pour commencer vos séances quotidiennes.</p>
            @unless ($pendingPayment)
                <a href="{{ route('payment.plans') }}" class="btn">Choisir ma formule</a>
            @endunless
        @elseif (! $program && ! $attestations->contains('level', \App\Enums\Level::C2))
            <h2>Construisons votre programme</h2>
            <p>Votre abonnement est actif. Répondez à 4 petites questions : le professeur prépare un programme adapté à votre objectif et à vos jours de travail.</p>
            <a href="{{ route('program.questionnaire', 1) }}" class="btn">Construire mon programme</a>
        @elseif ($program?->status === \App\Enums\ProgramStatus::LevelTestDue)
            <h2>Bilan de fin de niveau {{ $program->level->value }}</h2>
            <p>Vous avez terminé les 12 unités ! Passez le bilan oral avec le professeur pour obtenir votre attestation et passer au niveau suivant.</p>
            <a href="{{ route('level-test.show') }}" class="btn">Passer mon bilan</a>
        @else
            <h2>Ma séance du jour</h2>
            @if ($currentLesson)
                <p class="muted">
                    Unité {{ $currentLesson->unit_number }} · {{ $currentLesson->unit()->title }} —
                    leçon {{ $currentLesson->lesson_number }} : {{ $currentLesson->focusLabel() }}
                </p>
            @endif
            @if ($remainingSeconds >= $minStartSeconds)
                <p>Votre professeur vous attend.</p>
                <a href="{{ route('lesson.show') }}" class="btn">Commencer ma séance</a>
            @else
                <p>Votre temps de séance est épuisé pour aujourd'hui. Revenez demain !</p>
            @endif
            @if ($lastLesson?->summary)
                <h3 class="next-step">Dernière séance</h3>
                <p>{{ $lastLesson->summary }}</p>
                @if ($lastLesson->homework)
                    <p><strong>Devoir :</strong> {{ $lastLesson->homework }}</p>
                @endif
            @endif
        @endif
    </section>

    @if ($lastLevelTest?->passed === false && $program?->status === \App\Enums\ProgramStatus::Active)
        <section class="card next-step">
            <h2>Résultat de votre bilan de niveau {{ $lastLevelTest->level->value }}</h2>
            <p>{{ $lastLevelTest->feedback }}</p>
            <p><strong>Unités à reprendre :</strong> {{ implode(', ', $lastLevelTest->units_to_review ?? []) }}. Elles ont été replacées dans votre calendrier.</p>
        </section>
    @endif

    @if ($program || $streak > 0)
        <section class="next-step">
            <h2>Mon suivi</h2>
            <div class="stats">
                @if ($program)
                    <div class="stat">
                        <div class="label">Progression du niveau {{ $program->level->value }}</div>
                        <div class="value">{{ $progress }} %</div>
                        <div class="progress" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progress }}%"></span></div>
                        <div class="detail"><a href="{{ route('program.show') }}">Voir mon programme et mon calendrier</a></div>
                    </div>
                @endif
                <div class="stat">
                    <div class="label">Série en cours</div>
                    <div class="value">{{ $streak }} {{ $streak > 1 ? 'jours' : 'jour' }}</div>
                    <div class="detail">de séances consécutives</div>
                </div>
                <div class="stat">
                    <div class="label">Erreurs à revoir</div>
                    <div class="value">{{ $openErrorsCount }}</div>
                    <div class="detail"><a href="{{ route('lessons.history') }}">Mes séances et mes erreurs</a></div>
                </div>
            </div>

            @if (collect($skills)->filter()->isNotEmpty())
                <div class="card next-step">
                    <h3>Mes compétences <span class="muted small">(moyenne des 10 dernières séances, sur 5)</span></h3>
                    <ul class="skills-chart">
                        @foreach (\App\Enums\Skill::cases() as $skill)
                            @php
                                $score = $skills[$skill->value];
                            @endphp
                            <li title="{{ $skill->label() }} : {{ $score !== null ? number_format($score, 1, ',', '') : '—' }} sur 5">
                                <span class="skill-name">{{ $skill->label() }}</span>
                                <span class="skill-track"><span class="skill-bar" style="width: {{ $score ? $score * 20 : 0 }}%"></span></span>
                                <span class="skill-value">{{ $score !== null ? number_format($score, 1, ',', '') : '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($openErrors->isNotEmpty())
                <div class="card">
                    <h3>Erreurs en cours</h3>
                    @foreach ($openErrors as $error)
                        <p class="error-item"><span class="wrong" lang="en">{{ $error->wrong }}</span> → <strong lang="en">{{ $error->correct }}</strong></p>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if ($attestations->isNotEmpty())
        <section class="card next-step">
            <h2>Mes attestations de niveau</h2>
            @foreach ($attestations as $attestation)
                <p>
                    Niveau {{ $attestation->level->value }} ({{ $attestation->level->label() }}), le {{ $attestation->issued_at->translatedFormat('j F Y') }}
                    — <a href="{{ route('attestation.download', $attestation) }}">Télécharger l'attestation</a>
                </p>
            @endforeach
        </section>
    @endif
</x-layout>
