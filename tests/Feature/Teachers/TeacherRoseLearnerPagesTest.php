<?php

namespace Tests\Feature\Teachers;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pages de l'apprenant qui a choisi Teacher Rose : « Mon espace » parle de SA professeure (nom, accords, visage),
 * jamais de Teacher Joe à sa place. Un apprenant de Teacher Joe garde les textes d'avant.
 */
class TeacherRoseLearnerPagesTest extends TestCase
{
    use RefreshDatabase;

    private function learner(?string $teacher): User
    {
        $user = User::factory()->create(['name' => 'Afi']);
        $user->forceFill(['teacher' => $teacher])->save();
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        return $user;
    }

    public function test_my_space_speaks_of_teacher_rose_when_she_is_chosen(): void
    {
        $this->actingAs($this->learner('rose'))->get('/espace')->assertOk()
            ->assertSee('Votre professeure Teacher Rose est prête.')
            ->assertSee('images/teacher-rose.webp', false)
            ->assertDontSee('Votre professeur Teacher Joe est prêt.');
    }

    public function test_teacher_joe_learners_keep_the_same_texts(): void
    {
        $this->actingAs($this->learner(null))->get('/espace')->assertOk()
            ->assertSee('Votre professeur Teacher Joe est prêt.')
            ->assertSee('images/teacher-joe.webp', false);
    }
}
