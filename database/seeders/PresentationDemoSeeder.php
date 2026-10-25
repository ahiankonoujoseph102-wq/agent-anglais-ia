<?php

namespace Database\Seeders;

use App\Enums\AssessmentStatus;
use App\Enums\ErrorStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\ProgramLessonStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Attestation;
use App\Models\CourseLesson;
use App\Models\CourseReview;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\ProgramBuilder;
use App\Support\Plan;
use App\Support\PrivateStorage;
use App\Support\Videos\VideoSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Base de DÉMONSTRATION des vidéos de présentation (tools/presentation) : comptes fictifs « Élève » à chaque étape
 * du parcours, sur une base LOCALE vide. Jamais en production, aucune donnée réelle, aucun vrai paiement : le paiement
 * en attente n'a ni capture ni numéro de transaction (rien n'est lu, rien n'est envoyé).
 *
 * php artisan db:seed --class=PresentationDemoSeeder
 */
class PresentationDemoSeeder extends Seeder
{
    /** Mot de passe commun des comptes de démonstration (base locale seulement). */
    public const PASSWORD = 'demo-presentation';

    /** Numéros fictifs (plage +228 99 00 00 0x), un par étape du parcours. */
    public const ACCOUNTS = [
        'fresh' => '+22899000001',     // compte neuf : évaluation à passer
        'assessed' => '+22899000002',  // évaluation passée (A2), pas encore de formule
        'pending' => '+22899000003',   // capture envoyée : accès provisoire de 24 heures
        'welcome' => '+22899000004',   // paiement validé, message de bienvenue et questionnaire à venir
        'active' => '+22899000005',    // formule, programme, séances, « Mes cours », attestation
        'abroad' => '+33600000006',    // pays sans paiement en ligne (WhatsApp)
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Base de démonstration refusée en production.');

            return;
        }

        VideoSettings::saveSite(VideoSettings::site() ?? 'talkami.laravel.cloud');
        config(['services.gemini.key' => null, 'services.openai.key' => null]);

        $fresh = $this->learner('fresh', 'TG', null);
        $assessed = $this->learner('assessed', 'TG', Level::A2);
        $this->assessment($assessed, Level::A2);

        $pending = $this->learner('pending', 'TG', Level::A2);
        $this->assessment($pending, Level::A2);
        $plan = Plan::find('essentiel');
        // Image neutre « Image de démonstration » : jamais une fausse confirmation de paiement.
        $proof = "payment-proofs/demo-{$pending->id}.png";
        PrivateStorage::disk()->put($proof, self::picture(['Image de demonstration', 'Aucun paiement']));
        $payment = $pending->payments()->create([
            'plan' => $plan->key, 'plan_name' => $plan->name, 'daily_minutes' => $plan->dailyMinutes,
            'duration_days' => $plan->durationDays, 'amount' => $plan->price, 'currency' => (string) config('platform.currency'),
            'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => (string) config('payment.countries.TG.operators.flooz.number'), 'proof_path' => $proof,
        ]);
        $payment->grantProvisional();

        $welcome = $this->learner('welcome', 'TG', Level::A2, welcomed: false);
        $this->assessment($welcome, Level::A2);
        Subscription::factory()->for($welcome)->forPlan('essentiel')->create(['starts_at' => now()->subMinutes(5)]);

        $active = $this->learner('active', 'TG', Level::A2);
        $this->assessment($active, Level::A2);
        $this->activeLearner($active);

        $abroad = $this->learner('abroad', 'FR', Level::A2);
        $this->assessment($abroad, Level::A2);

        $this->command?->info('Comptes de démonstration créés (mot de passe : '.self::PASSWORD.').');
        unset($fresh);
    }

    private function learner(string $key, string $country, ?Level $level, bool $welcomed = true): User
    {
        $user = User::factory()->create([
            'name' => 'Élève',
            'phone' => self::ACCOUNTS[$key],
            'country' => $country,
            'password' => Hash::make(self::PASSWORD),
            'welcomed_at' => $welcomed ? now() : null,
        ]);

        if ($level !== null) {
            $user->level = $level;
            $user->save();
        }

        return $user;
    }

    private function assessment(User $user, Level $level): void
    {
        $user->assessment()->create([
            'status' => AssessmentStatus::Completed, 'started_at' => now()->subDays(3), 'ended_at' => now()->subDays(3)->addMinutes(7),
            'allowed_seconds' => 480, 'used_seconds' => 420, 'level' => $level,
            'feedback' => 'Vous comprenez les questions simples et vous répondez avec des phrases courtes. Travaillons ensemble les verbes au passé.',
        ]);
    }

    /** Formule Essentiel, programme A2 commencé, deux séances faites, une leçon « Mes cours » et une attestation A1. */
    private function activeLearner(User $user): void
    {
        $subscription = Subscription::factory()->for($user)->forPlan('essentiel')->create([
            'status' => SubscriptionStatus::Active, 'starts_at' => now()->subDays(3),
        ]);

        $program = app(ProgramBuilder::class)->build($user, Level::A2, [
            'goal' => 'work', 'occupation' => 'Commerçante au Grand Marché de Lomé', 'interests' => 'musique',
            'study_days' => [1, 2, 3, 4, 5, 6, 7], 'study_time' => '18:30', 'difficulties' => ['speaking', 'pronunciation'],
        ]);

        foreach ($program->lessons()->orderBy('position')->take(2)->get() as $index => $programLesson) {
            $programLesson->update(['status' => ProgramLessonStatus::Completed, 'completed_at' => now()->subDays(2 - $index), 'attempts' => 1]);
            $lesson = $user->lessons()->create([
                'subscription_id' => $subscription->id, 'program_id' => $program->id, 'program_lesson_id' => $programLesson->id,
                'date' => today()->subDays(2 - $index), 'started_at' => now()->subDays(2 - $index), 'ended_at' => now()->subDays(2 - $index)->addMinutes(15),
                'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed, 'outcome' => 'completed',
                'summary' => $index === 0 ? '« Nice to meet you. » Vous savez vous présenter et dire votre métier.' : '« Can you repeat, please? » Vous savez demander de répéter et de parler plus lentement.',
                'homework' => $index === 0 ? 'Présentez-vous à voix haute en trois phrases.' : 'Dites trois phrases sur votre journée de travail.',
            ]);

            if ($index === 1) {
                $user->learnerErrors()->create(['lesson_id' => $lesson->id, 'wrong' => 'I have 25 years.', 'correct' => 'I\'m 25.', 'explanation' => 'En anglais, on dit son âge avec « to be ».', 'status' => ErrorStatus::Open, 'times_seen' => 1]);
            }
        }

        $user->attestations()->create(['level' => Level::A1, 'code' => Attestation::newCode(Level::A1), 'issued_at' => now()->subDays(20)]);

        $course = $user->courseLessons()->create([
            'title' => 'Le présent simple',
            'source' => CourseLesson::SOURCE_PHOTOS,
            'page_count' => 1,
            'content' => [
                'summary' => 'Cette leçon explique le présent simple et donne des exercices.',
                'level' => 'A2',
                'grammar' => [['point' => 'Present simple', 'explanation' => 'On ajoute -s à la troisième personne du singulier.', 'examples' => ['She works in Lomé.', 'He plays football.']]],
                'vocabulary' => [['english' => 'to work', 'french' => 'travailler'], ['english' => 'market', 'french' => 'marché']],
                'examples' => ['I go to the market every day.'],
                'exercises' => ['Complete: She ___ (to work) in a bank.'],
            ],
            'text' => 'Present simple. She works in a bank. Exercise 1: complete the sentences.',
        ]);
        $path = "courses/{$user->id}/{$course->id}/page-1.png";
        PrivateStorage::disk()->put($path, self::picture(['Present simple', '', 'I work in Lome.', 'She works in a bank.', 'He plays football.', '', 'Exercise 1: complete', 'She ___ (to work) in a bank.', 'They ___ (to go) to school.'], true));
        $course->pages()->create(['position' => 1, 'path' => $path, 'mime' => 'image/png', 'size' => strlen((string) PrivateStorage::disk()->get($path))]);

        $session = $user->courseSessions()->create([
            'course_lesson_id' => $course->id, 'date' => today()->subDay(), 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addMinutes(9),
            'allowed_seconds' => 600, 'used_seconds' => 540, 'status' => LessonStatus::Completed,
        ]);
        $reviewPath = "course-reviews/{$user->id}/{$session->id}-demo.pdf";
        PrivateStorage::disk()->put($reviewPath, "%PDF-1.4\n% Document de démonstration\n%%EOF");
        CourseReview::query()->create([
            'user_id' => $user->id, 'course_session_id' => $session->id, 'course_lesson_id' => $course->id,
            'status' => CourseReview::READY, 'path' => $reviewPath, 'prepared_at' => now()->subDay(),
            'content' => ['lesson' => 'Le présent simple'],
        ]);
    }

    /**
     * Image dessinée (aucune vraie photo) : page de cahier lignée, ou carte neutre.
     *
     * @param  list<string>  $lines
     */
    private static function picture(array $lines, bool $notebook = false): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');
        }

        $image = imagecreatetruecolor(600, 800);
        imagefill($image, 0, 0, imagecolorallocate($image, 252, 250, 240));
        $line = imagecolorallocate($image, 190, 205, 230);
        $ink = imagecolorallocate($image, 14, 42, 92);

        for ($y = 90; $notebook && $y < 800; $y += 40) {
            imageline($image, 30, $y, 570, $y, $line);
        }

        foreach ($lines as $i => $text) {
            imagestring($image, 5, 50, 70 + $i * 40, $text, $ink);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
