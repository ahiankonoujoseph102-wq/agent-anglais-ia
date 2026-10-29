<?php

use App\Http\Controllers\Api\CatalogueJobController;
use App\Http\Controllers\Api\PresentationJobController;
use App\Http\Controllers\Api\SiteVideoJobController;
use App\Http\Controllers\Api\VideoJobController;
use Illuminate\Support\Facades\Route;

/*
| Fabrication des vidéos de leçon par GitHub Actions (tools/video/render.mjs) : jeton secret, aucune session.
*/
Route::middleware(['video.token', 'throttle:120,1'])->prefix('videos')->name('api.videos.')->group(function () {
    Route::get('/travaux', [VideoJobController::class, 'jobs'])->name('jobs');
    Route::get('/{video}/voix', [VideoJobController::class, 'audio'])->name('audio');
    Route::put('/{video}/fichier', [VideoJobController::class, 'upload'])->name('upload');
    Route::post('/{video}/prete', [VideoJobController::class, 'done'])->name('done');
    Route::post('/{video}/echec', [VideoJobController::class, 'failed'])->name('failed');
    Route::post('/{video}/reporter', [VideoJobController::class, 'release'])->name('release');
});

/*
| Fabrication des vidéos de présentation (tools/presentation/make.mjs, workflow lancé à la main) : même jeton.
*/
Route::middleware(['video.token', 'throttle:120,1'])->prefix('presentations')->name('api.presentations.')->group(function () {
    Route::get('/travaux', [PresentationJobController::class, 'jobs'])->name('jobs');
    // Version « site » (page « Comment ça marche ») tirée de la version WhatsApp : tools/presentation/site-version.mjs.
    Route::get('/site/travaux', [SiteVideoJobController::class, 'jobs'])->name('site.jobs');
    Route::get('/{presentation}/site/source', [SiteVideoJobController::class, 'source'])->name('site.source');
    Route::put('/{presentation}/site/fichier/{file}', [SiteVideoJobController::class, 'upload'])->whereIn('file', ['video', 'apercu'])->name('site.upload');
    Route::post('/{presentation}/site/prete', [SiteVideoJobController::class, 'done'])->name('site.done');
    Route::get('/{presentation}/voix', [PresentationJobController::class, 'audio'])->name('audio');
    Route::put('/{presentation}/fichier/{version}', [PresentationJobController::class, 'upload'])->whereIn('version', ['tiktok', 'whatsapp'])->name('upload');
    Route::post('/{presentation}/prete', [PresentationJobController::class, 'done'])->name('done');
    Route::post('/{presentation}/echec', [PresentationJobController::class, 'failed'])->name('failed');
});

/*
| Catalogue des écrans pour un montage (tools/presentation/catalogue.mjs, workflow lancé à la main) : même jeton.
*/
Route::middleware(['video.token', 'throttle:60,1'])->prefix('catalogue')->name('api.catalogue.')->group(function () {
    Route::get('/travail', [CatalogueJobController::class, 'job'])->name('job');
    Route::put('/fichier/{file}', [CatalogueJobController::class, 'upload'])->whereIn('file', ['video', 'voix'])->name('upload');
    Route::post('/pret', [CatalogueJobController::class, 'done'])->name('done');
});
