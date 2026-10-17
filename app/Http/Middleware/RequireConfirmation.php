<?php

namespace App\Http\Middleware;

use App\Support\AccessGrant;
use App\Support\Confirmation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * Action grave (alias « confirm:action ») : sans confirmed=1, on n'exécute rien et on
 * affiche une page de confirmation simple. C'est le repli quand le JavaScript ne s'est
 * pas chargé ; avec JavaScript, la fenêtre intégrée ajoute confirmed=1 elle-même.
 */
class RequireConfirmation
{
    public function handle(Request $request, Closure $next, string $action): Response
    {
        if ($request->boolean('confirmed')) {
            // Envoi depuis la page de confirmation : le retour (back()) se fait vers la page
            // d'origine, pas vers l'adresse de l'action (qui n'accepte pas GET).
            $origin = (string) $request->input('_origin');

            if ($origin !== '' && str_starts_with($origin, url('/'))) {
                $request->headers->set('referer', $origin);
            }

            return $next($request);
        }

        // Champs du formulaire renvoyés tels quels (par exemple le motif d'un refus).
        $fields = [];

        foreach (Arr::dot($request->except(['_token', '_method', 'confirmed', '_origin'])) as $key => $value) {
            $parts = explode('.', (string) $key);
            $name = array_shift($parts).implode('', array_map(fn ($part) => "[{$part}]", $parts));
            $fields[$name] = is_scalar($value) ? (string) $value : '';
        }

        $previous = url()->previous();

        return response()->view('confirm.page', $this->texts($action, $request) + [
            'action' => $request->url(),
            'method' => $request->method(),
            'fields' => $fields,
            'back' => $previous !== $request->url() ? $previous : url('/'),
        ]);
    }

    /**
     * Textes de la confirmation ; « access.grant » rappelle la formule et la date de fin choisies.
     *
     * @return array{title: string, message: string, confirm: string}
     */
    private function texts(string $action, Request $request): array
    {
        if ($action !== 'access.grant') {
            return Confirmation::for($action);
        }

        return Confirmation::filled($action, AccessGrant::summary($request->input('plan'), $request->input('duration'), $request->input('custom_days')));
    }
}
