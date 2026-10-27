<?php

namespace App\Support\Admin;

use App\Enums\Role;
use App\Mail\AdminRecoveryMail;
use App\Models\User;
use App\Support\Alerts\Redact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Accès retrouvé par l'ADMINISTRATEUR sans console : un lien à usage unique, valable
 * config('admin.recovery.minutes') minutes, envoyé par e-mail à config('admin.recovery.email').
 *
 * Le lien n'apparaît QUE dans l'e-mail : jamais dans un journal, la sortie d'une commande ni la base
 * (seule son empreinte SHA-256 est gardée en cache). Le jeton est après « # » : un navigateur ne
 * l'envoie jamais au serveur dans l'adresse, il ne peut donc pas finir dans les journaux d'accès ;
 * la page le renvoie dans un formulaire (POST). Un nouvel envoi rend les anciens liens inutilisables.
 */
final class AdminRecovery
{
    private const PREFIX = 'admin-recovery:';

    /**
     * Envoie un lien par administrateur. Renvoie [réussite, message sans secret].
     *
     * @return array{0: bool, 1: string}
     */
    public static function send(): array
    {
        $address = (string) config('admin.recovery.email');

        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Aucune adresse de récupération valable (ADMIN_RECOVERY_EMAIL).'];
        }

        if (! self::mailReady()) {
            return [false, 'L\'envoi des e-mails n\'est pas réglé (MAIL_MAILER=smtp, MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS) : aucun lien envoyé.'];
        }

        $admins = User::query()->where('role', Role::Admin)->orderBy('id')->get();

        if ($admins->isEmpty()) {
            return [false, 'Aucun compte administrateur.'];
        }

        $minutes = (int) config('admin.recovery.minutes', 30);

        try {
            foreach ($admins as $admin) {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);

                Cache::put(self::PREFIX.$hash, $admin->id, now()->addMinutes($minutes));
                Cache::put(self::PREFIX.'current:'.$admin->id, $hash, now()->addMinutes($minutes));

                Mail::to($address)->send(new AdminRecoveryMail($admin, route('admin.recovery').'#'.$token, $minutes));
            }
        } catch (Throwable $e) {
            return [false, 'Envoi impossible : '.Redact::reason($e->getMessage())];
        }

        return [true, $admins->count() === 1
            ? 'Lien envoyé à '.self::mask($address).' (valable '.$minutes.' minutes, une seule fois).'
            : $admins->count().' liens envoyés à '.self::mask($address).' (un par administrateur, valables '.$minutes.' minutes, une seule fois).'];
    }

    /**
     * Utilise le jeton UNE fois : renvoie l'administrateur, ou null (inconnu, expiré, déjà utilisé, remplacé).
     */
    public static function consume(string $token): ?User
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $hash = hash('sha256', $token);
        $id = Cache::pull(self::PREFIX.$hash);

        if ($id === null || Cache::get(self::PREFIX.'current:'.$id) !== $hash) {
            return null;
        }

        Cache::forget(self::PREFIX.'current:'.$id);

        $admin = User::find($id);

        return $admin !== null && $admin->role === Role::Admin ? $admin : null;
    }

    /** Un vrai envoi (jamais « log » ni « array », qui garderaient le lien dans un fichier ou en mémoire). */
    public static function mailReady(): bool
    {
        $mailer = (array) config('mail.mailers.'.config('mail.default'));

        return filled(config('mail.from.address'))
            && ($mailer['transport'] ?? null) === 'smtp'
            && filled($mailer['host'] ?? null)
            && filled($mailer['username'] ?? null)
            && filled($mailer['password'] ?? null);
    }

    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('•', max(mb_strlen($local) - 2, 1)).'@'.$domain;
    }
}
