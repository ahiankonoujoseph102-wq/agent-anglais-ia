<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\PaymentStatus;
use App\Enums\ProgramLessonStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * La suite d'une étape : UN bouton principal orange, avec une phrase courte, placé AVANT les détails
 * (<x-next-step>, « Mon espace », « Mon programme », page d'un paiement).
 */
class NextStepTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Africa/Lome'));
    }

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user);

        return $user;
    }

    public function test_after_registering_the_next_step_names_the_free_assessment(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/espace')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="btn btn-accent btn-lg btn-block btn-next"[^>]*>.*Commencer mon évaluation gratuite/s', $html);
        // La carte « Prochaine étape » vient avant tout le reste de la page.
        $this->assertLessThan(strpos($html, 'Mon suivi') ?: PHP_INT_MAX, strpos($html, 'Commencer mon évaluation gratuite'));
    }

    public function test_when_the_daily_time_is_used_up_the_next_step_is_to_read_the_last_session(): void
    {
        $user = $this->subscriber();
        $user->lessons()->create(['date' => today(), 'started_at' => now()->subHour(), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed]);

        $this->actingAs($user)->get('/espace')->assertOk()
            ->assertSeeText('Votre temps du jour est épuisé')
            ->assertSeeText('relisez votre dernière séance et votre devoir')
            ->assertSee('href="'.route('lessons.history').'" class="btn btn-lg btn-block btn-next"', false)
            ->assertSeeText('Revoir ma dernière séance');
    }

    public function test_the_program_page_shows_the_next_lesson_first_with_one_orange_button(): void
    {
        $user = $this->subscriber();
        $html = $this->actingAs($user)->get('/programme')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'btn-next'));
        $this->assertStringContainsString('id="suite"', $html);
        $this->assertStringContainsString('Votre programme est prêt', $html);
        $this->assertStringContainsString('Commencer ma première leçon', $html);
        $this->assertStringContainsString('class="btn btn-accent btn-lg btn-block btn-next" href="'.route('lesson.show').'"', $html);
        // Avant les chiffres et le calendrier.
        $this->assertLessThan(strpos($html, 'Progression du niveau'), strpos($html, 'id="suite"'));
        $this->assertLessThan(strpos($html, 'Mon calendrier'), strpos($html, 'id="suite"'));

        // Après une première séance terminée : « Votre prochaine leçon ».
        $user->programs()->first()->programLessons()->orderBy('position')->first()->update(['status' => ProgramLessonStatus::Completed]);
        $this->actingAs($user)->get('/programme')->assertSee('Votre prochaine leçon')->assertSee('Commencer la séance')->assertDontSee('Commencer ma première leçon');
    }

    public function test_a_payment_being_checked_offers_one_main_button_and_whatsapp_as_secondary(): void
    {
        $user = User::factory()->create();
        $payment = $user->payments()->create([
            'plan' => 'essentiel', 'plan_name' => 'Essentiel', 'daily_minutes' => 15, 'duration_days' => 30,
            'amount' => 7500, 'currency' => 'FCFA', 'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+228 90 00 00 22', 'proof_path' => 'payment-proofs/essai.png',
        ]);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $html = $this->actingAs($user)->get(route('payment.show', $payment))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'btn-next'));
        $this->assertStringContainsString('Revenir à mon espace', $html);
        if (str_contains($html, 'btn-whatsapp')) {
            $this->assertLessThan(strpos($html, 'btn-whatsapp'), strpos($html, 'btn-next'));
            $this->assertStringNotContainsString('btn-whatsapp btn-lg', $html);
        }
    }

    public function test_the_component_has_a_large_orange_button_and_a_focusable_title(): void
    {
        $html = $this->blade('<x-next-step title="Titre" text="Ce qui vous attend." href="/suite" label="Continuer">'
            .'<a href="/revoir">Revoir</a></x-next-step>')->__toString();

        $this->assertStringContainsString('aria-labelledby="suite-titre"', $html);
        $this->assertStringContainsString('<h2 id="suite-titre" tabindex="-1">Titre</h2>', $html);
        $this->assertStringContainsString('Ce qui vous attend.', $html);
        $this->assertStringContainsString('class="btn btn-accent btn-lg btn-block btn-next" href="/suite"', $html);
        $this->assertStringContainsString('class="next-step-more"', $html);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.btn-next { min-height: 56px; }', $css);
    }
}
