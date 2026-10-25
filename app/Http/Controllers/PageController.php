<?php

namespace App\Http\Controllers;

use App\Support\CountryPage;
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
    public function plans(SiteVideos $videos): View
    {
        $link = config('videos.presentations.site.plans_link');

        return view('pages.plans', [
            'questionsVideo' => $link ? $videos->find($link) : null,
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

    /** Image d'aperçu d'une vidéo (environ 30 kilooctets), gardée en cache par le navigateur. */
    public function poster(string $key, SiteVideos $videos): Response
    {
        $video = $videos->find($key);
        $disk = PrivateStorage::disk();
        abort_if($video === null || ! $disk->exists($video->poster_path), 404);

        return $disk->response($video->poster_path, $key.'.jpg', ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400']);
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
