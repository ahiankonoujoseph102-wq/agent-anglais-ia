<?php

namespace App\Support\Ambassadors;

use App\Enums\ApplicationStatus;
use App\Models\AmbassadorApplication;
use App\Models\AmbassadorWaitlistEntry;
use App\Models\User;
use App\Support\Alerts\AdminAlerts;
use App\Support\Alerts\AlertBuilder;
use App\Support\Greeting;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\DB;

/**
 * Page « Devenir ambassadeur » : candidatures (compte obligatoire) et liste d'attente quand toutes les places
 * sont prises. Le nombre de places libres vient TOUJOURS de AmbassadorProgram::freePlaces() (places de
 * « Réglages » moins les ambassadeurs actifs) : jamais un nombre écrit à la main.
 */
final class AmbassadorApplications
{
    /** Ce que la page montre à ce visiteur : candidature possible, déjà envoyée, ambassadeur, liste d'attente… */
    public static function stateFor(?User $user): string
    {
        // Toujours relu : une candidature ou une place sur la liste a pu changer depuis le dernier chargement.
        $user?->load(['ambassador', 'ambassadorApplication', 'ambassadorWaitlist']);

        if ($user?->isAmbassador()) {
            return 'ambassador';
        }

        if ($user?->isAdmin()) {
            return 'admin';
        }

        if ($user?->ambassadorApplication?->isPending()) {
            return 'pending';
        }

        if (AmbassadorProgram::freePlaces() > 0) {
            return $user === null ? 'guest' : 'open';
        }

        if ($user === null) {
            return 'full_guest';
        }

        return $user->ambassadorWaitlist !== null ? 'waiting' : 'full';
    }

    /**
     * Enregistre une candidature (ou en renvoie une après un refus). Refusée quand toutes les places sont prises :
     * le formulaire est alors fermé et la personne peut s'inscrire sur la liste d'attente.
     *
     * @param  array{motivation: string, channels: string, minor: bool}  $data
     */
    public static function apply(User $user, array $data): AmbassadorApplication
    {
        $state = self::stateFor($user);

        if (in_array($state, ['full', 'waiting'], true)) {
            throw new AmbassadorRefused('Toutes les places d\'ambassadeurs sont prises.');
        }

        if ($state !== 'open') {
            throw new AmbassadorRefused('Vous ne pouvez pas envoyer de candidature pour le moment.');
        }

        $application = DB::transaction(function () use ($user, $data) {
            $application = AmbassadorApplication::query()->firstOrNew(['user_id' => $user->id]);
            $application->fill($data);
            $application->forceFill(['status' => ApplicationStatus::Pending, 'reviewed_by' => null, 'reviewed_at' => null])->save();

            return $application;
        });

        AdminAlerts::dispatch(AlertBuilder::ambassadorApplication($application->load('user')));

        return $application;
    }

    /** Acceptation par l'administrateur : le compte devient ambassadeur (refusé si toutes les places sont prises). */
    public static function accept(AmbassadorApplication $application, User $admin): array
    {
        if (! $application->isPending()) {
            throw new AmbassadorRefused('Cette candidature a déjà été traitée.');
        }

        // AmbassadorProgram::appoint marque la candidature « Acceptée » et retire la personne de la liste d'attente.
        return AmbassadorProgram::appoint($application->user, $admin);
    }

    public static function reject(AmbassadorApplication $application, User $admin): void
    {
        if (! $application->isPending()) {
            throw new AmbassadorRefused('Cette candidature a déjà été traitée.');
        }

        $application->forceFill(['status' => ApplicationStatus::Rejected, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
    }

    /** Appelé à la nomination d'un ambassadeur, d'où qu'elle vienne (candidature ou fiche d'un inscrit). */
    public static function noteAppointed(User $user, User $admin): void
    {
        AmbassadorApplication::query()->where('user_id', $user->id)->where('status', ApplicationStatus::Pending->value)
            ->update(['status' => ApplicationStatus::Accepted->value, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        AmbassadorWaitlistEntry::query()->where('user_id', $user->id)->delete();
    }

    /** « Me prévenir quand une place se libère » : seulement quand toutes les places sont prises. */
    public static function joinWaitlist(User $user): void
    {
        if (self::stateFor($user) !== 'full') {
            throw new AmbassadorRefused('Vous ne pouvez pas rejoindre la liste d\'attente pour le moment.');
        }

        AmbassadorWaitlistEntry::query()->firstOrCreate(['user_id' => $user->id]);
    }

    public static function leaveWaitlist(User $user): void
    {
        AmbassadorWaitlistEntry::query()->where('user_id', $user->id)->delete();
    }

    /** Message WhatsApp de l'administrateur à une personne de la liste d'attente (place libérée). */
    public static function waitlistMessage(User $user): string
    {
        return sprintf(
            'Bonjour %s, une place d\'ambassadeur %s vient de se libérer. Vous pouvez poser votre candidature ici : %s',
            Greeting::firstName($user->name),
            config('platform.name'),
            route('ambassador.join').'#candidature',
        );
    }

    public static function waitlistWhatsAppUrl(User $user): string
    {
        return WhatsApp::url($user->phone, self::waitlistMessage($user));
    }
}
