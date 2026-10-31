<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\AmbassadorController as AdminAmbassadorController;
use App\Http\Controllers\Admin\AssessmentController as AdminAssessmentController;
use App\Http\Controllers\Admin\AssistantController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\GrantedAccessController;
use App\Http\Controllers\Admin\IconPreviewController;
use App\Http\Controllers\Admin\InactiveLearnerController;
use App\Http\Controllers\Admin\LiveTrialController;
use App\Http\Controllers\Admin\OriginController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PresentationVideoController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\TextDiagnosticController;
use App\Http\Controllers\Admin\UploadCheckController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\Admin\VoiceController;
use App\Http\Controllers\Admin\VoiceDiagnosticController;
use App\Http\Controllers\Admin\VoiceLiveController;
use App\Http\Controllers\Admin\VoiceSpeechTrialController;
use App\Http\Controllers\Ambassador\AmbassadorJoinController;
use App\Http\Controllers\Ambassador\AmbassadorLinkController;
use App\Http\Controllers\Ambassador\AmbassadorSpaceController;
use App\Http\Controllers\Auth\AdminRecoveryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\CourseReviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuideController;
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
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TeacherChoiceController;
use App\Http\Controllers\WebAppController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Support\CountryPage;
use App\Support\Guides;
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
// « Comment ça marche » : vidéos de présentation en version très légère ; image d'aperçu et vidéo seulement à la demande.
Route::get('/comment-ca-marche', [PageController::class, 'how'])->name('how');
Route::get('/comment-ca-marche/{key}/apercu', [PageController::class, 'poster'])->name('how.poster');
Route::get('/comment-ca-marche/{key}/video', [PageController::class, 'video'])->middleware('throttle:30,1')->name('how.video');
// Pages d'arrivée : aide aux cours d'anglais (élèves, étudiants, parents) et préparation d'un entretien.
Route::get('/aide-aux-cours-d-anglais', [PageController::class, 'coursesHelp'])->name('courses-help');
Route::get('/preparer-un-entretien-en-anglais', [PageController::class, 'interview'])->name('interview');
// Guides gratuits (App\Support\Guides).
Route::get('/guides', [GuideController::class, 'index'])->name('guides.index');
Route::get('/guides/{guide}', [GuideController::class, 'show'])->whereIn('guide', Guides::slugs())->name('guides.show');
// Installer l'application : explications pour Android et iPhone (lien du Menu et du pied de page, sans JavaScript).
Route::view('/installer-l-application', 'pages.install')->name('install');
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
// Lien de récupération de l'administrateur (app:send-admin-recovery) : usage unique, quelques minutes.
Route::get('/recuperation-administrateur', [AdminRecoveryController::class, 'show'])->middleware('throttle:20,1')->name('admin.recovery');
Route::post('/recuperation-administrateur', [AdminRecoveryController::class, 'store'])->middleware('throttle:5,1')->name('admin.recovery.store');
Route::get('/attestation/verifier/{code}', [AttestationController::class, 'verify'])->name('attestation.verify');
// Lien personnel d'un ambassadeur (aussi derrière son code QR) : mène toujours à l'accueil.
Route::get('/a/{code}', AmbassadorLinkController::class)->where('code', '[A-Za-z0-9]{1,20}')->middleware('throttle:60,1')->name('ambassador.link');
// Page « Devenir ambassadeur » : compteur réel des places, candidature ou liste d'attente.
Route::get('/devenir-ambassadeur', [AmbassadorJoinController::class, 'show'])->name('ambassador.join');

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
    // Choix du professeur (« Mon espace ») : seulement les professeurs prêts (config/teachers.php).
    Route::post('/espace/professeur', [TeacherChoiceController::class, 'update'])->middleware('throttle:30,1')->name('teacher.choose');
    // Suivi des élèves : heure des séances, téléphones abonnés aux rappels, e-mail ajouté depuis l'écran de la séance.
    Route::put('/espace/heure', [ReminderController::class, 'time'])->middleware('throttle:10,1')->name('reminders.time');
    Route::post('/espace/rappels/telephone', [ReminderController::class, 'subscribe'])->middleware('throttle:10,1')->name('reminders.device');
    Route::delete('/espace/rappels/telephone', [ReminderController::class, 'unsubscribe'])->middleware('throttle:10,1')->name('reminders.device.remove');
    Route::post('/seance/email', [ReminderController::class, 'email'])->middleware('throttle:10,1')->name('lesson.email');

    // Espace ambassadeur : lien, code et code QR personnels, mois gratuit, régularité.
    Route::get('/ambassadeur', [AmbassadorSpaceController::class, 'show'])->name('ambassador.show');
    Route::get('/ambassadeur/code-qr', [AmbassadorSpaceController::class, 'qr'])->middleware('throttle:20,1')->name('ambassador.qr');
    // Candidature et liste d'attente (compte obligatoire).
    Route::get('/devenir-ambassadeur/candidature', [AmbassadorJoinController::class, 'form'])->name('ambassador.join.form');
    Route::post('/devenir-ambassadeur/candidature', [AmbassadorJoinController::class, 'apply'])->middleware('throttle:5,1')->name('ambassador.apply');
    Route::post('/devenir-ambassadeur/liste-d-attente', [AmbassadorJoinController::class, 'joinWaitlist'])->middleware('throttle:10,1')->name('ambassador.waitlist');
    Route::delete('/devenir-ambassadeur/liste-d-attente', [AmbassadorJoinController::class, 'leaveWaitlist'])->middleware('throttle:10,1')->name('ambassador.waitlist.leave');

    // Bienvenue de Teacher Joe : message vocal après l'inscription, puis à l'ouverture d'un accès.
    Route::get('/bienvenue', [WelcomeController::class, 'show'])->name('welcome.show');
    Route::get('/bienvenue/audio/{index}', [WelcomeController::class, 'audio'])->whereNumber('index')->middleware('throttle:60,1')->name('welcome.audio');
    Route::get('/bienvenue/voix/{teacher}', [WelcomeController::class, 'voice'])->where('teacher', '[a-z]+')->middleware('throttle:30,1')->name('welcome.voice');

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
        Route::get('/message-vocal/etat', 'voiceStatus')->middleware('throttle:40,1')->name('voice.status');
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
        Route::post('/{course}/partie', [CourseController::class, 'choosePart'])->whereNumber('course')->middleware('throttle:10,1')->name('part');
        Route::delete('/{course}', [CourseController::class, 'destroy'])->whereNumber('course')->middleware('confirm:course.delete')->name('destroy');
        // Document personnel de révision d'une séance : téléchargement, état (mise à jour de la page), « Réessayer ».
        Route::get('/seances/{session}/document', [CourseReviewController::class, 'download'])->whereNumber('session')->name('review.download');
        Route::get('/seances/{session}/document/etat', [CourseReviewController::class, 'status'])->whereNumber('session')->middleware('throttle:30,1')->name('review.status');
        Route::post('/seances/{session}/document/reessayer', [CourseReviewController::class, 'retry'])->whereNumber('session')->middleware('throttle:10,1')->name('review.retry');
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
    Route::put('/inscrits/{user}/compte-de-test', [UserController::class, 'testAccount'])->name('users.test-account');
    // Rôle d'assistant : administrateur principal seulement (hors de AdminAccess::ASSISTANT_ROUTES).
    Route::post('/inscrits/{user}/assistant', [AssistantController::class, 'appoint'])->middleware('confirm:assistant.appoint')->name('users.assistant');
    Route::delete('/inscrits/{user}/assistant', [AssistantController::class, 'remove'])->middleware('confirm:assistant.remove')->name('users.assistant.remove');
    Route::post('/inscrits/{user}/mot-de-passe', [UserController::class, 'temporaryPassword'])->middleware('confirm:password.temporary')->name('users.password');
    Route::post('/inscrits/{user}/acces-offert', [GrantedAccessController::class, 'store'])->middleware('confirm:access.grant')->name('users.grant');
    Route::delete('/inscrits/{user}/acces-offert', [GrantedAccessController::class, 'destroy'])->middleware('confirm:access.revoke')->name('users.revoke');
    // Programme d'ambassadeurs : nommer, renouveler le mois gratuit, retirer, accord d'un parent.
    Route::get('/ambassadeurs', [AdminAmbassadorController::class, 'index'])->name('ambassadors.index');
    Route::post('/inscrits/{user}/ambassadeur', [AdminAmbassadorController::class, 'appoint'])->middleware('confirm:ambassador.appoint')->name('users.ambassador');
    Route::post('/ambassadeurs/{ambassador}/renouveler', [AdminAmbassadorController::class, 'renew'])->middleware('confirm:ambassador.renew')->name('ambassadors.renew');
    Route::delete('/ambassadeurs/{ambassador}', [AdminAmbassadorController::class, 'remove'])->middleware('confirm:ambassador.remove')->name('ambassadors.remove');
    Route::put('/ambassadeurs/{ambassador}/accord-parental', [AdminAmbassadorController::class, 'consent'])->name('ambassadors.consent');
    Route::put('/inscrits/{user}/ambassadeur-rattachement', [AdminAmbassadorController::class, 'referral'])->name('users.referral');
    Route::post('/inscrits/{user}/commission', [AdminAmbassadorController::class, 'addCommission'])->middleware('confirm:commission.add')->name('users.commission');
    Route::post('/commissions/{commission}/payee', [AdminAmbassadorController::class, 'payCommission'])->middleware('confirm:commission.pay')->name('commissions.pay');
    Route::post('/commissions/{commission}/annuler', [AdminAmbassadorController::class, 'cancelCommission'])->middleware('confirm:commission.cancel')->name('commissions.cancel');
    Route::post('/candidatures/{application}/accepter', [AdminAmbassadorController::class, 'acceptApplication'])->middleware('confirm:ambassador.appoint')->name('applications.accept');
    Route::post('/candidatures/{application}/refuser', [AdminAmbassadorController::class, 'rejectApplication'])->middleware('confirm:application.reject')->name('applications.reject');
    Route::get('/liste-d-attente/{entry}/whatsapp', [AdminAmbassadorController::class, 'notifyWaitlist'])->name('waitlist.notify');
    Route::delete('/liste-d-attente/{entry}', [AdminAmbassadorController::class, 'removeWaitlist'])->name('waitlist.remove');
    Route::delete('/inscrits/{user}/evaluation', [AdminAssessmentController::class, 'destroy'])->middleware('confirm:assessment.reset')->name('assessments.destroy');
    // Texte des conversations d'un apprenant : lecture d'une séance, suppression de toutes (demande de suppression).
    Route::get('/inscrits/{user}/conversations/{type}/{id}', [ConversationController::class, 'show'])->whereIn('type', array_keys(Transcripts::TYPES))->whereNumber('id')->name('conversations.show');
    Route::delete('/inscrits/{user}/conversations', [ConversationController::class, 'destroy'])->middleware('confirm:transcripts.delete')->name('conversations.destroy');
    // Export anonymisé du texte des conversations, pour analyser et améliorer Teacher Joe (rien n'est gardé).
    Route::get('/conversations/export-anonymise', [ConversationController::class, 'export'])->middleware('throttle:6,1')->name('conversations.export');
    Route::get('/consommation', [UsageController::class, 'index'])->name('usage.index');
    Route::get('/provenance', [OriginController::class, 'index'])->name('origins.index');
    Route::get('/reglages', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/reglages', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/voix', [VoiceController::class, 'index'])->name('voices.index');
    Route::get('/voix/{voice}/extrait', [VoiceController::class, 'sample'])->where('voice', '[A-Za-z]+')->middleware('throttle:30,1')->name('voices.sample');
    Route::post('/voix', [VoiceController::class, 'choose'])->name('voices.choose');
    Route::get('/voix/test-francais/{provider}/{voice}/extrait', [VoiceSpeechTrialController::class, 'sample'])->whereIn('provider', ['gemini', 'openai'])->where('voice', '[A-Za-z]+')->middleware('throttle:30,1')->name('voices.speech.sample');
    Route::post('/voix/test-francais/preparer', [VoiceSpeechTrialController::class, 'prepare'])->middleware('throttle:6,1')->name('voices.speech.prepare');
    Route::post('/voix/test-francais', [VoiceSpeechTrialController::class, 'choose'])->name('voices.speech.choose');
    Route::post('/voix/test-francais/clarte', [VoiceSpeechTrialController::class, 'measure'])->middleware('throttle:6,1')->name('voices.speech.measure');
    // « Écouter en séance » : vraie connexion de séance avec cette voix, sans micro (VoiceLiveController).
    Route::post('/voix/seance/fin', [VoiceLiveController::class, 'finish'])->name('voices.live.finish');
    Route::post('/voix/seance/{voice}', [VoiceLiveController::class, 'start'])->where('voice', '[A-Za-z]+')->middleware('throttle:30,1')->name('voices.live');
    Route::get('/voix/seance/{voice}/extrait', [VoiceLiveController::class, 'show'])->where('voice', '[A-Za-z]+')->name('voices.live.file');
    Route::post('/voix/seance/{voice}/extrait', [VoiceLiveController::class, 'store'])->where('voice', '[A-Za-z]+')->middleware('throttle:30,1')->name('voices.live.store');
    Route::get('/diagnostic-voix', [VoiceDiagnosticController::class, 'index'])->name('voice-diagnostic.index');
    Route::post('/diagnostic-voix/essai/{provider}', [VoiceDiagnosticController::class, 'test'])->whereIn('provider', ['gemini', 'openai'])->middleware('throttle:10,1')->name('voice-diagnostic.test');
    Route::post('/diagnostic-voix/preparer', [VoiceDiagnosticController::class, 'prepare'])->middleware('throttle:6,1')->name('voice-diagnostic.prepare');
    Route::get('/diagnostic-texte', [TextDiagnosticController::class, 'index'])->name('text-diagnostic.index');
    Route::post('/diagnostic-texte/envoi', [UploadCheckController::class, 'size'])->middleware('throttle:4,1')->name('upload-check.size');
    Route::post('/diagnostic-texte/attente', [UploadCheckController::class, 'wait'])->middleware('throttle:4,1')->name('upload-check.wait');
    Route::post('/diagnostic-texte/essai/{provider}', [TextDiagnosticController::class, 'test'])->whereIn('provider', ['gemini', 'openai'])->middleware('throttle:10,1')->name('text-diagnostic.test');
    Route::post('/diagnostic-texte/direct/{provider}/essai', [LiveTrialController::class, 'test'])->whereIn('provider', ['gemini', 'openai', 'gemini2'])->middleware('throttle:10,1')->name('live-trial.test');
    Route::get('/diagnostic-texte/direct/{provider}/conversation', [LiveTrialController::class, 'show'])->whereIn('provider', ['gemini', 'openai', 'gemini2'])->name('live-trial.show');
    Route::post('/diagnostic-texte/direct/{provider}/conversation/connexion', [LiveTrialController::class, 'connect'])->whereIn('provider', ['gemini', 'openai', 'gemini2'])->middleware('throttle:10,1')->name('live-trial.connect');
    Route::post('/diagnostic-texte/direct/{provider}/conversation/signal', [LiveTrialController::class, 'signal'])->whereIn('provider', ['gemini', 'openai', 'gemini2'])->middleware('throttle:30,1')->name('live-trial.signal');
    Route::post('/diagnostic-texte/direct/{provider}/conversation/fin', [LiveTrialController::class, 'finish'])->whereIn('provider', ['gemini', 'openai', 'gemini2'])->middleware('throttle:10,1')->name('live-trial.finish');
    Route::get('/alertes', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alertes/abonnement', [AlertController::class, 'subscribe'])->middleware('throttle:20,1')->name('alerts.subscribe');
    Route::delete('/alertes/abonnement', [AlertController::class, 'unsubscribe'])->middleware('throttle:20,1')->name('alerts.unsubscribe');
    Route::post('/alertes/essai-notification', [AlertController::class, 'testPush'])->middleware('throttle:10,1')->name('alerts.test-push');
    Route::post('/alertes/essai-email', [AlertController::class, 'testMail'])->middleware('throttle:10,1')->name('alerts.test-mail');
    Route::delete('/alertes/telephones/{subscription}', [AlertController::class, 'destroy'])->name('alerts.destroy');
    Route::get('/icones', IconPreviewController::class)->name('icons.index');
    Route::get('/videos', [AdminVideoController::class, 'index'])->name('videos.index');
    Route::post('/videos/preparer', [AdminVideoController::class, 'prepare'])->middleware('throttle:6,1')->name('videos.prepare');
    Route::put('/videos/{video}/publiee', [AdminVideoController::class, 'publish'])->name('videos.publish');
    Route::post('/videos/{video}/reessayer', [AdminVideoController::class, 'retry'])->name('videos.retry');
    Route::get('/videos/{video}/fichier', [AdminVideoController::class, 'file'])->name('videos.file');
    Route::put('/videos/reglages', [AdminVideoController::class, 'updateSite'])->name('videos.site');
    Route::put('/videos/par-jour', [AdminVideoController::class, 'updatePerDay'])->name('videos.per-day');
    Route::put('/videos/garde', [AdminVideoController::class, 'updateKeepDays'])->name('videos.keep-days');
    Route::post('/videos/{video}/renvoyer', [AdminVideoController::class, 'resendMail'])->name('videos.mail');
    Route::post('/videos/club', [AdminVideoController::class, 'prepareClub'])->middleware('throttle:6,1')->name('videos.club.prepare');
    Route::get('/videos/voix/{provider}/{voice}', [AdminVideoController::class, 'voiceSample'])->where(['provider' => '[a-z]+', 'voice' => '[A-Za-z]+'])->middleware('throttle:20,1')->name('videos.voice-sample');
    Route::post('/videos/voix', [AdminVideoController::class, 'chooseVoice'])->name('videos.voice-choose');
    Route::post('/videos/{video}/refaire', [AdminVideoController::class, 'redo'])->name('videos.redo');
    Route::post('/videos/refaire-aujourd-hui', [AdminVideoController::class, 'redoToday'])->name('videos.redo-today');
    Route::post('/videos/presentations', [PresentationVideoController::class, 'request'])->middleware('throttle:6,1')->name('videos.presentations.request');
    Route::get('/videos/presentations/{presentation}/fichier', [PresentationVideoController::class, 'file'])->name('videos.presentations.file');
    Route::get('/videos/catalogue/{file}', [AdminVideoController::class, 'catalogue'])->whereIn('file', ['video', 'voix'])->name('videos.catalogue');
    Route::get('/abonnes', [SubscriberController::class, 'index'])->name('subscribers.index');
    Route::get('/inactifs', [InactiveLearnerController::class, 'index'])->name('inactive.index');
    Route::get('/inactifs/{user}/whatsapp', [InactiveLearnerController::class, 'whatsapp'])->name('inactive.whatsapp');
});
