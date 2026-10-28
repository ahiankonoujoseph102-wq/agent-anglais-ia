{{--
    Fiche d'un paiement : capture (touchez pour l'agrandir), lecture de l'IA, règles,
    accès provisoire. $payment ; $actions : boutons Valider / Refuser (paiement en attente).
--}}
@php
    use App\Support\Duration;
    use App\Support\Money;

    $analysis = $payment->analysis;
    $provisional = $payment->isPending() ? $payment->provisionalSubscription() : null;
    $actions ??= false;
    $fields = [
        'operator' => 'Opérateur',
        'amount' => 'Montant',
        'currency' => 'Devise',
        'date_time' => 'Date et heure',
        'transaction_id' => 'Numéro de transaction',
        'recipient_number' => 'Numéro du destinataire',
        'recipient_name' => 'Nom du destinataire',
    ];
@endphp

<article class="card payment" id="paiement-{{ $payment->id }}">
    <div class="payment-grid">
        <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank" rel="noopener" class="proof" title="Agrandir la capture">
            <img src="{{ route('admin.payments.proof', $payment) }}" alt="Capture du paiement de {{ $payment->user->name }}" loading="lazy">
        </a>

        <div>
            <h2><a href="{{ route('admin.users.show', $payment->user) }}">{{ $payment->user->name }}</a></h2>
            <p>
                @if ($provisional)
                    <span class="badge">Accès provisoire</span>
                    encore {{ Duration::format((int) now()->diffInSeconds($provisional->provisional_until, true)) }}
                    (jusqu'au {{ $provisional->provisional_until->format('d/m/Y H:i') }})
                @elseif ($payment->isPending())
                    <span class="badge badge-muted">{{ $payment->subscription ? 'Accès provisoire terminé, en attente' : 'En attente de vérification' }}</span>
                @else
                    <span @class(['badge', 'badge-muted' => $payment->status !== \App\Enums\PaymentStatus::Validated])>{{ $payment->status->label() }}</span>
                    <span class="small muted">@include('admin.payments.reviewer', ['payment' => $payment])</span>
                @endif
            </p>
            <dl class="summary">
                <dt>Compte</dt><dd><a href="tel:{{ $payment->user->phone }}">{{ \App\Support\PhoneNumber::display($payment->user->phone) }}</a></dd>
                <dt>Formule</dt><dd>{{ $payment->plan_name }} ({{ $payment->duration_days }} jours)</dd>
                <dt>Prix</dt><dd><strong>{{ $payment->formattedAmount() }}</strong></dd>
                <dt>Opérateur choisi</dt><dd>{{ $payment->operator_name }} ({{ $payment->country }}) → {{ $payment->recipient_number }}</dd>
                @if ($payment->reference)
                    <dt>Référence déclarée</dt><dd><code>{{ $payment->reference }}</code></dd>
                @endif
                <dt>Envoyé le</dt><dd>{{ $payment->created_at->format('d/m/Y H:i') }}</dd>
                @if ($payment->rejection_reason)
                    <dt>Motif du refus</dt><dd>{{ $payment->rejection_reason }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <details class="next-step" @if ($actions) open @endif>
        <summary>
            <strong>Lecture de la capture par l'IA</strong>
            {{-- Avant le secours OpenAI, toutes les lectures étaient faites par Gemini. --}}
            @if ($analysis !== null)
                <span class="badge" data-read-by>Lu par {{ \App\Support\Text\TextProviders::find($analysis['provider'] ?? 'gemini')?->label() ?? $analysis['provider'] }}</span>
            @endif
        </summary>
        @if ($analysis === null)
            <p class="muted">Aucune lecture : l'IA n'a pas répondu{{ $payment->checks === null ? ' (paiement de l\'ancien parcours)' : '' }}. Vérifiez la capture vous-même.</p>
        @else
            <dl class="summary">
                <dt>Lisible</dt><dd>{{ $analysis['readable'] ?? false ? 'Oui' : 'Non' }}</dd>
                @foreach ($fields as $key => $label)
                    <dt>{{ $label }}</dt>
                    <dd>
                        @if (($analysis[$key] ?? null) === null)
                            <span class="muted">non lu</span>
                        @elseif ($key === 'amount')
                            {{ Money::format((int) $analysis[$key], '') }}
                        @else
                            {{ $analysis[$key] }}
                        @endif
                    </dd>
                @endforeach
            </dl>
        @endif

        @if ($payment->checks)
            <ul class="checks next-step">
                @foreach ($payment->checks as $check)
                    <li>
                        @if ($check['passed'])
                            <x-icon name="check-circle" class="ok" label="Règle remplie" />
                        @else
                            <x-icon name="alert" class="ko" label="Règle non remplie" />
                        @endif
                        <div><strong>{{ $check['label'] }}</strong><br><span class="small muted">{{ $check['reason'] }}</span></div>
                    </li>
                @endforeach
            </ul>
        @endif
    </details>

    @if ($actions && $payment->isPending())
        <div class="payment-actions">
            <form method="POST" action="{{ route('admin.payments.approve', $payment) }}" {!! \App\Support\Confirmation::attributes('payment.approve') !!}>
                @csrf
                <button type="submit" class="btn btn-block">Valider</button>
            </form>

            <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" {!! \App\Support\Confirmation::attributes('payment.reject') !!}>
                @csrf
                @php
                    $bag = $errors->getBag('reject-'.$payment->id);
                @endphp
                <label for="reason-{{ $payment->id }}">Motif du refus (facultatif, visible par l'apprenant)</label>
                <textarea id="reason-{{ $payment->id }}" name="reason" rows="2" maxlength="500"
                          @class(['is-invalid' => $bag->has('reason')])>{{ $bag->any() ? old('reason') : '' }}</textarea>
                @if ($bag->has('reason'))
                    <div class="error">{{ $bag->first('reason') }}</div>
                @endif
                <button type="submit" class="btn btn-danger btn-block">Refuser</button>
            </form>
        </div>
    @endif
</article>
