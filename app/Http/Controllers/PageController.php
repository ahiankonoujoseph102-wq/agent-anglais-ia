<?php

namespace App\Http\Controllers;

use App\Support\CountryPage;
use App\Support\LightImage;
use App\Support\PaymentCountry;
use App\Support\Plan;
use App\Support\PrivateStorage;
use App\Support\Videos\SiteVideos;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pages publiques du référencement : formules et prix, « À propos », « Comment ça marche » (vidéos à la demande),
 * pages « Apprendre l'anglais » par pays.
 */
class PageController extends Controller
{
    public function plans(): View
    {
        return view('pages.plans', [
            'plans' => Plan::all(),
            'countries' => PaymentCountry::available(),
            'countryPages' => CountryPage::all(),
        ]);
    }

    public function about(): View
    {
        return view('pages.about', ['countryPages' => CountryPage::all()]);
    }

    public function country(string $country): View
    {
        return view('pages.country', [
            'page' => CountryPage::find($country),
            'plans' => Plan::all(),
            'countryPages' => CountryPage::all(),
        ]);
    }

    public function how(SiteVideos $videos): View
    {
        return view('pages.how', ['videos' => $videos->ready()]);
    }

    /**
     * Image d'aperçu d'une vidéo, gardée en cache par le navigateur : en WebP (environ deux fois plus légère que le JPEG
     * de GitHub), préparée une fois et gardée à côté de l'original ; si GD ne sait pas la faire, le JPEG d'origine.
     */
    public function poster(string $key, SiteVideos $videos): Response
    {
        $video = $videos->find($key);
        $disk = PrivateStorage::disk();
        abort_if($video === null || ! $disk->exists($video->poster_path), 404);

        $headers = ['Cache-Control' => 'public, max-age=86400'];
        // Une nouvelle version « site » (vidéo refaite) change la date : une nouvelle copie allégée est préparée.
        $light = $video->poster_path.'.'.($video->updated_at?->timestamp ?? 0).'.webp';

        if (! $disk->exists($light) && ($webp = LightImage::webp((string) $disk->get($video->poster_path))) !== null) {
            $disk->put($light, $webp);
        }

        if ($disk->exists($light)) {
            return $disk->response($light, $key.'.webp', ['Content-Type' => 'image/webp'] + $headers);
        }

        return $disk->response($video->poster_path, $key.'.jpg', ['Content-Type' => 'image/jpeg'] + $headers);
    }

    /** Toucher sur « Regarder » : adresse signée de la version « site » sur le bucket privé. */
    public function video(string $key, SiteVideos $videos): Response|RedirectResponse
    {
        $video = $videos->find($key);
        $disk = PrivateStorage::disk();
        abort_if($video === null || ! $disk->exists($video->web_path), 404);

        try {
            return redirect()->away($disk->temporaryUrl($video->web_path, now()->addMinutes((int) config('videos.presentations.site.url_minutes')), [
                'ResponseContentType' => 'video/mp4',
                'ResponseContentDisposition' => 'inline',
            ]));
        } catch (Throwable) {
            // Disque sans adresse signée (développement) : envoyé par le site.
            return $disk->response($video->web_path, $key.'.mp4', ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store']);
        }
    }
}
