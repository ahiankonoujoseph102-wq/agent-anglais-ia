<x-layout title="Mon espace" install>
    <div class="welcome rise">
        <img src="{{ asset('icons/apple-touch-icon.png') }}" width="64" height="64" alt="">
        <div>
            <h1>Bonjour {{ $user->name }}</h1>
            <p>Votre professeur {{ config('avatar.model.name') }} est prêt.</p>
        </div>
    </div>

    {{-- Où en est l'apprenant dans le parcours : évaluation, formule, programme, séances. --}}
    <x-stepper :labels="['Évaluation', 'Formule', 'Programme', 'Séances']" :current="$journeyStep" label="Votre parcours" />

    {{-- Prochaine étape : TOUJOURS la première carte, une seule action principale (bouton orange). --}}
    <section class="card next-card rise" aria-labelledby="next-step-title" style="--i: 1">
        <span class="next-card-eyebrow">Prochaine étape</span>
        @if ($needsAssessment && ! $subscription)
            <h2 id="next-step-title">Passez votre évaluation gratuite</h2>
            <p>
                Parlez {{ $assessmentMinutes }} minutes au maximum avec {{ config('avatar.model.name') }}
                pour connaître votre niveau d'anglais. C'est gratuit et sans engagement.
            </p>
            <a href="{{ route('assessment.show') }}" class="btn btn-accent btn-lg btn-block"><x-icon name="mic" /> Parler avec {{ config('avatar.model.name') }}</a>
        @elseif ($pendingPayment && ! $subscription)
            <h2 id="next-step-title">Nous vérifions votre paiement</h2>
            <p>
                {{ $pendingPayment->plan_name }}, {{ $pendingPayment->formattedAmount() }}, envoyé le
                {{ $pendingPayment->created_at->translatedFormat('j F à H:i') }}. Votre formule sera activée ici dès la vérification :
                vous n'avez rien d'autre à faire.
            </p>
            <a href="{{ route('payment.show', $pendingPayment) }}" class="btn btn-lg btn-block">Suivre mon paiement <x-icon name="arrow-right" /></a>
        @elseif (! $subscription)
            @if ($expiredSubscription)
                <h2 id="next-step-title">Votre abonnement a expiré</h2>
                <p>
                    Votre formule {{ $expiredSubscription->plan_name }} a pris fin le
                    {{ $expiredSubscription->ends_at->translatedFormat('j F Y') }}. Vos séances sont en pause ;
                    renouvelez votre formule : votre niveau et votre programme vous attendent.
                </p>
                <a href="{{ route('payment.plans') }}" class="btn btn-lg btn-block">Renouveler <x-icon name="arrow-right" /></a>
            @else
                <h2 id="next-step-title">Choisissez votre formule</h2>
                <p>
                    @if ($user->level)
                        Votre niveau : {{ $user->level->value }} ({{ $user->level->label() }}).
                    @endif
                    Choisissez une formule et payez par Mobile Money : vos séances orales quotidiennes commencent tout de suite après.
                </p>
                <a href="{{ route('payment.plans') }}" class="btn btn-lg btn-block">Choisir ma formule <x-icon name="arrow-right" /></a>
            @endif
        @elseif (! $program && ! $attestations->contains('level', \App\Enums\Level::C2))
            <h2 id="next-step-title">Construisons votre programme</h2>
            <p>Répondez à 4 petites questions : {{ config('avatar.model.name') }} prépare un programme adapté à votre objectif et à vos jours de travail.</p>
            <a href="{{ route('program.questionnaire', 1) }}" class="btn btn-lg btn-block">Construire mon programme <x-icon name="arrow-right" /></a>
        @elseif ($program?->status === \App\Enums\ProgramStatus::LevelTestDue)
            <h2 id="next-step-title">Bilan de fin de niveau {{ $program->level->value }}</h2>
            <p>Vous avez terminé les 12 unités ! Passez le bilan oral avec le professeur pour obtenir votre attestation et passer au niveau suivant.</p>
            <a href="{{ route('level-test.show') }}" class="btn btn-lg btn-block"><x-icon name="award" /> Passer mon bilan</a>
        @else
            <h2 id="next-step-title">Votre séance du jour</h2>
            @if ($currentLesson)
                <p>
                    Unité {{ $currentLesson->unit_number }} · {{ $currentLesson->unit()->title }} —
                    leçon {{ $currentLesson->lesson_number }} : {{ $currentLesson->focusLabel() }}
                </p>
            @endif
            @if ($remainingSeconds >= $minStartSeconds)
                <p>{{ \App\Support\Duration::format($remainingSeconds) }} de séance orale disponibles aujourd'hui.</p>
                <a href="{{ route('lesson.show') }}" class="btn btn-lg btn-block"><x-icon name="mic" /> Parler avec {{ config('avatar.model.name') }}</a>
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

        {{-- Accès provisoire : séances ouvertes pendant la vérification du paiement, avec le temps restant. --}}
        @if ($subscription?->isProvisional() && $pendingPayment)
            <p class="next-card-note">
                <x-icon name="clock" />
                <span>
                    <strong>Accès provisoire : encore {{ \App\Support\Duration::format(max(1, (int) ceil(now()->diffInSeconds($subscription->provisional_until) / 60)) * 60) }}</strong>
                    (jusqu'au {{ $subscription->provisional_until->translatedFormat('j F à H:i') }}), pendant que notre équipe vérifie votre paiement.
                    <a href="{{ route('payment.show', $pendingPayment) }}">Voir mon paiement</a>
                </span>
            </p>
        @endif
    </section>

    {{-- Abonné sans évaluation (réinitialisée par l'administrateur) : invitation juste après la prochaine étape. --}}
    @if ($needsAssessment && $subscription)
        <section class="card next-step rise" aria-labelledby="assessment-invite">
            <div class="card-title"><span class="icon-bubble icon-bubble-primary"><x-icon name="mic" /></span><h2 id="assessment-invite">Passez votre évaluation gratuite</h2></div>
            <p>{{ $assessmentMinutes }} minutes au maximum avec {{ config('avatar.model.name') }} pour mesurer votre niveau.</p>
            <a href="{{ route('assessment.show') }}" class="btn btn-accent btn-block"><x-icon name="mic" /> Parler avec {{ config('avatar.model.name') }}</a>
        </section>
    @endif

    @if ($rejectedPayment && ! $pendingPayment)
        <div class="alert alert-error rise" role="alert">
            <x-icon name="alert" />
            <div>
                <strong>Nous n'avons pas pu valider votre paiement du {{ $rejectedPayment->created_at->translatedFormat('j F') }}.</strong><br>
                @if ($rejectedPayment->rejection_reason)
                    Motif : {{ $rejectedPayment->rejection_reason }}<br>
                @endif
                <a href="{{ route('payment.show', $rejectedPayment) }}">Voir mon paiement et nous écrire</a>
            </div>
        </div>
    @endif

    @if ($renewalDue && ! $pendingPayment)
        <div class="alert alert-warning rise" role="status">
            <x-icon name="calendar" />
            <div>
                <strong>Votre formule se termine le {{ $subscription->ends_at->translatedFormat('j F Y à H:i') }}.</strong>
                Renouvelez-la pour continuer vos séances sans interruption : la nouvelle période commencera à cette date.
                <a class="btn btn-block next-step" href="{{ route('payment.plans') }}">Renouveler</a>
            </div>
        </div>
    @endif

    @if ($nextSubscription)
        <p class="muted">
            Prochaine formule déjà payée : {{ $nextSubscription->plan_name }}, du
            {{ $nextSubscription->starts_at->translatedFormat('j F Y') }} au {{ $nextSubscription->ends_at->translatedFormat('j F Y') }}.
        </p>
    @endif

    <div class="stats stats-main">
        <div class="stat rise" style="--i: 2">
            <div class="label"><x-icon name="level" /> Mon niveau</div>
            {{-- Évaluation à (re)passer : aucun ancien niveau affiché comme niveau actuel. --}}
            @if ($user->level && ! $needsAssessment)
                <div class="value">{{ $user->level->value }}</div>
                <div class="detail">{{ $user->level->label() }} · <a href="{{ route('assessment.show') }}">Mon résultat</a></div>
            @elseif (! $needsAssessment)
                <div class="value">—</div>
                <div class="detail"><a href="{{ route('assessment.show') }}">Choisir mon niveau</a></div>
            @else
                <div class="value">—</div>
                <div class="detail">Pas encore évalué</div>
            @endif
        </div>

        <div class="stat rise" style="--i: 3">
            <div class="label"><x-icon name="money" /> Ma formule</div>
            @if ($subscription)
                <div class="value">{{ $subscription->plan_name }}</div>
                <div class="detail">
                    {{ $subscription->daily_minutes }} minutes par jour,
                    jusqu'au {{ $subscription->ends_at->translatedFormat('j F Y') }}
                </div>
            @else
                <div class="value">Aucune</div>
                <div class="detail">Pas d'abonnement actif</div>
            @endif
        </div>

        <div class="stat rise" style="--i: 4">
            <div class="label"><x-icon name="clock" /> Temps restant aujourd'hui</div>
            @if ($remainingSeconds !== null)
                <div class="value">{{ \App\Support\Duration::format($remainingSeconds) }}</div>
                <div class="detail">sur {{ $subscription->daily_minutes }} minutes</div>
            @else
                <div class="value">—</div>
                <div class="detail">Disponible avec un abonnement</div>
            @endif
        </div>

        <div class="stat rise" style="--i: 5">
            <div class="label"><x-icon name="chart" /> Ma progression</div>
            @if ($program)
                <div class="value">{{ $progress }} %</div>
                <div class="progress" role="progressbar" aria-label="Progression du niveau {{ $program->level->value }}" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progress }}%"></span></div>
                <div class="detail">du niveau {{ $program->level->value }} · <a href="{{ route('program.show') }}">Mon programme</a></div>
            @else
                <div class="value">—</div>
                <div class="detail">Avec votre programme personnel</div>
            @endif
        </div>
    </div>

    @if ($assessment?->feedback)
        <section class="card next-step reveal">
            <div class="card-title"><span class="icon-bubble icon-bubble-accent"><x-icon name="badge-check" /></span><h2>Votre évaluation</h2></div>
            <p>{{ $assessment->feedback }}</p>
        </section>
    @endif

    @if ($lastLevelTest?->passed === false && $program?->status === \App\Enums\ProgramStatus::Active)
        <section class="card next-step reveal">
            <h2>Résultat de votre bilan de niveau {{ $lastLevelTest->level->value }}</h2>
            <p>{{ $lastLevelTest->feedback }}</p>
            <p><strong>Unités à reprendre :</strong> {{ implode(', ', $lastLevelTest->units_to_review ?? []) }}. Elles ont été replacées dans votre calendrier.</p>
        </section>
    @endif

    @if ($program || $streak > 0)
        <section class="next-step">
            <h2 class="next-step">Mon suivi</h2>
            <div class="stats">
                <div class="stat reveal">
                    <div class="label"><x-icon name="fire" /> Série en cours</div>
                    <div class="value">{{ $streak }} {{ $streak > 1 ? 'jours' : 'jour' }}</div>
                    <div class="detail">de séances consécutives</div>
                </div>
                <div class="stat reveal" style="--i: 1">
                    <div class="label"><x-icon name="edit" /> Erreurs à revoir</div>
                    <div class="value">{{ $openErrorsCount }}</div>
                    <div class="detail"><a href="{{ route('lessons.history') }}">Mes séances et mes erreurs</a></div>
                </div>
            </div>

            @if (collect($skills)->filter()->isNotEmpty())
                <div class="card next-step reveal">
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
                <div class="card reveal">
                    <h3>Erreurs en cours</h3>
                    @foreach ($openErrors as $error)
                        <p class="error-item"><span class="wrong" lang="en">{{ $error->wrong }}</span> → <strong lang="en">{{ $error->correct }}</strong></p>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if ($attestations->isNotEmpty())
        <section class="card next-step reveal">
            <div class="card-title"><span class="icon-bubble icon-bubble-accent"><x-icon name="award" /></span><h2>Mes attestations de niveau</h2></div>
            @foreach ($attestations as $attestation)
                <p>
                    Niveau {{ $attestation->level->value }} ({{ $attestation->level->label() }}), le {{ $attestation->issued_at->translatedFormat('j F Y') }}
                    — <a href="{{ route('attestation.download', $attestation) }}"><x-icon name="download" /> Télécharger l'attestation</a>
                </p>
            @endforeach
        </section>
    @endif

    <p class="center next-step"><a href="{{ route('account.edit') }}"><x-icon name="user" /> Mon compte et mon adresse e-mail</a></p>
</x-layout>
