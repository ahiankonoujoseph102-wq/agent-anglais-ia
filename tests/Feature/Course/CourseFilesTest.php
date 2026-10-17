<?php

namespace Tests\Feature\Course;

use App\Models\CourseLesson;
use App\Models\CoursePage;
use App\Models\User;
use App\Support\Confirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

class CourseFilesTest extends TestCase
{
    use HasCourses, RefreshDatabase;

    public function test_the_list_shows_the_most_recent_lesson_first_with_title_date_and_pages(): void
    {
        $user = $this->learner();
        $old = $this->storedLesson($user, 'Ancienne leçon', 1);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->storedLesson($user, 'Leçon récente', 3);
        $this->storedLesson($this->learner(), 'Leçon d\'un autre');

        $this->actingAs($user)->get('/mes-cours')
            ->assertOk()
            ->assertSeeInOrder(['Leçon récente', now()->translatedFormat('j F Y'), '3 pages', 'Ancienne leçon', now()->subDays(3)->translatedFormat('j F Y'), '1 page'])
            ->assertDontSee('Leçon d\'un autre')
            ->assertSee('Supprimer');
    }

    public function test_the_lesson_page_shows_what_was_read_and_the_pages(): void
    {
        $user = $this->learner();
        $lesson = $this->storedLesson($user);

        $this->actingAs($user)->get(route('courses.show', $lesson))
            ->assertOk()
            ->assertSeeText('Cette leçon explique le présent simple')
            ->assertSeeText('Points de grammaire (1)')
            ->assertSeeText('Vocabulaire (2)')
            ->assertSeeText('Exercices (1)')
            ->assertSee(route('courses.page', [$lesson, $lesson->pages->first()]), false)
            ->assertSeeText('Ces pages restent privées')
            ->assertSee('Parler avec Teacher Joe');
    }

    public function test_the_owner_and_the_administrator_read_the_files_nobody_else(): void
    {
        $owner = $this->learner();
        $lesson = $this->storedLesson($owner);
        $page = $lesson->pages->first();
        $url = route('courses.page', [$lesson, $page]);

        $response = $this->actingAs($owner)->get($url)->assertOk();
        $this->assertSame('octets-de-la-page-1', $response->streamedContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();

        // Un autre apprenant, même avec un accès actif : introuvable (jamais « interdit », pour ne rien révéler).
        $this->actingAs($this->learner())->get($url)->assertNotFound();
        $this->actingAs($this->learner())->get(route('courses.show', $lesson))->assertNotFound();

        // Un visiteur : renvoyé vers la connexion.
        auth()->logout();
        $this->get($url)->assertRedirect('/connexion');

        // Une page qui n'appartient pas à cette leçon.
        $other = $this->storedLesson($owner, 'Autre');
        $this->actingAs($owner)->get(route('courses.page', [$lesson, $other->pages->first()]))->assertNotFound();
    }

    public function test_files_are_never_reachable_by_a_public_address(): void
    {
        $lesson = $this->storedLesson($this->learner());
        $path = $lesson->pages->first()->path;

        $this->assertFileDoesNotExist(public_path($path));
        $this->assertFileDoesNotExist(public_path('storage/'.$path));
        $this->get('/storage/'.$path)->assertNotFound();
        $this->get('/'.$path)->assertNotFound();
        Storage::disk('public')->assertMissing($path);
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    public function test_deletion_asks_for_the_talkami_confirmation_not_the_browser_one(): void
    {
        $user = $this->learner();
        $lesson = $this->storedLesson($user);
        $texts = Confirmation::for('course.delete');

        $this->assertSame('Supprimer cette leçon ?', $texts['title']);

        // Avec JavaScript : le formulaire porte les textes de la fenêtre intégrée.
        $this->actingAs($user)->get('/mes-cours')
            ->assertSee('data-confirm="course.delete"', false)
            ->assertSee('data-confirm-title="Supprimer cette leçon ?"', false)
            ->assertDontSee('onsubmit', false);

        // Sans JavaScript : une page de confirmation, rien n'est supprimé.
        $this->actingAs($user)->delete(route('courses.destroy', $lesson))
            ->assertOk()
            ->assertSee('Supprimer cette leçon ?')
            ->assertSee('Supprimer la leçon');
        $this->assertSame(1, CourseLesson::count());
        Storage::disk('local')->assertExists($lesson->pages->first()->path);
    }

    public function test_deleting_a_lesson_erases_its_files_and_keeps_the_daily_count(): void
    {
        config(['filesystems.private_disk' => 'bucket']);
        Storage::fake('bucket');
        $user = $this->learner();
        $lesson = $this->storedLesson($user, 'À effacer', 2);
        $paths = $lesson->pages->pluck('path')->all();

        foreach ($paths as $path) {
            Storage::disk('bucket')->put($path, 'octets');
        }

        $session = $user->courseSessions()->create(['course_lesson_id' => $lesson->id, 'date' => today(), 'allowed_seconds' => 600, 'started_at' => now()]);

        $this->actingAs($user)->delete(route('courses.destroy', $lesson), ['confirmed' => 1])
            ->assertRedirect('/mes-cours')
            ->assertSessionHas('status', 'La leçon « À effacer » est supprimée, fichiers compris.');

        $this->assertSame(0, CourseLesson::count());
        $this->assertSame(0, CoursePage::count());

        foreach ($paths as $path) {
            Storage::disk('bucket')->assertMissing($path);
        }

        $this->assertNull($session->fresh()->course_lesson_id);
        $this->assertSame(1, $user->courseSessionsToday());
    }

    public function test_nobody_else_can_delete_a_lesson(): void
    {
        $lesson = $this->storedLesson($this->learner());

        $this->actingAs($this->learner())->delete(route('courses.destroy', $lesson), ['confirmed' => 1])->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->delete(route('courses.destroy', $lesson), ['confirmed' => 1])->assertNotFound();

        $this->assertSame(1, CourseLesson::count());
        Storage::disk('local')->assertExists($lesson->pages->first()->path);
    }

    public function test_an_upload_that_fails_while_saving_leaves_no_file_behind(): void
    {
        config(['filesystems.private_disk' => 'bucket', 'services.gemini.key' => 'cle']);
        Storage::fake('bucket');
        $this->fakeGemini();
        $user = $this->learner();

        // L'enregistrement de la deuxième page échoue APRÈS la lecture : la première ne doit pas rester sur le disque.
        CoursePage::creating(function (CoursePage $page) {
            if ($page->position === 2) {
                throw new \RuntimeException('Échec simulé de la base');
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs($user)->post('/mes-cours', ['pages' => [$this->photo('a.jpg'), $this->photo('b.jpg')]]);
            $this->fail('L\'erreur simulée aurait dû remonter.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Échec simulé de la base', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('bucket')->allFiles());
        $this->assertSame(0, CourseLesson::count());
        $this->assertSame(0, CoursePage::count());
    }
}
