<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

class InactiveLearnersTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private function subscriber(string $name, ?int $lastLessonDaysAgo, int $subscribedDaysAgo = 20): User
    {
        $user = User::factory()->create(['name' => $name]);
        Subscription::factory()->for($user)->create(['starts_at' => now()->subDays($subscribedDaysAgo)]);

        if ($lastLessonDaysAgo !== null) {
            $user->lessons()->create([
                'date' => today()->subDays($lastLessonDaysAgo),
                'started_at' => now()->subDays($lastLessonDaysAgo),
                'allowed_seconds' => 900,
                'used_seconds' => 600,
                'status' => LessonStatus::Completed,
            ]);
        }

        return $user;
    }

    public function test_admin_sees_subscribed_learners_inactive_for_three_days_or_more(): void
    {
        $admin = User::factory()->admin()->create();
        $this->subscriber('Actif Hier', 1);
        $this->subscriber('Inactif Trois', 3);
        $this->subscriber('Inactif Dix', 10);
        $this->subscriber('Jamais Venu', null, subscribedDaysAgo: 5);
        $this->subscriber('Nouvel Abonné', null, subscribedDaysAgo: 1);
        // Sans abonnement actif : pas concerné.
        User::factory()->create(['name' => 'Sans Abonnement']);

        $this->actingAs($admin)->get('/admin/inactifs')
            ->assertOk()
            ->assertSee('Apprenants inactifs (3)')
            ->assertSeeInOrder(['Inactif Dix', 'Jamais Venu', 'Inactif Trois'])
            ->assertDontSee('Actif Hier')
            ->assertDontSee('Nouvel Abonné')
            ->assertDontSee('Sans Abonnement')
            ->assertSee('Encourager sur WhatsApp');

        $this->actingAs(User::factory()->create())->get('/admin/inactifs')->assertForbidden();
    }

    public function test_whatsapp_button_opens_a_message_written_by_gemini(): void
    {
        config(['services.gemini.key' => 'cle-test']);
        Http::fake(['*/interactions' => Http::response(['steps' => [
            ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Bonjour Afi, on vous attend pour votre séance !']]],
        ]])]);

        $admin = User::factory()->admin()->create();
        $learner = $this->subscriber('Afi', 4);
        $learner->update(['phone' => '+22891234567']);
        $this->buildProgram($learner);

        $this->actingAs($admin)->get(route('admin.inactive.whatsapp', $learner))
            ->assertRedirect('https://wa.me/22891234567?text='.rawurlencode('Bonjour Afi, on vous attend pour votre séance !'));

        Http::assertSent(fn (Request $request) => str_contains($request['input'], 'aucune séance depuis 4 jours')
            && str_contains($request['input'], 'Niveau A1'));

        // Le message du jour est réutilisé : pas de second appel au modèle.
        $this->actingAs($admin)->get(route('admin.inactive.whatsapp', $learner))->assertRedirect();
        Http::assertSentCount(1);
    }

    public function test_whatsapp_message_falls_back_to_a_template_without_gemini(): void
    {
        config(['services.gemini.key' => null]);
        $admin = User::factory()->admin()->create();
        $learner = $this->subscriber('Komla', 6);

        $location = $this->actingAs($admin)->get(route('admin.inactive.whatsapp', $learner))->headers->get('Location');

        $this->assertStringStartsWith('https://wa.me/', $location);
        $this->assertStringContainsString(rawurlencode('Bonjour Komla'), $location);
        $this->assertStringContainsString(rawurlencode('Cela fait 6 jours'), $location);
    }
}
