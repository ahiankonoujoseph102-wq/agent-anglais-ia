<?php

namespace App\Support\Gemini;

use App\Enums\Level;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\Unit;
use App\Support\Duration;
use App\Support\Plan;

/**
 * Faits sur les formules donnés au professeur (bloc « advisor » de config/teacher.php) : noms, prix, durée,
 * temps par jour et prix par jour, TOUS lus dans config/platform.php. Rien n'est écrit à la main : changer un
 * prix dans la configuration change ce que dit le professeur.
 */
class PlanFacts
{
    /**
     * Marqueurs du bloc « advisor » pour une conversation.
     *
     * @return array<string, string>
     */
    public static function replacements(string $kind): array
    {
        $plans = array_values(Plan::all());
        $cheapest = collect($plans)->sortBy('price')->first();
        $leastDaily = collect($plans)->min('dailyMinutes');

        return [
            'plan_facts' => implode("\n", array_map(fn (Plan $plan) => '    - '.self::line($plan), $plans)),
            'cheapest' => $cheapest === null ? '' : 'The cheapest plan, '.$cheapest->name.', costs '.self::amount($cheapest->price)
                .' for '.$cheapest->durationDays.' days ('.self::perDay($cheapest).'): a good way to try.',
            'daily_least' => $leastDaily === null ? '' : Duration::format((int) $leastDaily * 60),
            'where_plans' => self::where($kind),
            'disconnect_rule' => (string) config('platform.live.disconnect_rule'),
            'assessment_free' => 'The oral assessment is free ('.Duration::format((int) config('platform.assessment.max_minutes') * 60)
                .', once per account), so they can try before paying.',
            'level_length' => self::levelLength(),
        ];
    }

    /**
     * Longueur d'un niveau du programme, tirée du programme officiel (unités) et de Unit::LESSONS : un fait vrai pour
     * répondre à « combien de temps pour bien parler ? », jamais une promesse.
     */
    public static function levelLength(): string
    {
        $units = count(Curriculum::units(Level::A1));
        $lessons = $units * Unit::LESSONS;
        $weeks = [intdiv($lessons, 5), (int) ceil($lessons / 5)];

        return "each level of the programme has {$units} units of ".Unit::LESSONS." lessons, so {$lessons} lessons; "
            .'one lesson per study day chosen by the learner; with 5 study days a week, a level takes about '
            .($weeks[0] === $weeks[1] ? $weeks[0] : $weeks[0].' to '.$weeks[1]).' weeks if each lesson is passed the first time, '
            .'longer when a lesson must be done again';
    }

    /** Une formule, en une ligne : nom, badge, prix, durée, temps par jour, prix par jour. */
    public static function line(Plan $plan): string
    {
        return $plan->name.($plan->badge ? ' (« '.$plan->badge.' »)' : '').' : '.self::amount($plan->price)
            .' pour '.$plan->durationDays.' jours, '.Duration::format($plan->dailyMinutes * 60).' par jour, '
            .self::perDay($plan).'.';
    }

    /**
     * Prix par jour, honnête : le montant exact s'il tombe juste (« 250 francs CFA par jour »), sinon « moins de »
     * l'arrondi à la cinquantaine supérieure (2 000 pour 7 jours : « moins de 300 francs CFA par jour »).
     */
    public static function perDay(Plan $plan): string
    {
        if ($plan->durationDays <= 0) {
            return '';
        }

        if ($plan->price % $plan->durationDays === 0) {
            return 'soit '.self::amount(intdiv($plan->price, $plan->durationDays)).' par jour';
        }

        return 'soit moins de '.self::amount((int) (ceil($plan->price / $plan->durationDays / 50) * 50)).' par jour';
    }

    /** Montant écrit pour le professeur, avec des espaces ordinaires (« 2 000 francs CFA »). */
    private static function amount(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' francs CFA';
    }

    /** Où se trouvent les formules, selon la conversation en cours. */
    private static function where(string $kind): string
    {
        return $kind === 'assessment'
            // Les formules ne s'affichent qu'à la FIN de la conversation (#122) : jamais « elles s'affichent » pendant.
            ? '« Les formules s\'afficheront sous votre résultat, à la fin de notre échange : vous choisirez avec le bouton orange. » (they are never shown during the conversation)'
            : '« Dans « Mon espace », touchez « Choisir ma formule ». »';
    }
}
