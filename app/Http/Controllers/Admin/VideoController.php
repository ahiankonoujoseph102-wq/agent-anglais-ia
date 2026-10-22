<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonVideo;
use App\Support\PrivateStorage;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\VideoScriptWriter;
use App\Support\Videos\VideoTopics;
use App\Support\Videos\VideoVoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Page « Vidéos » de l'administration : vidéos de leçon de Teacher Joe (aperçu, téléchargement, texte de
 * publication, « Publiée »), préparation de nouvelles vidéos et « Réessayer ».
 */
class VideoController extends Controller
{
    public function index(): View
    {
        $videos = LessonVideo::query()->orderByDesc('day')->orderBy('slot')->paginate(15);

        return view('admin.videos.index', [
            'videos' => $videos,
            'remaining' => VideoTopics::remaining(),
            'tokenConfigured' => filled(config('videos.token')),
            'voices' => collect(VideoVoice::providers())->map(fn ($provider) => $provider->label().' (voix « '.VideoVoice::voiceFor($provider).' »)')->all(),
            'closing' => VideoScriptWriter::closing(),
            'reminder' => (string) config('videos.ai_label_reminder'),
            'perDay' => (int) config('videos.per_day'),
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

    public function publish(Request $request, LessonVideo $video): RedirectResponse
    {
        $video->update(['published_at' => $request->boolean('published') ? ($video->published_at ?? now()) : null]);

        return redirect()->to(route('admin.videos.index', ['page' => $request->integer('page') ?: null]).'#video-'.$video->id)
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
}
