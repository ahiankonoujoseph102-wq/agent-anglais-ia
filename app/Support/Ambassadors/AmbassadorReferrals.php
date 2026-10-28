<?php

namespace App\Support\Ambassadors;

use App\Models\Ambassador;
use App\Models\AmbassadorReferral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Suivi des inscrits des ambassadeurs, sans aucun outil extérieur :
 * - le lien personnel (talkami.net/a/CODE, aussi derrière le code QR) pose un cookie du site pendant
 *   platform.ambassadors.link_days jours ; le PREMIER lien l'emporte (un autre lien ne le remplace pas tant qu'il
 *   vaut pour un ambassadeur actif) ;
 * - à l'inscription, le code TAPÉ dans « Code ambassadeur » passe avant tout, sinon le lien gardé ;
 * - le rattachement est fixé à l'inscription ; seul l'administrateur peut le corriger ensuite ;
 * - signaux de triche (jamais un blocage) : même appareil que l'ambassadeur (cookie posé à sa connexion), même réseau
 *   (empreinte de l'adresse IP, jamais l'adresse), même réseau qu'un autre inscrit du même ambassadeur.
 */
final class AmbassadorReferrals
{
    /** Lien utilisé (code et date), chiffré comme tous les cookies du site. */
    public const LINK_COOKIE = 'talkami_ambassadeur';

    /** Appareil d'un ambassadeur (son numéro dans le programme), posé quand il se connecte. */
    public const DEVICE_COOKIE = 'talkami_appareil';

    /** Ambassadeur actif d'après un code (majuscules ou non, espaces ou tirets ignorés). */
    public static function findActive(?string $code): ?Ambassador
    {
        $code = AmbassadorCode::normalize($code);

        return $code === '' ? null : Ambassador::query()->active()->where('code', $code)->first();
    }

    /**
     * Lien personnel ouvert : garde le code, sauf si un lien encore valable (ambassadeur actif, moins de
     * link_days jours) est déjà gardé. Un code inconnu ou retiré ne laisse rien.
     */
    public static function rememberLink(Request $request, string $code): void
    {
        $ambassador = self::findActive($code);

        if ($ambassador === null || self::linkedAmbassador($request) !== null) {
            return;
        }

        Cookie::queue(self::LINK_COOKIE, json_encode(['code' => $ambassador->code, 'at' => now()->timestamp]), self::linkDays() * 24 * 60);
    }

    /** Ambassadeur du lien gardé, s'il est encore valable. */
    public static function linkedAmbassador(Request $request): ?Ambassador
    {
        $data = json_decode((string) $request->cookie(self::LINK_COOKIE), true);

        if (! is_array($data) || ! isset($data['code'], $data['at']) || (int) $data['at'] < now()->subDays(self::linkDays())->timestamp) {
            return null;
        }

        return self::findActive((string) $data['code']);
    }

    /** Code proposé dans le formulaire d'inscription : celui du lien gardé (l'inscrit le voit et peut le changer). */
    public static function suggestedCode(Request $request): ?string
    {
        return self::linkedAmbassador($request)?->code;
    }

    /**
     * Rattache un compte qui vient de s'inscrire : le code tapé, sinon le lien gardé ; rien sinon.
     */
    public static function record(User $user, Request $request, ?string $typedCode): ?AmbassadorReferral
    {
        $linked = self::linkedAmbassador($request);
        $typed = self::findActive($typedCode);
        $ambassador = $typed ?? $linked;

        // Un ambassadeur ne se rattache jamais lui-même (filet de sécurité : il a déjà un compte).
        if ($ambassador === null || $ambassador->user_id === $user->id) {
            return null;
        }

        $ipHash = self::ipHash($request);
        $flags = [];

        if ((int) $request->cookie(self::DEVICE_COOKIE) === $ambassador->id) {
            $flags[] = 'same_device';
        }

        if ($ipHash !== null && $ipHash === $ambassador->last_ip_hash) {
            $flags[] = 'same_network';
        }

        if ($ipHash !== null && $ambassador->referrals()->where('ip_hash', $ipHash)
            ->where('created_at', '>=', now()->subDays((int) config('platform.ambassadors.shared_network_days')))->exists()) {
            $flags[] = 'shared_network';
        }

        Cookie::queue(Cookie::forget(self::LINK_COOKIE));

        return AmbassadorReferral::query()->create([
            'ambassador_id' => $ambassador->id,
            'user_id' => $user->id,
            // Le code proposé d'après le lien et laissé tel quel compte comme le lien.
            'source' => $typed !== null && ! $typed->is($linked) ? 'code' : 'link',
            'flags' => $flags === [] ? null : $flags,
            'ip_hash' => $ipHash,
        ]);
    }

    /**
     * Ambassadeur connecté (connexion, espace ambassadeur) : repère de son appareil et empreinte de son réseau,
     * pour signaler une inscription faite chez lui.
     */
    public static function noteAmbassador(User $user, Request $request): void
    {
        $ambassador = $user->ambassador;

        if ($ambassador === null || ! $ambassador->isActive()) {
            return;
        }

        Cookie::queue(self::DEVICE_COOKIE, (string) $ambassador->id, 60 * 24 * 365);

        $ipHash = self::ipHash($request);

        if ($ipHash !== null && $ipHash !== $ambassador->last_ip_hash) {
            $ambassador->forceFill(['last_ip_hash' => $ipHash])->save();
        }
    }

    /**
     * Correction par l'administrateur : rattache le compte à un ambassadeur actif, ou retire le rattachement.
     */
    public static function correct(User $user, ?Ambassador $ambassador): void
    {
        if ($ambassador === null) {
            $user->referral()->delete();

            return;
        }

        AmbassadorReferral::query()->updateOrCreate(['user_id' => $user->id], [
            'ambassador_id' => $ambassador->id,
            'source' => 'admin',
        ]);
    }

    /** Empreinte de l'adresse IP (jamais l'adresse elle-même), propre à ce site. */
    public static function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip === null ? null : hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private static function linkDays(): int
    {
        return (int) config('platform.ambassadors.link_days');
    }
}
