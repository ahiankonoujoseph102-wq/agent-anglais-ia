<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAlert;
use App\Models\PushSubscription;
use App\Support\Alerts\AdminAlerts;
use App\Support\Alerts\AlertBuilder;
use App\Support\CourseReviews;
use App\Support\LearnerInvitations;
use App\Support\MailQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page « Alertes » : téléphones abonnés aux notifications, état de l'e-mail, boutons d'essai et dernières
 * alertes envoyées (canal et résultat). Jamais de clé affichée : seulement si elle est présente.
 */
class AlertController extends Controller
{
    public function index(Request $request, AdminAlerts $alerts): View
    {
        return view('admin.alerts.index', [
            'pushReady' => $alerts->pushConfigured(),
            'mailReady' => $alerts->mailConfigured(),
            'emails' => count((array) config('alerts.emails')),
            'devices' => PushSubscription::query()->with('user')->orderBy('created_at')->get(),
            'publicKey' => $alerts->pushConfigured() ? config('alerts.vapid.public_key') : null,
            'recent' => AdminAlert::query()->orderByDesc('id')->limit((int) config('alerts.shown'))->get(),
            // E-mails du jour : documents de révision et alertes, un seul compteur (limite gratuite de Brevo).
            'mailQuota' => ['used' => MailQuota::usedToday(), 'limit' => MailQuota::limit(), 'reviews' => (int) config('platform.courses.review.daily_emails'), 'configured' => MailQuota::configured()],
            'reviewMail' => CourseReviews::stats()['mail_today'],
            'invitations' => LearnerInvitations::stats(),
        ]);
    }

    /** Enregistre ce téléphone (JSON envoyé par public/js/admin-alerts.js après la permission). */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        $subscription = PushSubscription::query()->firstOrNew(['endpoint_hash' => PushSubscription::hashOf($data['endpoint'])]);
        $subscription->fill([
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
        ]);
        $device = self::deviceName((string) $request->userAgent());

        // Un nom connu n'est pas remplacé par un nom vague (rafraîchissement depuis un autre navigateur).
        if ($subscription->device === null || $device !== 'Appareil, navigateur') {
            $subscription->device = $device;
        }

        $subscription->user_id = $request->user()->id;
        $subscription->save();

        return response()->json(['ok' => true]);
    }

    /** Arrête les alertes sur ce téléphone. */
    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');

        if ($endpoint !== '') {
            PushSubscription::query()->where('endpoint_hash', PushSubscription::hashOf($endpoint))->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** « Envoyer une alerte d'essai » : notification sur tous les téléphones abonnés, résultat affiché. */
    public function testPush(AdminAlerts $alerts): RedirectResponse
    {
        if (! $alerts->pushConfigured()) {
            return $this->result(false, 'Les notifications ne sont pas réglées : les variables VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY et VAPID_SUBJECT manquent dans Laravel Cloud.');
        }

        $result = $alerts->send(AlertBuilder::test(), ['push'])['push'] ?? null;

        return $result !== null && $result['status'] === AdminAlert::SENT
            ? $this->result(true, 'Notification d\'essai envoyée. Elle doit apparaître sur votre téléphone dans quelques secondes.')
            : $this->result(false, 'Notification d\'essai non envoyée : '.($result['reason'] ?? 'raison inconnue').'.');
    }

    /** « Envoyer un e-mail d'essai ». */
    public function testMail(AdminAlerts $alerts): RedirectResponse
    {
        if (! $alerts->mailConfigured()) {
            return $this->result(false, 'L\'e-mail n\'est pas réglé : il faut ADMIN_ALERT_EMAIL, une adresse d\'expéditeur et les réglages SMTP (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD) dans Laravel Cloud.');
        }

        $result = $alerts->send(AlertBuilder::test(), ['mail'])['mail'] ?? null;

        return $result !== null && $result['status'] === AdminAlert::SENT
            ? $this->result(true, 'E-mail d\'essai envoyé. S\'il n\'arrive pas dans quelques minutes, regardez dans les courriers indésirables.')
            : $this->result(false, 'E-mail d\'essai non envoyé : '.($result['reason'] ?? 'raison inconnue').'.');
    }

    /** Supprime un téléphone de la liste. */
    public function destroy(PushSubscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return to_route('admin.alerts.index')->with('status', 'Ce téléphone ne recevra plus d\'alertes.');
    }

    private function result(bool $ok, string $text): RedirectResponse
    {
        return to_route('admin.alerts.index')->with('alert_result', ['ok' => $ok, 'text' => $text]);
    }

    /** Nom court de l'appareil, tiré de l'agent utilisateur (« Android, Chrome »). */
    private static function deviceName(string $agent): string
    {
        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Appareil',
        };
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'navigateur',
        };

        return $system.', '.$browser;
    }
}
