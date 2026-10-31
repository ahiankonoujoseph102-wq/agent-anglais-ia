<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Asset;
use App\Support\Avatar;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Greeting;
use App\Support\Speech\LightAudio;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use App\Support\WelcomeFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Page de bienvenue de Teacher Joe, à la première ouverture d'un accès : message vocal qui explique la
 * formule, le questionnaire, la séance orale et « Mes cours ». Montrée automatiquement une seule fois,
 * réécoutable depuis « Mon espace » (?reecouter=1) par un apprenant qui a eu un accès.
 *
 * Avec plusieurs professeurs proposés : « Avec qui voulez-vous apprendre ? » (une carte par professeur : photo et
 * nom). La page reçoit le message, la voix et le personnage de CHAQUE professeur : toucher l'autre carte change tout
 * aussitôt, sans rechargement, et le choix est enregistré en arrière-plan. Jamais de choix sur la page de paiement.
 */
class WelcomeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (WelcomeFlow::due($user)) {
            WelcomeFlow::markSeen($user);
        } elseif (! $request->boolean('reecouter')) {
            return to_route('dashboard');
        }

        // Réécoute : rien n'est marqué « vu » ; sans aucun accès, rien à réécouter.
        $texts = WelcomeMessage::texts($user);

        if ($texts === null) {
            return to_route('dashboard');
        }

        $current = $texts['teacher'];
        $programReady = $user->activeProgram()->exists();

        // Chaque professeur proposé arrive avec son message, sa voix et son visage : changer de professeur sur cette
        // page est immédiat, sans rechargement (public/js/welcome.js) ; le choix est enregistré en arrière-plan.
        $teachers = [];

        foreach (Teachers::hasChoice() ? Teachers::choosable() : [$current->key => $current] as $key => $teacher) {
            $teachers[$key] = $this->teacherData($user, $teacher->key === $current->key ? $texts : WelcomeMessage::texts($user, $texts['period'], $teacher), $programReady);
        }

        return view('welcome.show', [
            'firstName' => $texts['first_name'],
            'firstVisit' => ! $request->boolean('reecouter'),
            'planName' => $texts['plan'],
            'teacher' => $current,
            'teachers' => $teachers,
            'current' => $teachers[$current->key],
            'hasChoice' => count($teachers) > 1,
        ]);
    }

    /**
     * Ce que la page doit savoir d'un professeur : ses morceaux (texte, adresse, prêt ou bientôt prêt), ses textes,
     * son bouton de fin et son personnage 3D.
     *
     * @return array<string, mixed>
     */
    private function teacherData(User $user, array $texts, bool $programReady): array
    {
        /** @var Teacher $teacher */
        $teacher = $texts['teacher'];

        // UNE seule voix pour tout le message : le fournisseur chez qui il est prêt dit aussi la salutation.
        $message = WelcomeMessage::message($texts);
        $files = $message->files();
        $provider = $message->provider($files);
        $openingReady = $provider !== null && WelcomeMessage::openingReady($texts, $provider);

        // Salutation au prénom pas encore gardée (autre moment de la journée, autre professeur) : préparée APRÈS
        // cette réponse ; la page la redemande quelques fois et, si elle n'est pas là au toucher, commence sans elle.
        if ($provider !== null && ! $openingReady) {
            WelcomeMessage::queueOpeningFor($user, $texts['period'], $teacher);
        }

        $pieces = [];

        for ($index = 0, $count = WelcomeMessage::count($texts); $index < $count; $index++) {
            $text = WelcomeMessage::piece($texts, $index);
            $pieces[] = [
                'text' => $text,
                // Toujours « déjà prêt seulement » : la page n'attend jamais une synthèse vocale.
                'url' => route('welcome.audio', ['index' => $index, 'salut' => $texts['period'], 'voix' => $provider?->name(), 'professeur' => $teacher->key, 'pret' => 1]),
                // Un paragraphe pas encore préparé chez ce fournisseur reste en texte ; la salutation peut arriver ensuite.
                'ready' => $provider !== null && ($index === 0 ? $openingReady : isset($files[WelcomeMessage::path($text, $provider)])),
                'soon' => $index === 0 && $provider !== null && ! $openingReady,
            ];
        }

        $name = $teacher->name();

        return [
            'key' => $teacher->key,
            'name' => $name,
            'face' => $teacher->face(),
            'portrait' => Asset::url(ltrim($teacher->portrait(), '/')),
            'play' => 'Toucher pour écouter '.$name,
            'hint' => 'Écoutez bien : '.$teacher->pronoun().' vous explique comment tout fonctionne.',
            'arriving' => 'Un instant, '.$name.' arrive…',
            'subtitles' => 'Texte du message de '.$name,
            'pieces' => $pieces,
            // Bouton de fin : le questionnaire, ou la séance si le programme est déjà construit.
            'next' => $programReady
                ? ['label' => 'Parler avec '.$name, 'url' => route('lesson.show'), 'icon' => 'mic']
                : ['label' => 'Construire mon programme', 'url' => route('program.questionnaire', 1), 'icon' => 'arrow-right'],
            'teacher3d' => Avatar::enabled($teacher) ? Avatar::clientConfig($teacher) + ['scene' => ['mode' => 'brand']] : null,
        ];
    }

    /**
     * Court extrait de la voix d'un professeur : le paragraphe où il se présente (« Je suis Teacher Rose… »),
     * déjà préparé pour la formule de l'apprenant ; 404 s'il n'est pas prêt (jamais de demande pendant l'écoute).
     */
    public function voice(Request $request, string $teacher): Response
    {
        $choice = Teachers::choosable()[$teacher] ?? null;
        abort_if($choice === null, 404);

        $wav = $this->sampleWav($request->user(), $choice);
        abort_if($wav === null, 404);

        return self::wavResponse($wav, $request->boolean('leger'));
    }

    /**
     * Réponse audio ; « leger » : version µ-law, deux fois plus petite, pour le préchargement (LightAudio).
     */
    private static function wavResponse(string $wav, bool $light): Response
    {
        if ($light) {
            $wav = LightAudio::mulaw($wav) ?? $wav;
        }

        return response($wav, 200, [
            'Content-Type' => 'audio/wav',
            'Content-Length' => (string) strlen($wav),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function sampleWav(User $user, Teacher $teacher): ?string
    {
        $texts = WelcomeMessage::texts($user, null, $teacher);
        $text = $texts['introduction'] ?? null;
        $provider = $texts !== null ? WelcomeMessage::providerFor($texts) : null;

        return $text !== null && $provider !== null ? app(WelcomeMessage::class)->get($text, $provider) : null;
    }

    /**
     * Un morceau du message (0 : ouverture, 1 et suivants : paragraphes), en WAV, chez le fournisseur du message.
     * Seule l'ouverture (propre au prénom) est générée à l'écoute ; les paragraphes communs sont préparés à l'avance.
     */
    public function audio(Request $request, int $index, WelcomeMessage $messages): Response
    {
        /** @var User $user */
        $user = $request->user();
        $period = (string) $request->query('salut');
        // Professeur du message demandé (la page précharge aussi celui qu'on peut choisir), sinon celui de l'élève.
        $teacher = Teachers::choosable()[(string) $request->query('professeur')] ?? null;
        $texts = WelcomeMessage::texts($user, Greeting::isPeriod($period) ? $period : null, $teacher);
        $text = $texts !== null ? WelcomeMessage::piece($texts, $index) : null;

        abort_if($text === null, 404);

        // Le fournisseur choisi par la page (toute la page joue la même voix), sinon celui du message.
        $provider = in_array($request->query('voix'), array_map(fn (SpeechProvider $provider) => $provider->name(), SpeechProviders::configured()), true)
            ? SpeechProviders::find((string) $request->query('voix'), $texts['teacher'])
            : WelcomeMessage::providerFor($texts);

        // « pret » : jamais de synthèse pendant l'écoute (la salutation se prépare à part, voir show()).
        $wav = $provider !== null ? $messages->get($text, $provider, $index === 0, readyOnly: $request->boolean('pret')) : null;

        if ($wav === null) {
            return response('Message vocal indisponible.', 503, ['Retry-After' => WelcomeMessage::RETRY_AFTER]);
        }

        return self::wavResponse($wav, $request->boolean('leger'));
    }
}
