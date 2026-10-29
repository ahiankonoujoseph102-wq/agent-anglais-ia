<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonVideo;
use App\Support\Gemini\SpeechFailed;
use App\Support\PrivateStorage;
use App\Support\Teachers\Teachers;
use App\Support\Videos\ClubVideos;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\OldAddress;
use App\Support\Videos\PresentationVideos;
use App\Support\Videos\ScreenCatalogue;
use App\Support\Videos\VideoEnding;
use App\Support\Videos\VideoSettings;
use App\Support\Videos\VideoTopics;
use App\Support\Videos\VideoVoice;
use App\Support\Videos\VideoVoiceTrial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Page « Vidéos » de l'administration : vidéos de leçon de Teacher Joe (aperçu, téléchargement, texte de
 * publication, « Publiée », mesures des lèvres et du son), préparation de nouvelles vidéos, « Réessayer », « Refaire »,
 * adresse du site des vidéos et essai comparatif des voix.
 */
class VideoController extends Controller
{
    public function index(Request $request, PresentationVideos $presentations, ClubVideos $club): View
    {
        $clubData = [
            'clubVideos' => $club->recent(7),
            'clubTheme' => ClubVideos::theme(today()),
            'clubMembers' => $club->members()->count(),
            'clubToday' => $club->forDay(today()),
        ];

        // Un assistant ne voit que le bloc « Club des abonnés » (publier à la place du fondateur).
        if (! $request->user()->isAdmin()) {
            return view('admin.videos.index', $clubData + ['onlyClub' => true]);
        }

        $videos = LessonVideo::query()->promo()->orderByDesc('day')->orderBy('slot')->paginate(15);

        return view('admin.videos.index', $clubData + [
            'onlyClub' => false,
            'videos' => $videos,
            'remaining' => VideoTopics::remaining(),
            'tokenConfigured' => filled(config('videos.token')),
            'voices' => collect(VideoVoice::providers())->map(fn ($provider) => $provider->label().' (voix « '.VideoVoice::voiceFor($provider).' »)')->all(),
            // Autres professeurs (un jour chacun) : leur propre voix, celle de leurs séances.
            'teacherVoices' => collect(Teachers::choosable())->reject(fn ($teacher) => $teacher->isDefault())->map(fn ($teacher) => $teacher->name().' : '.collect(VideoVoice::providers($teacher))->map(fn ($provider) => $provider->label().' « '.VideoVoice::voiceFor($provider, $teacher).' »')->implode(', puis '))->values()->all(),
            'site' => VideoSettings::site(),
            'blocked' => VideoSettings::blocked(),
            'closing' => VideoSettings::site() !== null ? VideoEnding::closing(VideoSettings::site()) : null,
            'subscribe' => (string) config('videos.ending.subscribe'),
            'trialVoices' => VideoVoiceTrial::voices(),
            'trialText' => (string) config('videos.voice_trial.text'),
            'redoable' => LessonVideo::query()->whereDate('day', today())->whereIn('status', [LessonVideo::READY, LessonVideo::FAILED])->count(),
            'reminder' => (string) config('videos.ai_label_reminder'),
            'perDay' => VideoSettings::perDay(),
            'perDayChoices' => VideoSettings::perDayChoices(),
            'presentations' => $presentations->all(),
            'catalogue' => app(ScreenCatalogue::class)->last(),
        ]);
    }

    /** « Préparer des vidéos maintenant » : ajoutées pour aujourd'hui, scripts et voix au fil des minutes. */
    public function prepare(Request $request, LessonVideos $videos): RedirectResponse
    {
        $count = (int) $request->validate(['count' => ['required', 'integer', 'min:1', 'max:3']])['count'];
        $created = $videos->plan(today(), $count);

        $message = $created === 0
            ? 'Aucun sujet nouveau : ajoutez des sujets dans resources/videos/topics.php.'
            : "{$created} vidéo(s) ajoutée(s). Le script et la voix sont prêts dans quelques minutes (état « Voix prête ») : lancez ensuite la fabrication sur GitHub.";

        return redirect()->to(route('admin.videos.index').'#videos')->with('status', $message);
    }

    /** Adresse du site dite et affichée à la fin des vidéos (vide : fabrication bloquée). */
    public function updateSite(Request $request): RedirectResponse
    {
        $value = trim((string) $request->input('site'));
        $site = VideoSettings::normalizeSite($value);

        if (OldAddress::isOld($site)) {
            return redirect()->to(route('admin.videos.index').'#reglages-videos')
                ->withErrors(['site' => 'C\'est l\'ancienne adresse du site. Utilisez l\'adresse officielle : '.OldAddress::primary()])->withInput();
        }

        if ($value !== '' && $site === null) {
            return redirect()->to(route('admin.videos.index').'#reglages-videos')
                ->withErrors(['site' => 'Cette adresse n\'est pas valable. Exemple : talkami.net'])->withInput();
        }

        VideoSettings::saveSite($site);

        return redirect()->to(route('admin.videos.index').'#reglages-videos')->with('status', $site !== null
            ? "Adresse des vidéos enregistrée : {$site}. Les prochaines vidéos la diront et l'afficheront à la fin."
            : 'Adresse des vidéos effacée : la fabrication est bloquée jusqu\'à ce qu\'une adresse soit renseignée.');
    }

    /** « Préparer la vidéo du Club d'aujourd'hui » (elle est sinon ajoutée toute seule, chaque minute). */
    public function prepareClub(ClubVideos $club): RedirectResponse
    {
        if (blank(config('videos.token'))) {
            return redirect()->to(route('admin.videos.index').'#club')->with('status', 'Jeton des vidéos non réglé (VIDEO_UPLOAD_TOKEN) : la vidéo du Club ne peut pas être fabriquée.');
        }

        $video = $club->ensureToday();

        $message = match (true) {
            $video !== null => 'Vidéo du Club ajoutée pour aujourd\'hui ('.$video->themeLabel().'). Script et voix prêts dans quelques minutes, puis GitHub la fabrique en premier.',
            $club->forDay(today()) !== null => 'La vidéo du Club d\'aujourd\'hui existe déjà.',
            default => 'Aucun abonné payant actif : pas de vidéo du Club aujourd\'hui.',
        };

        return redirect()->to(route('admin.videos.index').'#club')->with('status', $message);
    }

    /** Vidéos de promotion préparées chaque matin (1 quand le dépôt GitHub est privé : moins de minutes). */
    public function updatePerDay(Request $request): RedirectResponse
    {
        $count = (int) $request->validate(['per_day' => ['required', 'integer', 'in:'.implode(',', VideoSettings::perDayChoices())]])['per_day'];
        VideoSettings::savePerDay($count);

        return redirect()->to(route('admin.videos.index').'#videos-par-jour')
            ->with('status', "Vidéos de promotion par jour : {$count}. Pris en compte dès la préparation de demain matin.");
    }

    /** Extrait de l'essai comparatif : préparé au premier toucher, puis gardé. */
    public function voiceSample(Request $request, string $provider, string $voice): Response
    {
        abort_unless(VideoVoiceTrial::isAllowed($provider, $voice), 404);

        try {
            $wav = VideoVoiceTrial::sample($provider, $voice, 'video-voice-trial:'.$request->user()->id);
        } catch (SpeechFailed $e) {
            $message = $e->kind === SpeechFailed::MISSING_KEY
                ? 'La clé de ce fournisseur est absente : cette voix ne peut pas être essayée.'
                : 'Cette voix n\'a pas pu être préparée : '.$e->reason().'. Réessayez un peu plus tard.';

            return response()->json(['message' => $message], 503);
        }

        return response($wav, 200, ['Content-Type' => 'audio/wav', 'Cache-Control' => 'private, no-store']);
    }

    public function chooseVoice(Request $request): RedirectResponse
    {
        [$provider, $voice] = array_pad(explode(':', (string) $request->input('voice'), 2), 2, '');
        abort_unless(VideoVoiceTrial::isAllowed($provider, $voice), 422);
        VideoSettings::saveVoice($provider, $voice);

        return redirect()->to(route('admin.videos.index').'#essai-voix')
            ->with('status', "Voix des vidéos choisie : {$voice}. Elle sera utilisée pour les prochaines vidéos (les vidéos refaites gardent leur voix).");
    }

    /** « Refaire la vidéo » : même sujet, même script, même voix, nouveau rendu. */
    public function redo(LessonVideo $video, LessonVideos $videos): RedirectResponse
    {
        $done = $videos->redo($video);

        return redirect()->to(route('admin.videos.index').'#video-'.$video->id)->with('status', $done
            ? 'La vidéo sera refaite (même sujet, même script, même voix). Lancez ensuite la fabrication sur GitHub ; l\'ancienne vidéo reste visible jusqu\'à l\'arrivée de la nouvelle.'
            : 'Seule une vidéo prête ou en échec peut être refaite.');
    }

    public function redoToday(LessonVideos $videos): RedirectResponse
    {
        $count = $videos->redoToday();

        return redirect()->to(route('admin.videos.index').'#videos')->with('status', $count > 0
            ? "{$count} vidéo(s) d'aujourd'hui seront refaites (mêmes sujets, mêmes scripts, mêmes voix). Lancez ensuite la fabrication sur GitHub."
            : 'Aucune vidéo d\'aujourd\'hui n\'est prête ou en échec.');
    }

    public function publish(Request $request, LessonVideo $video): RedirectResponse
    {
        $this->authorizeVideo($request, $video);
        $video->update(['published_at' => $request->boolean('published') ? ($video->published_at ?? now()) : null]);

        return redirect()->to(route('admin.videos.index', ['page' => $request->integer('page') ?: null]).($video->isClub() ? '#club-' : '#video-').$video->id)
            ->with('status', $video->published_at ? 'Vidéo marquée comme publiée.' : 'Vidéo marquée comme non publiée.');
    }

    public function retry(LessonVideo $video, LessonVideos $videos): RedirectResponse
    {
        $videos->retry($video);

        return redirect()->to(route('admin.videos.index').'#video-'.$video->id)->with('status', 'La vidéo reprend là où elle s\'était arrêtée.');
    }

    /** Aperçu (dans la page) ou téléchargement (fichier MP4) : adresse signée de courte durée du bucket privé. */
    public function file(Request $request, LessonVideo $video): Response
    {
        $this->authorizeVideo($request, $video);
        $disk = PrivateStorage::disk();
        abort_if($video->video_path === null || ! $disk->exists($video->video_path), 404);

        $download = $request->boolean('telecharger');
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$video->downloadName().'"';

        try {
            return redirect()->away($disk->temporaryUrl($video->video_path, now()->addMinutes((int) config('videos.preview_url_minutes')), [
                'ResponseContentType' => 'video/mp4',
                'ResponseContentDisposition' => $disposition,
            ]));
        } catch (Throwable) {
            // Disque sans adresse signée (développement) : envoyé par le site.
            return $disk->response($video->video_path, $video->downloadName(), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store'], $download ? 'attachment' : 'inline');
        }
    }

    /** Catalogue des écrans : la vidéo ou la voix de Teacher Joe (fichier à télécharger). */
    public function catalogue(string $file, ScreenCatalogue $catalogue): Response
    {
        return $catalogue->download($file);
    }

    /** Un assistant n'a accès qu'aux vidéos du Club des abonnés. */
    private function authorizeVideo(Request $request, LessonVideo $video): void
    {
        abort_unless($request->user()->isAdmin() || $video->isClub(), 403);
    }
}
