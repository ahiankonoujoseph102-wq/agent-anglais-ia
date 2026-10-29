<?php

namespace Tests\Feature\Teachers;

use App\Support\Guides;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teacher Rose sur les pages publiques (étape 2 de « Teacher Rose partout ») : les deux professeurs présentés, le
 * choix possible chaque jour, Teacher Joe gardé comme nom de la marque. Avec un seul professeur proposé (retour à
 * Teacher Joe seul), les pages publiques ne parlent plus de Teacher Rose et reprennent les images d'avant.
 */
class TeacherRosePublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net']);
    }

    /** @return list<string> */
    private function publicPages(): array
    {
        return ['/', '/formules', '/a-propos', '/comment-ca-marche', '/confidentialite', '/apprendre-anglais/togo',
            '/aide-aux-cours-d-anglais', '/preparer-un-entretien-en-anglais', '/guides', '/guides/'.Guides::slugs()[0]];
    }

    public function test_the_home_page_presents_both_teachers_and_the_daily_choice(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<h2 id="professeurs">Teacher Joe ou Teacher Rose : à vous de choisir</h2>', false)
            ->assertSee('images/teacher-rose.webp', false)
            ->assertSee('Teacher Rose, la professeure d\'anglais de Talkami', false)
            ->assertSee('vous pouvez en changer chaque jour')
            ->assertSee('Pour l\'évaluation gratuite, vous choisissez aussi avant de commencer.')
            ->assertSee('Puis-je choisir mon professeur ?')
            ->assertSee('href="#professeurs"', false)
            // Teacher Joe reste le nom de la marque : titre et accroche inchangés.
            ->assertSee('Parlez anglais avec <span class="accent">Teacher Joe</span>, chaque jour', false);
    }

    public function test_every_public_page_mentions_teacher_rose(): void
    {
        foreach ($this->publicPages() as $page) {
            $this->get($page)->assertOk()->assertSee('Teacher Rose');
        }
    }

    public function test_structured_data_and_share_images_show_both_teachers(): void
    {
        $raw = $this->get('/')->assertOk()->rawContent();

        $this->assertStringContainsString('Cours d\'anglais oral en ligne avec Teacher Joe ou Teacher Rose', html_entity_decode($raw, ENT_QUOTES));
        $this->assertStringContainsString('<meta property="og:image" content="https://talkami.net/images/partage-talkami-duo.jpg">', $raw);
        $this->assertFileExists(public_path('images/teacher-rose.webp'));
        $this->assertSame([560, 560], array_slice(getimagesize(public_path('images/teacher-rose.webp')), 0, 2));
    }

    public function test_back_to_teacher_joe_alone_removes_teacher_rose_from_public_pages(): void
    {
        config(['teachers.list.rose.ready' => false]);

        foreach ($this->publicPages() as $page) {
            $this->get($page)->assertOk()->assertDontSee('Teacher Rose');
        }

        $this->get('/')->assertDontSee('id="professeurs"', false)->assertDontSee('Puis-je choisir mon professeur ?');
        $this->assertSame(Seo::SHARE_IMAGE, Seo::shareImage());
        $this->get('/apprendre-anglais/togo')->assertSee('images/partage/pays-togo.jpg', false)->assertDontSee('-duo.jpg', false);
    }
}
