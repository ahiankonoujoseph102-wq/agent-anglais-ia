<?php

namespace App\Http\Controllers;

use App\Models\LearnerPushSubscription;
use App\Support\EmailAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Suivi des élèves : heure des séances (« Mon espace », carte « Heure de mes séances »), téléphones abonnés aux
 * rappels (notifications), et adresse e-mail ajoutée depuis l'écran de la séance sans le quitter.
 */
class ReminderController extends Controller
{
    /** Heure des séances du programme actif. */
    public function time(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['study_time' => ['required', 'date_format:H:i']],
            ['study_time.required' => 'Choisissez l\'heure de vos séances.', 'study_time.date_format' => 'Indiquez une heure valide, par exemple 18:30.'],
        );
        $program = $request->user()->activeProgram()->first();
        abort_if($program === null, 404);

        $program->study_time = $data['study_time'];
        $program->save();

        return redirect()->to(route('dashboard').'#rappels')->with('status', 'L\'heure de vos séances est enregistrée : '.str_replace(':', ' h ', $data['study_time']).'.');
    }

    /** Enregistre ce téléphone pour les rappels (JSON envoyé par public/js/learner-reminders.js). */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        $device = LearnerPushSubscription::query()->firstOrNew(['endpoint_hash' => LearnerPushSubscription::hashOf($data['endpoint'])]);
        $device->fill([
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
        ]);
        $device->user_id = $request->user()->id;
        $device->save();

        return response()->json(['ok' => true]);
    }

    /** Arrête les rappels sur ce téléphone. */
    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');

        if ($endpoint !== '') {
            LearnerPushSubscription::query()
                ->where('user_id', $request->user()->id)
                ->where('endpoint_hash', LearnerPushSubscription::hashOf($endpoint))
                ->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** Adresse e-mail ajoutée depuis l'écran de la séance (JSON), ou sans JavaScript (retour sur la page). */
    public function email(Request $request): JsonResponse|RedirectResponse
    {
        $request->merge(['email' => EmailAddress::normalize($request->input('email'))]);
        $rules = EmailAddress::rules($request->user()->id);
        $rules[0] = 'required';
        $data = $request->validate(['email' => $rules], EmailAddress::messages() + ['email.required' => 'Écrivez votre adresse e-mail.']);

        $user = $request->user();
        $user->email = $data['email'];
        $user->save();

        return $request->expectsJson()
            ? response()->json(['saved' => true])
            : back()->with('status', 'Votre adresse e-mail est enregistrée.');
    }
}
