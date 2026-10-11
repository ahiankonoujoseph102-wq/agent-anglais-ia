<?php

use App\Http\Controllers\Admin\AssessmentController as AdminAssessmentController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Live\AssessmentController;
use App\Http\Controllers\Live\LessonController;
use App\Http\Controllers\Payment\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/contact', ContactController::class)->name('contact');

Route::middleware('guest')->group(function () {
    Route::get('/inscription', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store']);

    Route::get('/mot-de-passe-oublie', [PasswordController::class, 'forgot'])->name('password.forgot');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Changement obligatoire après un mot de passe provisoire.
    Route::get('/mot-de-passe/nouveau', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/mot-de-passe/nouveau', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');

    Route::get('/espace', DashboardController::class)->name('dashboard');

    // Paiement manuel par Mobile Money : formule → pays → déclaration → validation.
    Route::get('/abonnement', [PaymentController::class, 'plans'])->name('payment.plans');
    Route::get('/abonnement/{plan}', [PaymentController::class, 'create'])->name('payment.create');
    Route::post('/abonnement/{plan}', [PaymentController::class, 'store'])->middleware('throttle:10,1')->name('payment.store');
    Route::get('/paiements/{payment}', [PaymentController::class, 'show'])->name('payment.show');

    // Évaluation orale gratuite (une seule par compte).
    Route::prefix('evaluation')->name('assessment.')->controller(AssessmentController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/connexion', 'connect')->middleware('throttle:10,1')->name('connect');
        Route::post('/signal', 'signal')->middleware('throttle:30,1')->name('signal');
        Route::post('/resultat', 'result')->middleware('throttle:10,1')->name('result');
        Route::post('/fin', 'finish')->middleware('throttle:10,1')->name('finish');
    });

    // Séance du jour des abonnés.
    Route::prefix('seance')->name('lesson.')->controller(LessonController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/connexion', 'connect')->middleware('throttle:10,1')->name('connect');
        Route::post('/signal', 'signal')->middleware('throttle:30,1')->name('signal');
        Route::post('/bilan', 'report')->middleware('throttle:10,1')->name('report');
        Route::post('/fin', 'finish')->middleware('throttle:10,1')->name('finish');
    });
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/paiements')->name('home');

    Route::get('/paiements', [AdminPaymentController::class, 'pending'])->name('payments.pending');
    Route::get('/paiements/historique', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/paiements/{payment}/capture', [AdminPaymentController::class, 'proof'])->name('payments.proof');
    Route::post('/paiements/{payment}/valider', [AdminPaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/paiements/{payment}/refuser', [AdminPaymentController::class, 'reject'])->name('payments.reject');

    Route::get('/inscrits', [UserController::class, 'index'])->name('users.index');
    Route::get('/inscrits/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('/inscrits/{user}/mot-de-passe', [UserController::class, 'temporaryPassword'])->name('users.password');
    Route::delete('/inscrits/{user}/evaluation', [AdminAssessmentController::class, 'destroy'])->name('assessments.destroy');
    Route::get('/consommation', [UsageController::class, 'index'])->name('usage.index');
});
