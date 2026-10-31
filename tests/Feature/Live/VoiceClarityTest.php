<?php

namespace Tests\Feature\Live;

use App\Models\User;
use App\Support\Live\VoiceClarity;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Voix plus nette » (31 octobre 2026) : même voix, rendu plus net dans le téléphone (public/js/voice-clarity.js,
 * testé par tests/js/voice-clarity.test.mjs). Désactivé par défaut : aucun élève ne l'entend tant que
 * l'administrateur ne l'a pas choisi dans « Réglages » ; l'équipe peut comparer les deux sons sur la page.
 */
class VoiceClarityTest extends TestCase
{
    use RefreshDatabase;

    private function audio(User $user, string $query = ''): array
    {
        $html = $this->actingAs($user)->get('/evaluation'.$query)->assertOk()->getContent();
        preg_match("/data-audio='([^']+)'/", $html, $match);

        return json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
    }

    private function setMode(string $mode): void
    {
        SiteSettings::save([VoiceClarity::SETTING => $mode]);
        SiteSettings::flush();
    }

    public function test_nobody_hears_a_change_by_default(): void
    {
        $this->assertSame('off', VoiceClarity::mode());

        $learner = User::factory()->create();
        $audio = $this->audio($learner);
        $this->assertSame('classic', $audio['voice_mode']);
        // Le son actuel garde son volume d'avant.
        $this->assertSame(2.5, $audio['voice_gain']);
        $this->assertSame(config('platform.live.voice_clarity'), $audio['voice_clear']);

        // Un élève ne peut ni forcer le nouveau son, ni voir le lien de comparaison.
        $this->assertSame('classic', $this->audio($learner, '?son=net')['voice_mode']);
        $this->get('/evaluation?son=net')->assertDontSee('id="voice-compare"', false);
        $this->assertSame('classic', $this->audio(User::factory()->admin()->create())['voice_mode']);
    }

    public function test_the_team_compares_both_sounds_on_the_same_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame('clear', $this->audio($admin, '?son=net')['voice_mode']);
        $this->get('/evaluation?son=net')
            ->assertSee('Son de la voix : <strong>plus net</strong>', false)
            ->assertSee('Essayer le son actuel');

        $this->assertSame('classic', $this->audio($admin, '?son=actuel')['voice_mode']);
        $this->get('/evaluation?son=actuel')->assertSee('Essayer le son plus net');
    }

    public function test_the_setting_chooses_who_hears_it(): void
    {
        $learner = User::factory()->create();
        $tester = User::factory()->create();
        $tester->forceFill(['is_test_account' => true])->save();
        $admin = User::factory()->admin()->create();

        $this->setMode('test');
        $this->assertSame('classic', $this->audio($learner)['voice_mode']);
        $this->assertSame('clear', $this->audio($tester)['voice_mode']);
        $this->assertSame('clear', $this->audio($admin)['voice_mode']);

        $this->setMode('all');
        $this->assertSame('clear', $this->audio($learner)['voice_mode']);

        $this->setMode('off');
        $this->assertSame('classic', $this->audio($learner)['voice_mode']);
    }

    public function test_the_settings_page_saves_the_choice(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('id="voix-plus-nette"', false)
            ->assertSee('Désactivé (son actuel pour tout le monde)');

        $this->actingAs($admin)->put(route('admin.settings.update'), ['voice' => ['clarity' => 'test']])->assertRedirect();
        SiteSettings::flush();
        $this->assertSame('test', VoiceClarity::mode());

        $this->actingAs($admin)->put(route('admin.settings.update'), ['voice' => ['clarity' => 'autre']])
            ->assertSessionHasErrors('voice.clarity');
        SiteSettings::flush();
        $this->assertSame('test', VoiceClarity::mode());
    }
}
