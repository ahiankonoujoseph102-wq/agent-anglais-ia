<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\SiteGuide;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coût des séances en direct : le contexte (consignes + audio) est refacturé à chaque réponse du professeur.
 * La fiche du site est réduite aux parties utiles à chaque conversation, et l'audio gardé est borné pour la séance
 * du jour et « Mes cours » ; l'évaluation et le bilan de niveau gardent tout.
 */
class LiveCostReductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_guide_keeps_only_the_parts_useful_to_each_conversation(): void
    {
        $full = SiteGuide::text();
        $assessment = SiteGuide::text('assessment');
        $lesson = SiteGuide::text('lesson');
        $course = SiteGuide::text('course');

        // Prospect (évaluation) : paiement, étapes, pays sans paiement en ligne ; pas le questionnaire.
        $this->assertStringContainsString('## Payer, étape par étape', $assessment);
        $this->assertStringContainsString('## Les étapes, dans l\'ordre', $assessment);
        $this->assertStringNotContainsString('## Le questionnaire', $assessment);

        // Abonné en séance : questionnaire et accès offert ; ni le paiement pas à pas ni les étapes d'inscription.
        $this->assertStringContainsString('## Le questionnaire', $lesson);
        $this->assertStringContainsString('## Accès offert', $lesson);
        $this->assertStringNotContainsString('## Payer, étape par étape', $lesson);
        $this->assertStringNotContainsString('## Mes cours', $lesson);
        $this->assertStringContainsString('## Mes cours', $course);

        // Toujours : les prix réels, le renouvellement et l'équipe.
        foreach ([$assessment, $lesson, $course] as $guide) {
            $this->assertStringContainsString('## Les formules', $guide);
            $this->assertStringContainsString('Renouveler ou changer de formule', $guide);
            $this->assertStringContainsString('## Joindre l\'équipe', $guide);
            $this->assertStringNotContainsString('<!--', $guide);
        }

        $this->assertLessThan(mb_strlen($full) * 0.5, mb_strlen($lesson));
        $this->assertSame($full, SiteGuide::text(null));
    }

    public function test_the_lesson_and_course_windows_are_bounded_but_never_the_assessment_or_level_test(): void
    {
        config(['services.gemini.context_window' => ['audio_tokens' => 12000, 'kinds' => ['lesson', 'course']]]);
        $user = User::factory()->level(Level::A2)->create();

        $lesson = TeacherSetup::lesson($user, 15);
        $instructions = $lesson['systemInstruction']['parts'][0]['text'];
        $window = $lesson['contextWindowCompression'];

        $this->assertSame((int) ceil(mb_strlen($instructions) / 3) + 12000, $window['triggerTokens']);
        $this->assertSame($window['triggerTokens'] - 6000, $window['slidingWindow']['targetTokens']);

        $this->assertArrayHasKey('triggerTokens', TeacherSetup::course($user, 10, 'Leçon : les nombres.')['contextWindowCompression']);
        $this->assertEquals(['slidingWindow' => (object) []], TeacherSetup::assessment($user, 5)['contextWindowCompression']);
        $this->assertEquals(['slidingWindow' => (object) []], TeacherSetup::levelTest($user, Level::A2, 12)['contextWindowCompression']);

        config(['services.gemini.context_window.audio_tokens' => 0]);
        $this->assertEquals(['slidingWindow' => (object) []], TeacherSetup::lesson($user, 15)['contextWindowCompression']);
    }

    public function test_the_lesson_instructions_are_much_shorter_than_before(): void
    {
        $user = User::factory()->level(Level::A1)->create();

        // Avant : environ 52 000 caractères sans le contexte de la leçon (fiche du site entière comprise).
        $this->assertLessThan(42_000, mb_strlen(TeacherSetup::instructions('lesson', $user, 15, ['context' => ''])));
    }
}
