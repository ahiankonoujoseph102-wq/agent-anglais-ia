<?php

namespace App\Support\Live;

use App\Models\Assessment;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;
use Illuminate\Http\JsonResponse;

/**
 * Une séance n'a qu'UNE page à la fois. Chaque page de séance porte un identifiant tiré au hasard (en-tête
 * X-Live-Page, ou champ « page_id » de l'envoi de fermeture) ; la dernière page qui se connecte devient celle de la
 * séance. Une autre page encore ouverte (deuxième onglet, deuxième téléphone, ancienne page restée allumée) est
 * refusée à son prochain envoi (409, « superseded ») et s'arrête d'elle-même, sans clore la séance.
 *
 * Pourquoi : la même évaluation ouverte deux fois faisait parler deux Teacher Joe en même temps ; chacun entendait
 * l'autre par le micro et lui répondait (réponses de l'élève qui étaient en fait la voix du professeur).
 */
final class LivePage
{
    public const HEADER = 'X-Live-Page';

    public static function id(): ?string
    {
        $id = (string) (request()->header(self::HEADER) ?? request()->input('page_id', ''));

        return preg_match('/^[A-Za-z0-9-]{8,40}$/', $id) ? $id : null;
    }

    /**
     * La page qui se connecte devient celle de la séance.
     */
    public static function claim(Assessment|CourseSession|Lesson|LevelTest $conversation): void
    {
        if (($id = self::id()) !== null && $conversation->live_page !== $id) {
            $conversation->forceFill(['live_page' => $id])->save();
        }
    }

    /**
     * Refus pour une page qui n'est plus celle de la séance (une page sans identifiant, d'avant la mise à jour,
     * est acceptée).
     */
    public static function refusal(Assessment|CourseSession|Lesson|LevelTest|null $conversation): ?JsonResponse
    {
        $id = self::id();

        if ($conversation === null || $id === null || $conversation->live_page === null || $conversation->live_page === $id) {
            return null;
        }

        return response()->json(['message' => (string) config('platform.live.messages.superseded'), 'superseded' => true], 409);
    }
}
