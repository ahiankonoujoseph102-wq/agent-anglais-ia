<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Text\TextLog;
use App\Support\Text\TextProvider;
use App\Support\Text\TextProviders;
use App\Support\Text\TextRequest;
use App\Support\Text\TextResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * « Diagnostic des fournisseurs » du modèle texte (captures, « Mes cours », estimation, rédactions) : chaque
 * fournisseur (clé présente ou non, modèle, place dans l'ordre, mis de côté après une limite), un bouton
 * « Tester » qui envoie une petite image d'essai, et les derniers échecs. Jamais de clé affichée.
 */
class TextDiagnosticController extends Controller
{
    public function index(): View
    {
        return view('admin.text-diagnostic.index', [
            'providers' => TextProviders::all(),
            'order' => array_map(fn (TextProvider $provider) => $provider->name(), TextProviders::configured()),
            'failures' => TextLog::recent(),
        ]);
    }

    /**
     * « Tester » un fournisseur, seul (sans secours) : une petite image orange, une réponse JSON imposée.
     */
    public function test(string $provider): RedirectResponse
    {
        $provider = TextProviders::find($provider);
        abort_if($provider === null, 404);

        if (! $provider->hasKey()) {
            return back()->with('diagnostic', ['ok' => false, 'text' => $provider->label().' : échec : clé absente.']);
        }

        $result = TextProviders::generate(self::request(), $provider, fn (TextResult $result) => filled($result->json()['color'] ?? null));

        if ($result === null) {
            $failure = TextLog::recent()[0] ?? null;

            return back()->with('diagnostic', [
                'ok' => false,
                'text' => $provider->label().' : échec'.($failure !== null ? ' : '.$failure['reason'].($failure['status'] ? ' (code HTTP '.$failure['status'].')' : '').'. '.$failure['message'] : '.'),
            ]);
        }

        $seconds = round($result->durationMs / 1000, 1);

        return back()->with('diagnostic', [
            'ok' => true,
            'text' => $provider->label().' : réussi : le modèle « '.$result->model.' » a lu l\'image d\'essai en '
                .number_format($seconds, 1, ',', ' ').($seconds >= 2 ? ' secondes' : ' seconde')
                .' et a répondu au format attendu (couleur lue : « '.mb_substr((string) $result->json()['color'], 0, 40).' »).',
        ]);
    }

    public static function request(): TextRequest
    {
        return new TextRequest(
            kind: 'diagnostic',
            instructions: 'You describe test images. Answer with the main colour of the image, in English, in one or two words.',
            parts: [TextRequest::text('What is the main colour of this image?'), TextRequest::image(self::image(), 'image/png')],
            schema: ['type' => 'object', 'properties' => ['color' => ['type' => 'string']], 'required' => ['color']],
            schemaName: 'diagnostic',
            maxTokens: 50,
            timeout: 30,
            effort: 'low',
        );
    }

    /**
     * Image d'essai : un carré orange de 32 pixels, au format PNG, écrit sans bibliothèque.
     */
    public static function image(): string
    {
        $size = 32;
        $row = "\0".str_repeat("\xFF\x7A\x1A", $size);
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1A\n"
            .$chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 2, 0, 0, 0))
            .$chunk('IDAT', (string) gzcompress(str_repeat($row, $size)))
            .$chunk('IEND', '');
    }
}
