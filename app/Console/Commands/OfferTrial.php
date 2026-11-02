<?php

namespace App\Console\Commands;

use App\Enums\AssessmentStatus;
use App\Models\LearnerMail;
use App\Models\User;
use App\Support\Reminders\FollowUps;
use App\Support\Trial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lancement de l'offre (une fois) : chaque inscrit qui n'a jamais eu de formule payée ni d'essai peut REFAIRE son
 * évaluation gratuite (son évaluation est effacée, et le niveau du compte avec elle), puis profiter de la séance
 * d'essai ; un e-mail l'en prévient (campagne « offer », envoyée par lots par learners:follow-up). Sans --force :
 * simulation, rien n'est touché. Jamais un abonné payant, jamais l'équipe, jamais une évaluation en cours ; un
 * compte déjà prévenu n'est jamais remis à zéro une seconde fois (on peut donc relancer la commande sans risque).
 */
class OfferTrial extends Command
{
    protected $signature = 'learners:offer-trial {--force : Effacer les évaluations et préparer les e-mails}';

    protected $description = 'Lance l\'offre : évaluation gratuite à refaire et séance d\'essai, avec un e-mail aux inscrits sans formule payée';

    public function handle(): int
    {
        if (! Trial::enabled()) {
            $this->error('La séance d\'essai est désactivée (TRIAL_ENABLED). Rien n\'a été fait.');

            return self::FAILURE;
        }

        $targets = FollowUps::prospects()
            ->whereNull('follow_up_opt_out_at')
            ->whereDoesntHave('subscriptions', fn ($query) => $query->where('is_trial', true))
            ->whereDoesntHave('payments')
            ->whereDoesntHave('learnerMails', fn ($query) => $query->where('campaign', LearnerMail::OFFER))
            ->with('assessment')
            ->get()
            ->reject(fn (User $user) => $user->activeSubscription()->exists()
                || $user->assessment?->status === AssessmentStatus::InProgress);

        $withAssessment = $targets->filter(fn (User $user) => $user->assessment !== null)->count();
        $withEmail = $targets->filter(fn (User $user) => filled($user->email))->count();

        $this->line("Inscrits concernés (jamais de formule payée ni d'essai) : {$targets->count()}");
        $this->line("Évaluations à effacer pour qu'ils puissent la refaire : {$withAssessment}");
        $this->line("Avec une adresse e-mail (recevront l'e-mail, par lots) : {$withEmail}");
        $this->line('Sans adresse e-mail (à relancer sur WhatsApp depuis « Relances ») : '.($targets->count() - $withEmail));

        if (! $this->option('force')) {
            $this->warn('Simulation : rien n\'a été touché. Pour lancer : php artisan learners:offer-trial --force');

            return self::SUCCESS;
        }

        foreach ($targets as $user) {
            DB::transaction(function () use ($user) {
                // Comme « Réinitialiser l'évaluation » de l'administration : le niveau part avec elle.
                $user->assessment()->delete();
                $user->forceFill(['level' => null])->save();
                $user->learnerMails()->firstOrCreate(['campaign' => LearnerMail::OFFER], ['status' => LearnerMail::PENDING]);
            });
        }

        $this->info("C'est lancé : {$targets->count()} compte(s) peuvent refaire leur évaluation gratuite ; les e-mails partent par lots chaque heure, entre 6 heures et 20 heures.");

        return self::SUCCESS;
    }
}
