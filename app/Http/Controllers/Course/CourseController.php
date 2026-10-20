<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\ChooseCoursePartRequest;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Models\CourseLesson;
use App\Models\CoursePage;
use App\Models\User;
use App\Support\DailyTime;
use App\Support\Gemini\CourseReader;
use App\Support\PdfPages;
use App\Support\PrivateStorage;
use App\Support\UploadLimits;
use Illuminate\Http\JsonResponse;
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
    public function store(StoreCourseRequest $request, CourseReader $reader): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($redirect = $this->requireAccess($user)) {
            return $this->respond($request, $redirect);
        }

        // Lecture d'un document jusqu'à 50 pages : deux fournisseurs au plus, chacun avec son délai.
        self::allowLongReading();

        $limiter = 'course-read:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiter, (int) config('platform.courses.reads_per_hour'))) {
            throw ValidationException::withMessages(['pages' => StoreCourseRequest::waitMessage(RateLimiter::availableIn($limiter))]);
        }

        $pdf = $request->pdf();
        $uploads = $pdf !== null ? [$pdf] : $request->photos();
        $files = array_map(fn ($file) => ['data' => (string) $file->get(), 'mime' => (string) $file->getMimeType()], $uploads);

        RateLimiter::hit($limiter, 3600);
        $pageCount = $pdf !== null ? PdfPages::count($files[0]['data']) : count($uploads);
        // Long PDF : seulement le sommaire à l'envoi ; la partie à travailler est lue avant la séance.
        $byParts = $pdf !== null && $pageCount > (int) config('platform.courses.part_max_pages');
        $reading = $byParts ? $reader->outline($files[0]['data']) : $reader->read($files);

        if ($reading['status'] === CourseReader::UNAVAILABLE) {
            // Notre panne, pas celle de l'apprenant : la tentative ne compte pas non plus dans la limite par heure.
            RateLimiter::decrement($limiter, 3600);
        }

        if ($reading['status'] !== CourseReader::LESSON) {
            return $this->respond($request, to_route('courses.create')
                ->withInput($request->only('title'))
                ->with('course_error', $this->rejection($reading['status'], $pdf !== null)));
        }

        $lesson = $this->save($user, $request, $reading + ['text' => null], $uploads, $pageCount);
        $teacher = config('avatar.model.name');

        return $this->respond($request, $byParts
            ? redirect()->to(route('courses.show', $lesson).'#partie')->with('status', "{$teacher} a parcouru vos {$pageCount} pages. Choisissez maintenant la partie à travailler.")
            : redirect()->to(route('courses.show', $lesson).'#travailler')->with('status', "Votre leçon est prête. {$teacher} l'a lue : vous pouvez lui parler."));
    }

    /**
     * Partie d'un PDF à travailler à la prochaine séance : une partie déjà lue (reprise, aucune nouvelle lecture),
     * ou des pages / un passage. Long PDF : la partie est lue maintenant (part_max_pages pages au plus) et gardée ;
     * PDF court, déjà lu en entier : la partie indique seulement à Teacher Joe sur quoi se concentrer.
     */
    public function choosePart(ChooseCoursePartRequest $request, CourseReader $reader, CourseLesson $course): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($redirect = $this->requireAccess($user)) {
            return $this->respond($request, $redirect);
        }

        $back = route('courses.show', $course);
        $part = $request->existingPart();
        [$from, $to] = $request->pages();
        $description = $request->description();

        // Même choix qu'une partie déjà lue : reprise, sans relire le fichier.
        $part ??= $course->parts()
            ->where('page_from', $from)->where('page_to', $to)
            ->where('request', $description)
            ->when($course->readByParts(), fn ($query) => $query->whereNotNull('content'))
            ->first();

        if ($part === null && $course->readByParts()) {
            self::allowLongReading();
            $limiter = 'course-read:'.$user->id;

            if (RateLimiter::tooManyAttempts($limiter, (int) config('platform.courses.reads_per_hour'))) {
                throw ValidationException::withMessages(['from' => StoreCourseRequest::waitMessage(RateLimiter::availableIn($limiter))]);
            }

            $file = $course->pages()->first();
            $pdf = $file !== null ? PrivateStorage::disk()->get($file->path) : null;

            if ($pdf === null) {
                return $this->respond($request, redirect()->to($back.'#partie')->with('status', 'Le fichier de cette leçon est introuvable. Supprimez-la et envoyez-la de nouveau.'));
            }

            RateLimiter::hit($limiter, 3600);
            $reading = $reader->readPart($pdf, $from, $to, $description, (array) ($course->content['outline'] ?? []));

            if ($reading['status'] === CourseReader::UNAVAILABLE) {
                RateLimiter::decrement($limiter, 3600);
            }

            if ($reading['status'] !== CourseReader::LESSON) {
                throw ValidationException::withMessages(['from' => match ($reading['status']) {
                    CourseReader::NOT_FOUND => config('avatar.model.name').' n\'a pas trouvé ce passage. Indiquez plutôt les pages (par exemple de la page 3 à la page 5).',
                    CourseReader::UNREADABLE => 'Ces pages ne peuvent pas être lues avec certitude. Choisissez d\'autres pages, ou envoyez des photos nettes de cette partie.',
                    CourseReader::NOT_ENGLISH => 'Ces pages ne sont pas une leçon d\'anglais. Choisissez la partie de votre cours d\'anglais.',
                    default => 'La lecture est momentanément indisponible. Rien n\'a été compté : réessayez dans quelques minutes.',
                }]);
            }

            $part = $course->parts()->create([
                'page_from' => $reading['page_from'],
                'page_to' => $reading['page_to'],
                'request' => $description,
                'content' => array_diff_key($reading, array_flip(['status', 'title', 'text', 'page_from', 'page_to'])),
                'text' => $reading['text'],
                'provider' => $reading['provider'] ?? null,
            ]);
        }

        $part ??= $course->parts()->create(['page_from' => $from, 'page_to' => $to, 'request' => $description]);
        $part->touch();
        $course->update(['current_part_id' => $part->id]);

        return $this->respond($request, redirect()->to($back.'#travailler')
            ->with('status', 'Partie choisie : '.$part->label().'. '.config('avatar.model.name').' se concentrera sur elle.'));
    }

    /**
     * Envoi par le navigateur (fetch, Accept: application/json) : l'adresse où aller, messages déjà gardés en
     * session ; sinon la redirection habituelle (sans JavaScript).
     */
    private function respond(Request $request, RedirectResponse $redirect): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['redirect' => $redirect->getTargetUrl()]) : $redirect;
    }

    /**
     * La lecture peut durer jusqu'à deux délais de fournisseur : PHP ne doit pas couper avant (le navigateur, lui,
     * affiche un message clair s'il attend trop longtemps).
     */
    private static function allowLongReading(): void
    {
        $needed = 2 * (int) config('platform.courses.read_timeout_seconds') + 30;
        $current = (int) ini_get('max_execution_time');

        if ($current !== 0 && $current < $needed && function_exists('set_time_limit')) {
            @set_time_limit($needed);
        }
    }

    public function show(Request $request, CourseLesson $course): View|RedirectResponse
    {
        $this->ownLesson($request->user(), $course);

        if ($redirect = $this->requireAccess($request->user())) {
            return $redirect;
        }

        return view('courses.show', [
            'course' => $course->load(['pages', 'parts', 'currentPart']),
            'counter' => $this->counter($request->user()),
            'partLimits' => [
                'browser_timeout' => (int) config('platform.courses.browser_timeout_seconds'),
                'max_pages' => (int) config('platform.courses.part_max_pages'),
                'description_max' => (int) config('platform.courses.part_description_max'),
            ],
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
     * Temps du jour restant (partagé avec la séance orale) et durée de la prochaine séance « Mes cours » :
     * la durée maximale d'une séance, jamais plus que le temps restant.
     *
     * @return array{remaining: int, session: int, exhausted: bool}
     */
    private function counter(User $user): array
    {
        $available = (int) $user->remainingSecondsToday();

        return [
            'remaining' => (int) $user->remainingSecondsToday(false),
            'session' => min((int) config('platform.courses.max_session_minutes') * 60, $available),
            'exhausted' => $available < DailyTime::minStartSeconds(),
        ];
    }

    /**
     * Poids d'un PDF vérifié dans le navigateur : la limite de « Mes cours », ou celle du serveur si elle est plus
     * petite (l'élève lit alors tout de suite « Votre fichier dépasse … », au lieu d'un envoi refusé).
     */
    private static function pdfMaxBytes(): int
    {
        $limits = UploadLimits::report();
        $bytes = (int) config('platform.courses.pdf_max_kilobytes') * 1024;

        foreach ([$limits['file']['value'], $limits['post']['value'] - 1024 * 1024] as $server) {
            if ($server > 0) {
                $bytes = min($bytes, $server);
            }
        }

        return $bytes;
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
            'pdf_max_bytes' => self::pdfMaxBytes(),
            'part_pages' => (int) config('platform.courses.part_max_pages'),
            'browser_timeout' => (int) config('platform.courses.browser_timeout_seconds'),
        ];
    }
}
