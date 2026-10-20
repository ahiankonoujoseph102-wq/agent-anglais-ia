<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\AssessmentController as AdminAssessmentController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\GrantedAccessController;
use App\Http\Controllers\Admin\IconPreviewController;
use App\Http\Controllers\Admin\InactiveLearnerController;
use App\Http\Controllers\Admin\LiveTrialController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TextDiagnosticController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoiceController;
use App\Http\Controllers\Admin\VoiceDiagnosticController;
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
use App\Http\Controllers\Live\RealtimeCallController;
use App\Http\Controllers\Live\TranscriptController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Program\AttestationController;
use App\Http\Controllers\Program\LessonHistoryController;
use App\Http\Controllers\Program\ProgramAnswersController;
use App\Http\Controllers\Program\ProgramController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\WebAppController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Support\CountryPage;
use App\Support\Live\Transcripts;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', HomeController::class)->name('home');
Route::get('/contact', ContactController::class)->name('contact');
// Pages publiques du référencement : formules et prix, « À propos », une page par pays de paiement.
Route::get('/formules', [PageController::class, 'plans'])->name('plans');
Route::get('/a-propos', [PageController::class, 'about'])->name('about');
Route::get('/apprendre-anglais/{country}', [PageController::class, 'country'])->whereIn('country', CountryPage::slugs())->name('country');
// Confidentialité : ce que le site garde, pourquoi, combien de temps, et comment en demander la suppression.
Route::view('/confidentialite', 'privacy')->name('privacy');
// Ancienne page « Crédits » : supprimée, l'adresse renvoie vers l'accueil (licences : LICENCES-TIERS.md).
Route::permanentRedirect('/credits', '/');

// Application installable : sans session, ces réponses ne contiennent aucune donnée personnelle.
Route::withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, EnsurePasswordIsChanged::class])->group(function () {
    Route::get('/manifest.webmanifest', [WebAppController::class, 'manifest'])->name('manifest');
    Route::get('/hors-connexion', [WebAppController::class, 'offline'])->name('offline');
    // Référencement : écrits à partir de APP_URL (App\Support\Seo).
    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
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

    // Bienvenue de Teacher Joe : message vocal après l'inscription, puis à l'ouverture d'un accès.
    Route::get('/bienvenue', [WelcomeController::class, 'show'])->name('welcome.show');
    Route::get('/bienvenue/audio/{index}', [WelcomeController::class, 'audio'])->whereNumber('index')->middleware('throttle:60,1')->name('welcome.audio');

    // Compte : adresse e-mail facultative.
    Route::get('/compte', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/compte', [AccountController::class, 'update'])->middleware('throttle:10,1')->name('account.update');

    // Paiement manuel par Mobile Money : formule → pays → déclaration → validation.
    Route::get('/abonnement', [PaymentController::class, 'plans'])->name('payment.plans');
    Route::get('/abonnement/{plan}', [PaymentController::class, 'create'])->name('payment.create');
    Route::post('/abonnement/{plan}', [PaymentController::class, 'store'])->middleware('throttle:10,1')->name('payment.store');
    Route::get('/abonnement/{plan}/whatsapp', [PaymentController::class, 'whatsapp'])->middleware('throttle:20,1')->name('payment.whatsapp');
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
    Route::get('/programme/{program}/objectifs/{step}', [ProgramAnswersController::class, 'edit'])->whereNumber(['program', 'step'])->name('program.answers.edit');
    Route::post('/programme/{program}/objectifs/{step}', [ProgramAnswersController::class, 'update'])->whereNumber(['program', 'step'])->name('program.answers.update');
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

    // Séances en direct chez OpenAI : ouverture de l'appel WebRTC (adresse à usage unique).
    Route::post('/en-direct/appel/{ticket}', [RealtimeCallController::class, 'store'])->where('ticket', '[A-Za-z0-9]{40}')->middleware('throttle:10,1')->name('live.call');

    // Texte des conversations (jamais l'audio) : répliques envoyées par petits lots pendant la séance et à la fin.
    Route::post('/en-direct/texte/{type}/{id}', [TranscriptController::class, 'store'])->whereIn('type', array_keys(Transcripts::TYPES))->whereNumber('id')->middleware('throttle:60,1')->name('live.transcript');

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
    Route::post('/inscrits/{user}/acces-offert', [GrantedAccessController::class, 'store'])->middleware('confirm:access.grant')->name('users.grant');
    Route::delete('/inscrits/{user}/acces-offert', [GrantedAccessController::class, 'destroy'])->middleware('confirm:access.revoke')->name('users.revoke');
    Route::delete('/inscrits/{user}/evaluation', [AdminAssessmentController::class, 'destroy'])->middleware('confirm:assessment.reset')->name('assessments.destroy');
    // Texte des conversations d'un apprenant : lecture d'une séance, suppression de toutes (demande de suppression).
    Route::get('/inscrits/{user}/conversations/{type}/{id}', [ConversationController::class, 'show'])->whereIn('type', array_keys(Transcripts::TYPES))->whereNumber('id')->name('conversations.show');
    Route::delete('/inscrits/{user}/conversations', [ConversationController::class, 'destroy'])->middleware('confirm:transcripts.delete')->name('conversations.destroy');
    Route::get('/consommation', [UsageController::class, 'index'])->name('usage.index');
    Route::get('/reglages', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/reglages', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/voix', [VoiceController::class, 'index'])->name('voices.index');
    Route::get('/voix/{voice}/extrait', [VoiceController::class, 'sample'])->where('voice', '[A-Za-z]+')->middleware('throttle:30,1')->name('voices.sample');
    Route::post('/voix', [VoiceController::class, 'choose'])->name('voices.choose');
    Route::get('/diagnostic-voix', [VoiceDiagnosticController::class, 'index'])->name('voice-diagnostic.index');
    Route::post('/diagnostic-voix/essai/{provider}', [VoiceDiagnosticController::class, 'test'])->whereIn('provider', ['gemini', 'openai'])->middleware('throttle:10,1')->name('voice-diagnostic.test');
    Route::post('/diagnostic-voix/preparer', [VoiceDiagnosticController::class, 'prepare'])->middleware('throttle:6,1')->name('voice-diagnostic.prepare');
    Route::get('/diagnostic-texte', [TextDiagnosticController::class, 'index'])->name('text-diagnostic.index');
    Route::post('/diagnostic-texte/essai/{provider}', [TextDiagnosticController::class, 'test'])->whereIn('provider', ['gemini', 'openai'])->middleware('throttle:10,1')->name('text-diagnostic.test');
    Route::post('/diagnostic-texte/direct/{provider}/essai', [LiveTrialController::class, 'test'])->whereIn('provider', ['gemini', 'openai'])->middleware('throttle:10,1')->name('live-trial.test');
    Route::get('/diagnostic-texte/direct/{provider}/conversation', [LiveTrialController::class, 'show'])->whereIn('provider', ['gemini', 'openai'])->name('live-trial.show');
    Route::post('/diagnostic-texte/direct/{provider}/conversation/connexion', [LiveTrialController::class, 'connect'])->whereIn('provider', ['gemini', 'openai'])->middleware('throttle:10,1')->name('live-trial.connect');
    Route::post('/diagnostic-texte/direct/{provider}/conversation/signal', [LiveTrialController::class, 'signal'])->whereIn('provider', ['gemini', 'openai'])->middleware('throttle:30,1')->name('live-trial.signal');
    Route::post('/diagnostic-texte/direct/{provider}/conversation/fin', [LiveTrialController::class, 'finish'])->whereIn('provider', ['gemini', 'openai'])->middleware('throttle:10,1')->name('live-trial.finish');
    Route::get('/alertes', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alertes/abonnement', [AlertController::class, 'subscribe'])->middleware('throttle:20,1')->name('alerts.subscribe');
    Route::delete('/alertes/abonnement', [AlertController::class, 'unsubscribe'])->middleware('throttle:20,1')->name('alerts.unsubscribe');
    Route::post('/alertes/essai-notification', [AlertController::class, 'testPush'])->middleware('throttle:10,1')->name('alerts.test-push');
    Route::post('/alertes/essai-email', [AlertController::class, 'testMail'])->middleware('throttle:10,1')->name('alerts.test-mail');
    Route::delete('/alertes/telephones/{subscription}', [AlertController::class, 'destroy'])->name('alerts.destroy');
    Route::get('/icones', IconPreviewController::class)->name('icons.index');
    Route::get('/inactifs', [InactiveLearnerController::class, 'index'])->name('inactive.index');
    Route::get('/inactifs/{user}/whatsapp', [InactiveLearnerController::class, 'whatsapp'])->name('inactive.whatsapp');
});
