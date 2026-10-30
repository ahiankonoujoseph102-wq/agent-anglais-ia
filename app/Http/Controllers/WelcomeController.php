<?php

namespace App\Http\Controllers;

use App\Models\User;
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
 * Avec plusieurs professeurs proposés : « Avec qui voulez-vous travailler ? » (un court extrait de la voix de
 * chacun, puis « Choisir ») ; le message est dit par le professeur choisi. Jamais de choix sur la page de paiement.
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

        // UNE seule voix pour tout le message : le fournisseur chez qui il est prêt dit aussi la salutation.
        $message = WelcomeMessage::message($texts);
        $files = $message->files();
        $provider = $message->provider($files);
        $openingReady = $provider !== null && WelcomeMessage::openingReady($texts, $provider);

        // Salutation au prénom pas encore gardée (autre moment de la journée, autre professeur) : préparée APRÈS
        // cette réponse ; la page la redemande quelques fois et, si elle n'est pas là au toucher, commence sans elle.
        if ($provider !== null && ! $openingReady) {
            WelcomeMessage::queueOpeningFor($user, $texts['period'], $texts['teacher']);
        }

        $pieces = [];

        for ($index = 0, $count = WelcomeMessage::count($texts); $index < $count; $index++) {
            $text = WelcomeMessage::piece($texts, $index);
            $pieces[] = [
                'text' => $text,
                // Toujours « déjà prêt seulement » : la page n'attend jamais une synthèse vocale.
                'url' => route('welcome.audio', ['index' => $index, 'salut' => $texts['period'], 'voix' => $provider?->name(), 'pret' => 1]),
                // Un paragraphe pas encore préparé chez ce fournisseur reste en texte ; la salutation peut arriver ensuite.
                'ready' => $provider !== null && ($index === 0 ? $openingReady : isset($files[WelcomeMessage::path($text, $provider)])),
                'soon' => $index === 0 && $provider !== null && ! $openingReady,
            ];
        }

        $teacher = $texts['teacher'];

        // Bouton de fin : le questionnaire, ou la séance si le programme est déjà construit.
        $next = $user->activeProgram()->exists()
            ? ['label' => 'Parler avec '.$teacher->name(), 'url' => route('lesson.show'), 'icon' => 'mic']
            : ['label' => 'Construire mon programme', 'url' => route('program.questionnaire', 1), 'icon' => 'arrow-right'];

        return view('welcome.show', [
            'firstName' => $texts['first_name'],
            'firstVisit' => ! $request->boolean('reecouter'),
            'planName' => $texts['plan'],
            'pieces' => $pieces,
            'next' => $next,
            'teacher' => $teacher,
            'choices' => $this->choices($user, $teacher),
            'teacher3d' => Avatar::enabled($teacher) ? Avatar::clientConfig($teacher) + ['scene' => ['mode' => 'brand']] : null,
        ]);
    }

    /**
     * « Avec qui voulez-vous travailler ? » : chaque professeur proposé, et l'adresse d'un court extrait de sa voix
     * (sa présentation, déjà préparée) ; vide quand un seul professeur est proposé.
     *
     * @return list<array{teacher: Teacher, current: bool, sample: ?string}>
     */
    private function choices(User $user, Teacher $current): array
    {
        if (! Teachers::hasChoice()) {
            return [];
        }

        return array_values(array_map(fn (Teacher $teacher) => [
            'teacher' => $teacher,
            'current' => $teacher->key === $current->key,
            'sample' => $this->sampleWav($user, $teacher) !== null ? route('welcome.voice', $teacher->key) : null,
        ], Teachers::choosable()));
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
        $texts = WelcomeMessage::texts($user, Greeting::isPeriod($period) ? $period : null);
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
