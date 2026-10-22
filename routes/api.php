<?php

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
