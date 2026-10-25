<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresentationVideo;
use App\Support\PrivateStorage;
use App\Support\Videos\PresentationScripts;
use App\Support\Videos\PresentationVideos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Partie « Vidéos de présentation » de la page « Vidéos » : demande de préparation (voix par Laravel, puis
 * fabrication par GitHub Actions), aperçu et téléchargement des deux versions (TikTok et WhatsApp).
 */
class PresentationVideoController extends Controller
{
    /** « Préparer les vidéos de présentation » (toutes) ou « Préparer » une seule. */
    public function request(Request $request, PresentationVideos $videos): RedirectResponse
    {
        $key = $request->input('key');
        abort_if($key !== null && ! in_array($key, PresentationScripts::keys(), true), 404);

        $count = $videos->request($key);

        return redirect()->to(route('admin.videos.index').'#presentations')->with('status', $count === 0
            ? 'Rien n\'a été préparé : renseignez d\'abord l\'adresse du site des vidéos (« Fin de chaque vidéo »), ou attendez la fin de la fabrication en cours.'
            : "{$count} vidéo(s) de présentation demandée(s). La voix est prête en quelques minutes (état « Voix prête ») : lancez ensuite la fabrication sur GitHub (Actions, « Vidéos de présentation », Run workflow).");
    }

    /** Aperçu ou téléchargement : adresse signée de courte durée du bucket privé. */
    public function file(Request $request, PresentationVideo $presentation): Response
    {
        $light = $request->input('version') === 'whatsapp';
        $path = $light ? $presentation->light_path : $presentation->video_path;
        $disk = PrivateStorage::disk();
        abort_if($path === null || ! $disk->exists($path), 404);

        $download = $request->boolean('telecharger');
        $name = $presentation->downloadName($light);

        try {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes((int) config('videos.preview_url_minutes')), [
                'ResponseContentType' => 'video/mp4',
                'ResponseContentDisposition' => ($download ? 'attachment' : 'inline').'; filename="'.$name.'"',
            ]));
        } catch (Throwable) {
            // Disque sans adresse signée (développement) : envoyé par le site.
            return $disk->response($path, $name, ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store'], $download ? 'attachment' : 'inline');
        }
    }
}
