<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Textes précis et vrais, jamais de remplissage commercial : on paie par Mobile Money, donc aucune « carte bancaire »,
 * et aucune formule creuse (« sans engagement », « sans risque », « garanti »…) sur les pages que voit le visiteur.
 */
class PreciseWordingTest extends TestCase
{
    use RefreshDatabase;

    private const HOLLOW = ['carte bancaire', 'sans engagement', 'sans piège', '100 %', '100%', 'sans risque', 'rapide et facile', 'garanti'];

    public function test_no_bank_card_and_no_hollow_marketing_on_visitor_and_learner_pages(): void
    {
        $assessed = User::factory()->create();
        $assessed->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now()->subHour(), 'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => Level::A2]);

        $pages = [
            'accueil' => $this->get('/'),
            'formules' => $this->get('/formules'),
            'inscription' => $this->get('/inscription'),
            'pays' => $this->get('/apprendre-anglais/benin'),
            'évaluation' => $this->actingAs(User::factory()->create())->get('/evaluation'),
            'mon espace' => $this->actingAs(User::factory()->create())->get('/espace'),
            'résultat' => $this->actingAs($assessed)->get('/evaluation'),
            'choix de la formule' => $this->actingAs($assessed)->get('/abonnement'),
        ];

        foreach ($pages as $page => $response) {
            $text = mb_strtolower(strip_tags($response->assertOk()->getContent()));

            foreach (self::HOLLOW as $phrase) {
                // « aucun résultat n'est garanti » est une limite honnête, pas une promesse.
                $checked = str_replace(['aucun résultat n\'est garanti', 'aucun résultat n&#039;est garanti'], '', $text);
                $this->assertStringNotContainsString($phrase, $checked, "{$page} : « {$phrase} »");
            }
        }
    }

    public function test_the_home_page_states_precise_facts_instead(): void
    {
        $minutes = config('platform.assessment.max_minutes');

        $this->get('/')->assertOk()
            ->assertSee('Évaluation orale gratuite de '.$minutes.' minutes : votre niveau d\'anglais, de A1 à C2.', false)
            ->assertSee('Des formules à la semaine ou au mois')
            ->assertSee('Avec votre nom et votre numéro de téléphone.');
    }
}
