<?php

namespace Tests\Feature\Live;

use App\Models\User;
use App\Support\Avatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimatedTeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_screen_offers_the_3d_teacher_and_keeps_the_svg_face(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk();

        $response->assertSee('id="mouth"', false)
            ->assertSee('data-teacher3d=', false)
            ->assertSee('js/teacher-view.js?v=', false)
            ->assertSee('teacher-joe.glb?v=', false)
            ->assertSee('three-teacher.min.js?v=', false)
            // Attente de l'arrivée du professeur : son nom (configuration), une phrase rassurante, aucun mot technique.
            ->assertSee(config('avatar.model.name').' arrive…')
            ->assertSee('Patientez un instant.')
            ->assertDontSee('Chargement du professeur')
            ->assertDontSee('3D…');
    }

    public function test_the_learner_never_reads_technical_words_about_the_teacher(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        // Texte visible de la page (sans balises, attributs, scripts ni commentaires).
        $text = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html));

        foreach (['3D', 'Chargement', 'Visage simple', 'repli', 'mode allégé', 'serveur'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text, $word);
        }

        // Textes écrits par le navigateur : lien de réglage et messages de la séance.
        $view = file_get_contents(public_path('js/teacher-view.js'));
        $this->assertStringContainsString("const LIGHT = 'Affichage plus léger';", $view);
        $this->assertStringContainsString('const ANIMATED = `Revoir ${name} animé`;', $view);
        $this->assertStringNotContainsString("showToggle('Visage simple'", $view);
        $this->assertStringNotContainsString('Afficher le professeur en 3D', $view);
        $session = file_get_contents(public_path('js/live-session.js'));
        $this->assertStringNotContainsString('Impossible de joindre le serveur', $session);
        $this->assertStringNotContainsString('limite de conversations gratuites', $session);
        $this->assertStringNotContainsString('Connexion au professeur', $session);
    }

    public function test_svg_face_only_when_the_3d_teacher_is_disabled(): void
    {
        config(['avatar.enabled' => false]);

        $this->actingAs(User::factory()->create())->get('/evaluation')
            ->assertOk()
            ->assertSee('id="mouth"', false)
            ->assertDontSee('data-teacher3d=', false);
    }

    public function test_asset_versions_follow_the_file_content(): void
    {
        $url = Avatar::asset('teacher3d/models/teacher-joe.glb');
        $hash = substr(hash_file('xxh128', public_path('teacher3d/models/teacher-joe.glb')), 0, 12);

        $this->assertStringEndsWith('teacher3d/models/teacher-joe.glb?v='.$hash, $url);
    }
}
