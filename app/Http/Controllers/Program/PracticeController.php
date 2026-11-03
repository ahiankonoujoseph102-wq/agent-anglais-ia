<?php

namespace App\Http\Controllers\Program;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Curriculum\LessonBoards;
use App\Support\Practice\PracticeAudio;
use App\Support\Speech\LightAudio;
use App\Support\Speech\VoiceMessage;
use App\Support\Teachers\Teachers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * « Réviser à voix haute » : les phrases, mots et verbes du tableau d'une séance du jour, à écouter (voix du
 * professeur, ou plus lentement), à répéter, à enregistrer et à comparer. L'enregistrement de l'élève reste dans son
 * navigateur (rien n'est reçu ici). Aucun temps du jour consommé, aucun appel au modèle texte.
 */
class PracticeController extends Controller
{
    /** La dernière séance du jour commencée (fin d'une séance : « Réviser à voix haute ce que vous venez d'apprendre »). */
    public function latest(Request $request): RedirectResponse
    {
        abort_unless(PracticeAudio::enabled(), 404);
        $lesson = $request->user()->lessons()->whereNotNull('started_at')->whereNotNull('program_lesson_id')->latest('id')->first();

        return $lesson !== null ? redirect()->route('practice.show', $lesson) : redirect()->route('lessons.history');
    }

    public function show(Request $request, Lesson $lesson): View
    {
        [$board, $items, $message] = $this->load($request, $lesson);
        PracticeAudio::queue($message);
        $status = PracticeAudio::status($message);

        return view('program.practice', [
            'lesson' => $lesson,
            'board' => $board,
            'items' => $items,
            'ready' => $status['ready'],
            'teacher' => Teachers::forUser($request->user()),
        ]);
    }

    /** Éléments dont la voix est prête ; fait préparer le reste après la réponse. */
    public function status(Request $request, Lesson $lesson): JsonResponse
    {
        [, , $message] = $this->load($request, $lesson);
        PracticeAudio::queue($message);

        return response()->json(['ready' => PracticeAudio::status($message)['ready']]);
    }

    /** Voix d'un élément (déjà prête ; jamais de synthèse pendant l'écoute). */
    public function audio(Request $request, Lesson $lesson, int $index): Response
    {
        [, , $message] = $this->load($request, $lesson);
        abort_unless(isset($message->pieces[$index]), 404);
        $wav = PracticeAudio::file($message, $index);

        if ($wav === null) {
            return response('Voix pas encore prête.', 503, ['Retry-After' => 10]);
        }

        if ($request->boolean('leger')) {
            $wav = LightAudio::mulaw($wav) ?? $wav;
        }

        return response($wav, 200, [
            'Content-Type' => 'audio/wav',
            'Content-Length' => (string) strlen($wav),
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Le tableau de cette séance (seulement une séance du jour de l'élève connecté, avec sa leçon du programme).
     *
     * @return array{0: array, 1: list<array{kind: string, en: string, say: string, fr: ?string}>, 2: VoiceMessage}
     */
    private function load(Request $request, Lesson $lesson): array
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(PracticeAudio::enabled() && $lesson->user_id === $user->id && $lesson->started_at !== null, 404);
        $lesson->loadMissing('programLesson.program');
        $programLesson = $lesson->programLesson;
        abort_if($programLesson?->program === null, 404);

        $board = LessonBoards::for($programLesson, $programLesson->program);
        $items = PracticeAudio::items($board);
        abort_if($items === [], 404);

        return [$board, $items, PracticeAudio::message($items, Teachers::forUser($user))];
    }
}
