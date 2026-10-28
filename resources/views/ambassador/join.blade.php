{{--
    Page publique « Devenir ambassadeur » (AmbassadorJoinController). Compteur RÉEL : places de « Réglages » moins
    les ambassadeurs actifs (AmbassadorProgram::freePlaces), jamais un nombre écrit à la main. Plus de place :
    formulaire fermé, liste d'attente et lien vers les formules. Candidature : compte Talkami obligatoire.
    Montants, durées et pourcentage lus dans la configuration. Aucun chiffre inventé, aucune promesse de gain.
--}}
@php
    use App\Support\Money;

    $platform = config('platform.name');
    $teacher = config('avatar.model.name');
    $percent = config('platform.ambassadors.commission_percent');
    $days = config('platform.ambassadors.commission_days');
    $monthDays = config('platform.ambassadors.month_days');
    $maxMonths = config('platform.ambassadors.max_months');
    $linkDays = config('platform.ambassadors.link_days');
@endphp

<x-layout title="Devenir ambassadeur, programme de parrainage"
          :description="'Programme d\'ambassadeurs de '.$platform.' : accès gratuit à la formule '.$plan?->name.' et '.$percent.' % du premier paiement des personnes inscrites grâce à vous.'">
    <section class="section page-hero" aria-labelledby="ambassadeur-titre">
        <span class="eyebrow"><x-icon name="award" /> Programme d'ambassadeurs</span>
        <h1 id="ambassadeur-titre">Devenir ambassadeur de {{ $platform }}</h1>
        <p class="lead">Vous apprenez l'anglais avec {{ $teacher }} et vous voulez en parler autour de vous ? Devenez ambassadeur : vous apprenez gratuitement et vous pouvez recevoir une commission.</p>
        <p class="places-counter" id="places">
            @if ($free > 0)
                <x-icon name="users" /> <strong>Il reste {{ $free }} {{ $free > 1 ? 'places' : 'place' }} sur {{ $places }}.</strong>
            @else
                <x-icon name="users" /> <strong>Toutes les places d'ambassadeurs sont prises.</strong>
            @endif
        </p>
    </section>

    <section class="section" aria-labelledby="ambassadeur-recoit">
        <h2 id="ambassadeur-recoit">Ce que vous recevez</h2>
        <ul class="check-list check-list-plain">
            <li><x-icon name="gift" /> Un accès gratuit à la formule {{ $plan?->name }} ({{ $plan?->dailyMinutes }} minutes par jour), {{ $monthDays }} jours renouvelés par notre équipe, jusqu'à {{ $maxMonths }} mois.</li>
            <li><x-icon name="wallet" /> {{ $percent }} % du premier paiement de chaque personne inscrite grâce à vous, s'il est validé dans les {{ $days }} jours après son inscription. Par exemple :
                @foreach ($examples as $example){{ Money::format($example['amount']) }} pour la formule {{ $example['name'] }}{{ $loop->last ? '.' : ', ' }}@endforeach
                Versé par Mobile Money.</li>
            <li><x-icon name="share" /> Votre lien, votre code et votre code QR personnels, dans votre espace ambassadeur.</li>
        </ul>
    </section>

    <section class="section" aria-labelledby="ambassadeur-fait">
        <h2 id="ambassadeur-fait">Ce que vous faites</h2>
        <ul class="check-list check-list-plain">
            <li><x-icon name="mic" /> Vous suivez vos séances avec {{ $teacher }} régulièrement.</li>
            <li><x-icon name="users" /> Vous parlez de {{ $platform }} autour de vous et partagez votre lien ou votre code.</li>
            <li><x-icon name="whatsapp" /> Vous suivez le groupe WhatsApp des ambassadeurs : c'est là que se fait le suivi avec notre équipe.</li>
            <li><x-icon name="shield" /> Vous restez honnête : une inscription ou un paiement arrangé annule la commission et peut mettre fin à votre place.</li>
        </ul>
        <p class="muted small">Moins de 18 ans : l'accord écrit d'un parent vous sera demandé. Notre équipe choisit les ambassadeurs parmi les candidatures.</p>
    </section>

    <section class="section" id="regles" aria-labelledby="ambassadeur-regles">
        <h2 id="ambassadeur-regles">Les règles</h2>
        <ul class="check-list check-list-plain">
            <li><x-icon name="wallet" /> <strong>Seulement le premier paiement.</strong> La commission porte sur le premier paiement validé de chaque personne inscrite grâce à vous, jamais sur ses paiements suivants.</li>
            <li><x-icon name="share" /> <strong>Le code tapé, puis le premier lien.</strong> Le code ambassadeur tapé à l'inscription passe avant tout. Sans code, c'est le premier lien d'ambassadeur utilisé qui compte (gardé {{ $linkDays }} jours), même si la personne en ouvre un autre ensuite.</li>
            <li><x-icon name="users" /> <strong>Un seul niveau.</strong> Aucune commission sur les personnes inscrites par vos inscrits : seules comptent celles qui s'inscrivent grâce à vous.</li>
        </ul>
        <p class="muted small">Aucun revenu n'est promis : vous recevez une commission seulement quand une personne inscrite grâce à vous paie et que son paiement est validé.</p>
    </section>

    <section class="section" id="candidature" aria-labelledby="candidature-titre">
        <h2 id="candidature-titre">{{ in_array($state, ['full', 'full_guest', 'waiting'], true) ? 'Toutes les places sont prises' : 'Poser ma candidature' }}</h2>

        @error('application')
            <p class="alert alert-error" role="alert"><x-icon name="alert" /><span>{{ $message }}</span></p>
        @enderror

        @switch($state)
            @case('ambassador')
                <div class="card">
                    <p>Vous êtes déjà ambassadeur de {{ $platform }}.</p>
                    <a class="btn btn-accent btn-lg" href="{{ route('ambassador.show') }}"><x-icon name="award" /> Ouvrir mon espace ambassadeur</a>
                </div>
                @break

            @case('admin')
                <div class="card">
                    <p>Les candidatures se gèrent dans l'administration.</p>
                    <a class="btn btn-outline" href="{{ route('admin.ambassadors.index') }}#candidatures">Voir les candidatures</a>
                </div>
                @break

            @case('pending')
                <div class="card">
                    <p><strong>Votre candidature est envoyée.</strong> Notre équipe l'examine et vous répond sur WhatsApp.</p>
                    <p class="muted small">Envoyée le {{ $application->created_at->format('d/m/Y') }}.</p>
                </div>
                @break

            @case('guest')
                <div class="card">
                    <p>Pour poser votre candidature, il faut un compte {{ $platform }}.</p>
                    <div class="actions actions-row">
                        <a class="btn btn-accent btn-lg btn-block" href="{{ route('ambassador.join.form') }}"><x-icon name="login" /> Me connecter pour poser ma candidature</a>
                        <a class="btn btn-outline btn-block" href="{{ route('register') }}"><x-icon name="user-plus" /> Créer mon compte</a>
                    </div>
                </div>
                @break

            @case('open')
                <form method="POST" action="{{ route('ambassador.apply') }}" class="card">
                    @csrf
                    @if ($application?->status === \App\Enums\ApplicationStatus::Rejected)
                        <p class="muted small">Votre candidature précédente n'a pas été retenue. Vous pouvez en envoyer une nouvelle.</p>
                    @endif
                    <div class="field">
                        <label for="motivation">Pourquoi voulez-vous être ambassadeur ?</label>
                        <textarea id="motivation" name="motivation" rows="4" maxlength="1000" required
                                  @error('motivation') aria-invalid="true" aria-describedby="motivation-error" @enderror
                                  @class(['is-invalid' => $errors->has('motivation')])>{{ old('motivation', $application?->motivation) }}</textarea>
                        @error('motivation') <div class="error" id="motivation-error">{{ $message }}</div> @enderror
                    </div>
                    <x-input name="channels" label="À qui parlerez-vous de {{ $platform }} ?" maxlength="300" required
                             :value="$application?->channels" hint="Par exemple : ma classe, mon université, mon église, mes collègues, ma famille, mes réseaux." />
                    <label class="checkbox option">
                        <input type="checkbox" name="minor" value="1" @checked(old('minor', $application?->minor))>
                        J'ai moins de 18 ans
                    </label>
                    <label class="checkbox option">
                        <input type="checkbox" name="rules" value="1" required @error('rules') aria-invalid="true" @enderror>
                        J'accepte les règles du programme décrites sur cette page
                    </label>
                    @error('rules') <div class="error">{{ $message }}</div> @enderror
                    <button type="submit" class="btn btn-accent btn-lg btn-block"><x-icon name="award" /> Envoyer ma candidature</button>
                </form>
                @break

            @case('waiting')
                <div class="card">
                    <p><strong>Vous êtes sur la liste d'attente.</strong> Nous vous prévenons sur WhatsApp dès qu'une place se libère.</p>
                    <form method="POST" action="{{ route('ambassador.waitlist.leave') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-light">Quitter la liste d'attente</button>
                    </form>
                    <p><a href="{{ route('plans') }}">Voir les formules</a></p>
                </div>
                @break

            @default
                {{-- full ou full_guest : formulaire fermé. --}}
                <div class="card">
                    <p>Le formulaire de candidature est fermé. Une place se libère parfois : nous pouvons vous prévenir.</p>
                    @if ($state === 'full')
                        <form method="POST" action="{{ route('ambassador.waitlist') }}">
                            @csrf
                            <button type="submit" class="btn btn-accent btn-lg btn-block"><x-icon name="calendar-clock" /> Me prévenir quand une place se libère</button>
                        </form>
                    @else
                        <a class="btn btn-accent btn-lg btn-block" href="{{ route('ambassador.join.form') }}"><x-icon name="login" /> Me connecter pour être prévenu</a>
                    @endif
                    <p>En attendant, vous pouvez apprendre avec {{ $teacher }} : <a href="{{ route('plans') }}">voir les formules</a>.</p>
                </div>
        @endswitch
    </section>
</x-layout>
