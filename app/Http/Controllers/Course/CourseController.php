<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Models\CourseLesson;
use App\Models\CoursePage;
use App\Models\User;
use App\Support\Gemini\CourseReader;
use App\Support\PdfPages;
use App\Support\PrivateStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * « Mes cours » : un complément FACULTATIF. L'apprenant apporte une leçon d'anglais de son école
 * (photos ou PDF) ; Teacher Joe la lit, puis l'aide à la comprendre et à s'exercer (séance vocale :
 * App\Http\Controllers\Live\CourseSessionController). Réservé aux comptes ayant un accès actif.
 */
class CourseController extends Controller
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * Liste des leçons, ou l'explication « formule active nécessaire ».
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->activeSubscription === null) {
            return view('courses.index', ['access' => false, 'lessons' => collect(), 'counter' => null]);
        }

        return view('courses.index', [
            'access' => true,
            'lessons' => $user->courseLessons()->latest('id')->paginate(20),
            'counter' => $this->counter($user),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireAccess($request->user())) {
            return $redirect;
        }

        return view('courses.create', [
            'limits' => $this->limits(),
            'error' => session('course_error'),
        ]);
    }

    /**
     * Lecture synchrone de la leçon (QUEUE_CONNECTION=sync), puis enregistrement des fichiers sur le
     * disque privé. Une leçon illisible, d'une autre matière, ou une panne de lecture : rien n'est
     * enregistré ni compté dans la limite du jour (celle des séances).
     */
    public function store(StoreCourseRequest $request, CourseReader $reader): RedirectResponse
    {
        $user = $request->user();

        if ($redirect = $this->requireAccess($user)) {
            return $redirect;
        }

        $limiter = 'course-read:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiter, (int) config('platform.courses.reads_per_hour'))) {
            throw ValidationException::withMessages(['pages' => StoreCourseRequest::waitMessage(RateLimiter::availableIn($limiter))]);
        }

        $pdf = $request->pdf();
        $uploads = $pdf !== null ? [$pdf] : $request->photos();
        $files = array_map(fn ($file) => ['data' => (string) $file->get(), 'mime' => (string) $file->getMimeType()], $uploads);

        RateLimiter::hit($limiter, 3600);
        $reading = $reader->read($files);

        if ($reading['status'] === CourseReader::UNAVAILABLE) {
            // Notre panne, pas celle de l'apprenant : la tentative ne compte pas non plus dans la limite par heure.
            RateLimiter::decrement($limiter, 3600);
        }

        if ($reading['status'] !== CourseReader::LESSON) {
            return to_route('courses.create')
                ->withInput($request->only('title'))
                ->with('course_error', $this->rejection($reading['status'], $pdf !== null));
        }

        $lesson = $this->save($user, $request, $reading, $uploads, $pdf !== null ? PdfPages::count($files[0]['data']) : count($uploads));

        return to_route('courses.show', $lesson)->with('status', 'Votre leçon est prête. Teacher Joe l\'a lue : vous pouvez lui parler.');
    }

    public function show(Request $request, CourseLesson $course): View|RedirectResponse
    {
        $this->ownLesson($request->user(), $course);

        if ($redirect = $this->requireAccess($request->user())) {
            return $redirect;
        }

        return view('courses.show', [
            'course' => $course->load('pages'),
            'counter' => $this->counter($request->user()),
        ]);
    }

    /**
     * Un fichier de la leçon, lu sur le disque privé : son propriétaire et l'administrateur seulement,
     * jamais d'adresse publique.
     */
    public function page(Request $request, CourseLesson $course, CoursePage $page): StreamedResponse
    {
        abort_unless($page->course_lesson_id === $course->id, 404);
        abort_unless($course->user_id === $request->user()->id || $request->user()->isAdmin(), 404);

        $disk = PrivateStorage::disk();

        abort_unless($disk->exists($page->path), 404);

        return $disk->response($page->path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Supprime la leçon ET ses fichiers du disque privé (confirmation demandée avant).
     */
    public function destroy(Request $request, CourseLesson $course): RedirectResponse
    {
        $this->ownLesson($request->user(), $course);

        $course->purge();

        return to_route('courses.index')->with('status', 'La leçon « '.$course->title.' » est supprimée, fichiers compris.');
    }

    /**
     * @param  array<string, mixed>  $reading
     * @param  list<UploadedFile>  $uploads
     */
    private function save(User $user, StoreCourseRequest $request, array $reading, array $uploads, ?int $pageCount): CourseLesson
    {
        $title = trim((string) $request->input('title')) ?: ($reading['title'] ?: 'Leçon du '.today()->translatedFormat('j F Y'));
        $stored = [];
        $disk = PrivateStorage::disk();

        try {
            return DB::transaction(function () use ($user, $reading, $uploads, $pageCount, $title, &$stored, $disk) {
                $lesson = $user->courseLessons()->create([
                    'title' => Str::limit($title, 150, ''),
                    'source' => $uploads[0]->getMimeType() === 'application/pdf' ? CourseLesson::SOURCE_PDF : CourseLesson::SOURCE_PHOTOS,
                    'page_count' => max(1, (int) $pageCount),
                    'content' => array_diff_key($reading, array_flip(['status', 'title', 'text'])),
                    'text' => $reading['text'],
                ]);

                foreach ($uploads as $index => $file) {
                    $mime = (string) $file->getMimeType();
                    $path = trim((string) config('platform.courses.directory'), '/')."/{$user->id}/{$lesson->id}/".Str::uuid().'.'.(self::EXTENSIONS[$mime] ?? 'bin');

                    if ($disk->put($path, (string) $file->get()) === false) {
                        throw new \RuntimeException('Fichier de leçon non enregistré.');
                    }

                    $stored[] = $path;
                    $lesson->pages()->create(['position' => $index + 1, 'path' => $path, 'mime' => $mime, 'size' => (int) $file->getSize()]);
                }

                return $lesson;
            });
        } catch (Throwable $e) {
            // Rien ne reste sur le disque si l'enregistrement échoue.
            foreach ($stored as $path) {
                $disk->delete($path);
            }

            throw $e;
        }
    }

    /**
     * Message de refus affiché sur la page d'envoi.
     *
     * @return array{kind: string, title: string, message: string, tips: list<string>}
     */
    private function rejection(string $status, bool $pdf): array
    {
        return match ($status) {
            CourseReader::UNREADABLE => [
                'kind' => 'unreadable',
                'title' => 'Teacher Joe n\'arrive pas à lire cette page',
                'message' => 'Il préfère ne rien inventer. Envoyez une nouvelle '.($pdf ? 'version' : 'photo').', plus nette :',
                'tips' => $pdf
                    ? [
                        'Si le PDF est une photo de mauvaise qualité, refaites plutôt des photos de vos pages.',
                        'Un PDF protégé ou coupé ne peut pas être lu.',
                    ]
                    : [
                        'Placez la page entière dans la photo, sans rien couper.',
                        'Cherchez de la lumière : près d\'une fenêtre, sans ombre sur le texte.',
                        'Tenez le téléphone bien à plat au-dessus de la page et attendez que l\'image soit nette.',
                        'Reprenez la photo si le texte est flou.',
                    ],
            ],
            CourseReader::NOT_ENGLISH => [
                'kind' => 'not_english',
                'title' => 'Ce n\'est pas une leçon d\'anglais',
                'message' => '« Mes cours » concerne uniquement les cours d\'anglais. Pour une autre matière, Teacher Joe ne peut pas vous aider ici.',
                'tips' => ['Si c\'est bien un cours d\'anglais, reprenez la photo en montrant toute la page.'],
            ],
            default => [
                'kind' => 'unavailable',
                'title' => 'La lecture est momentanément indisponible',
                'message' => 'Teacher Joe ne peut pas lire votre leçon pour le moment. Rien n\'a été compté : réessayez dans quelques minutes.',
                'tips' => [],
            ],
        };
    }

    /**
     * Accès actif (payé ou provisoire de 24 heures) obligatoire pour ajouter ou ouvrir une leçon.
     */
    private function requireAccess(User $user): ?RedirectResponse
    {
        return $user->activeSubscription === null ? to_route('courses.index') : null;
    }

    private function ownLesson(User $user, CourseLesson $course): void
    {
        abort_unless($course->user_id === $user->id, 404);
    }

    /**
     * « Leçons aujourd'hui : 2 sur 5 » (séances avec Teacher Joe, nouvelle leçon ou retour sur une ancienne).
     *
     * @return array{used: int, limit: int, reached: bool}
     */
    private function counter(User $user): array
    {
        $used = $user->courseSessionsToday();
        $limit = (int) config('platform.courses.sessions_per_day');

        return ['used' => $used, 'limit' => $limit, 'reached' => $used >= $limit];
    }

    /**
     * @return array<string, int|float>
     */
    private function limits(): array
    {
        return [
            'photos' => (int) config('platform.courses.max_photos'),
            'pdf_megabytes' => (int) (config('platform.courses.pdf_max_kilobytes') / 1024),
            'pdf_pages' => (int) config('platform.courses.pdf_max_pages'),
            'max_side' => (int) config('platform.courses.photo_max_side'),
            'quality' => (float) config('platform.courses.photo_quality'),
            'photo_max_bytes' => (int) config('platform.courses.photo_max_kilobytes') * 1024,
            'pdf_max_bytes' => (int) config('platform.courses.pdf_max_kilobytes') * 1024,
        ];
    }
}
