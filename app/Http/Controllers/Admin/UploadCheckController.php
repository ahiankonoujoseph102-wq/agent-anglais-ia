<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\UploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « Diagnostic des fournisseurs », bloc « Limites d'envoi » : deux essais RÉELS sur le site en ligne, pour vérifier
 * que « Mes cours » passe (PDF de 20 mégaoctets, lecture jusqu'à une minute) : un envoi de la taille d'un PDF au plus
 * lourd, puis une page qui attend aussi longtemps qu'une lecture. Rien n'est gardé, aucun fournisseur n'est appelé.
 */
class UploadCheckController extends Controller
{
    /**
     * Reçoit le fichier d'essai et dit combien d'octets sont arrivés (le fichier est jeté aussitôt).
     */
    public function size(Request $request): JsonResponse
    {
        $file = $request->file('essai');

        if ($file === null || ! $file->isValid()) {
            return response()->json(['ok' => false, 'message' => 'Le fichier d\'essai n\'est pas arrivé entier : la taille d\'un fichier (upload_max_filesize) est trop petite.'], 422);
        }

        return response()->json(['ok' => true, 'bytes' => (int) $file->getSize(), 'label' => UploadLimits::label((int) $file->getSize())]);
    }

    /**
     * Attend aussi longtemps que la lecture la plus longue d'un PDF (platform.courses.check_wait_seconds).
     */
    public function wait(): JsonResponse
    {
        $seconds = (int) config('platform.courses.check_wait_seconds');

        if (UploadLimits::canExtendTime()) {
            @set_time_limit($seconds + 30);
        }

        $start = microtime(true);
        sleep($seconds);

        return response()->json(['ok' => true, 'seconds' => (int) round(microtime(true) - $start)]);
    }
}
