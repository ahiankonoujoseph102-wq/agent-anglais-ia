<?php

use App\Http\Controllers\Api\PresentationJobController;
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
});

/*
| Fabrication des vidéos de présentation (tools/presentation/make.mjs, workflow lancé à la main) : même jeton.
*/
Route::middleware(['video.token', 'throttle:120,1'])->prefix('presentations')->name('api.presentations.')->group(function () {
    Route::get('/travaux', [PresentationJobController::class, 'jobs'])->name('jobs');
    Route::get('/{presentation}/voix', [PresentationJobController::class, 'audio'])->name('audio');
    Route::put('/{presentation}/fichier/{version}', [PresentationJobController::class, 'upload'])->whereIn('version', ['tiktok', 'whatsapp'])->name('upload');
    Route::post('/{presentation}/prete', [PresentationJobController::class, 'done'])->name('done');
    Route::post('/{presentation}/echec', [PresentationJobController::class, 'failed'])->name('failed');
});
