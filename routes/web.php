<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AssessmentController as AdminAssessmentController;
use App\Http\Controllers\Admin\IconPreviewController;
use App\Http\Controllers\Admin\InactiveLearnerController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoiceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Live\AssessmentController;
use App\Http\Controllers\Live\CourseSessionController;
use App\Http\Controllers\Live\LessonController;
use App\Http\Controllers\Live\LevelTestController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Program\AttestationController;
use App\Http\Controllers\Program\LessonHistoryController;
use App\Http\Controllers\Program\ProgramController;
use App\Http\Controllers\WebAppController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', HomeController::class)->name('home');
Route::get('/contact', ContactController::class)->name('contact');
// Ancienne page « Crédits » : supprimée, l'adresse renvoie vers l'accueil (licences : LICENCES-TIERS.md).
Route::permanentRedirect('/credits', '/');

// Application installable : sans session, ces réponses ne contiennent aucune donnée personnelle.
Route::withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, EnsurePasswordIsChanged::class])->group(function () {
    Route::get('/manifest.webmanifest', [WebAppController::class, 'manifest'])->name('manifest');
    Route::get('/hors-connexion', [WebAppController::class, 'offline'])->name('offline');
});
Route::get('/attestation/verifier/{code}', [AttestationController::class, 'verify'])->name('attestation.verify');

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

    // Compte : adresse e-mail facultative.
    Route::get('/compte', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/compte', [AccountController::class, 'update'])->middleware('throttle:10,1')->name('account.update');

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
        Route::get('/message-vocal', 'voice')->middleware('throttle:10,1')->name('voice');
        Route::put('/niveau', 'chooseLevel')->name('level');
    });

    // Programme personnel : questionnaire en 4 écrans, calendrier, unités, historique.
    Route::get('/programme', [ProgramController::class, 'show'])->name('program.show');
    Route::get('/programme/questionnaire/{step}', [ProgramController::class, 'questionnaire'])->whereNumber('step')->name('program.questionnaire');
    Route::post('/programme/questionnaire/{step}', [ProgramController::class, 'saveStep'])->whereNumber('step')->name('program.questionnaire.store');
    Route::get('/programme/unites/{number}', [ProgramController::class, 'unit'])->whereNumber('number')->name('program.unit');
    Route::get('/mes-seances', LessonHistoryController::class)->name('lessons.history');
    Route::get('/attestations/{attestation}/telecharger', [AttestationController::class, 'download'])->name('attestation.download');

    // Bilan oral de fin de niveau.
    Route::prefix('bilan-niveau')->name('level-test.')->controller(LevelTestController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/connexion', 'connect')->middleware('throttle:10,1')->name('connect');
        Route::post('/signal', 'signal')->middleware('throttle:30,1')->name('signal');
        Route::post('/resultat', 'result')->middleware('throttle:10,1')->name('result');
        Route::post('/fin', 'finish')->middleware('throttle:10,1')->name('finish');
    });

    // « Mes cours » : complément facultatif. L'apprenant apporte une leçon d'anglais (photos ou PDF),
    // Teacher Joe la lit puis l'aide à la comprendre et à s'exercer (séance vocale à part).
    Route::prefix('mes-cours')->name('courses.')->group(function () {
        Route::get('/', [CourseController::class, 'index'])->name('index');
        Route::get('/nouvelle', [CourseController::class, 'create'])->name('create');
        Route::post('/', [CourseController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::post('/seance/signal', [CourseSessionController::class, 'signal'])->middleware('throttle:30,1')->name('signal');
        Route::post('/seance/fin', [CourseSessionController::class, 'finish'])->middleware('throttle:10,1')->name('finish');
        Route::get('/{course}', [CourseController::class, 'show'])->whereNumber('course')->name('show');
        Route::get('/{course}/pages/{page}', [CourseController::class, 'page'])->whereNumber(['course', 'page'])->name('page');
        Route::delete('/{course}', [CourseController::class, 'destroy'])->whereNumber('course')->middleware('confirm:course.delete')->name('destroy');
        Route::get('/{course}/seance', [CourseSessionController::class, 'show'])->whereNumber('course')->name('session');
        Route::post('/{course}/seance/connexion', [CourseSessionController::class, 'connect'])->whereNumber('course')->middleware('throttle:10,1')->name('connect');
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
    Route::post('/paiements/{payment}/valider', [AdminPaymentController::class, 'approve'])->middleware('confirm:payment.approve')->name('payments.approve');
    Route::post('/paiements/{payment}/refuser', [AdminPaymentController::class, 'reject'])->middleware('confirm:payment.reject')->name('payments.reject');

    Route::get('/inscrits', [UserController::class, 'index'])->name('users.index');
    Route::get('/inscrits/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('/inscrits/{user}/mot-de-passe', [UserController::class, 'temporaryPassword'])->middleware('confirm:password.temporary')->name('users.password');
    Route::delete('/inscrits/{user}/evaluation', [AdminAssessmentController::class, 'destroy'])->middleware('confirm:assessment.reset')->name('assessments.destroy');
    Route::get('/consommation', [UsageController::class, 'index'])->name('usage.index');
    Route::get('/reglages', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/reglages', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/voix', [VoiceController::class, 'index'])->name('voices.index');
    Route::get('/voix/{voice}/extrait', [VoiceController::class, 'sample'])->where('voice', '[A-Za-z]+')->middleware('throttle:30,1')->name('voices.sample');
    Route::post('/voix', [VoiceController::class, 'choose'])->name('voices.choose');
    Route::get('/icones', IconPreviewController::class)->name('icons.index');
    Route::get('/inactifs', [InactiveLearnerController::class, 'index'])->name('inactive.index');
    Route::get('/inactifs/{user}/whatsapp', [InactiveLearnerController::class, 'whatsapp'])->name('inactive.whatsapp');
});
