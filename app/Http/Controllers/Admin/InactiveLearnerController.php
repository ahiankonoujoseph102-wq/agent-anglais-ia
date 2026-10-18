<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Gemini\TextClient;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Apprenants abonnés sans séance depuis plusieurs jours, à relancer sur WhatsApp.
 */
class InactiveLearnerController extends Controller
{
    public function index(): View
    {
        return view('admin.inactive.index', [
            'learners' => self::inactive(),
            'days' => (int) config('program.inactive_after_days'),
        ]);
    }

    /**
     * Ouvre WhatsApp vers l'apprenant avec un message d'encouragement déjà rédigé.
     */
    public function whatsapp(User $user, TextClient $text): RedirectResponse
    {
        $user->loadMax('lessons', 'started_at')->load('activeProgram');
        $days = self::inactiveDays($user);

        // Un message par apprenant et par jour : pas de nouvel appel au modèle à chaque clic.
        $message = Cache::remember("encouragement:{$user->id}:".today()->toDateString(), now()->addDay(),
            fn () => self::encouragement($user, $days, $text));

        return redirect()->away(WhatsApp::url($user->phone, $message));
    }

    /**
     * @return Collection<int, User>
     */
    public static function inactive(): Collection
    {
        $threshold = (int) config('program.inactive_after_days');

        return User::query()
            ->where('role', Role::Learner)
            ->whereHas('activeSubscription')
            ->with(['activeSubscription', 'activeProgram'])
            ->withMax('lessons', 'started_at')
            ->get()
            ->filter(fn (User $user) => self::inactiveDays($user) >= $threshold)
            ->sortByDesc(fn (User $user) => self::inactiveDays($user))
            ->values();
    }

    /**
     * Jours depuis la dernière séance (ou depuis le début de l'abonnement s'il n'y en a jamais eu).
     */
    public static function inactiveDays(User $user): int
    {
        $last = $user->lessons_max_started_at ?? $user->activeSubscription?->starts_at;

        return $last === null ? 0 : (int) Carbon::parse($last)->startOfDay()->diffInDays(today());
    }

    private static function encouragement(User $user, int $days, TextClient $text): string
    {
        $program = $user->activeProgram;
        $context = $program
            ? "Niveau {$program->level->value}, objectif : {$program->goal->label()}, métier : {$program->occupation}."
            : 'Programme pas encore construit.';

        return $text->write(
            'Tu écris un message WhatsApp court (3 phrases maximum), chaleureux et motivant, en français simple, '
            .'au vouvoiement, de la part de l\'équipe de '.config('platform.name').' (cours d\'anglais à l\'oral). '
            .'Pas de reproche, pas d\'emoji excessif (un seul au maximum), pas de lien.',
            "Encourage {$user->name} à reprendre ses séances d'anglais : aucune séance depuis {$days} jours. {$context}",
            200,
            'encouragement',
        ) ?? sprintf(
            'Bonjour %s, votre professeur d\'anglais %s vous attend ! Cela fait %d jours que nous ne nous sommes pas parlé : '
            .'une petite séance aujourd\'hui, même courte, et vous gardez votre élan. À tout de suite sur le site !',
            $user->name,
            config('platform.name'),
            $days,
        );
    }
}
