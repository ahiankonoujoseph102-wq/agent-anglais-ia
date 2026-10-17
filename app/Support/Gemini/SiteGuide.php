<?php

namespace App\Support\Gemini;

use App\Support\Duration;
use App\Support\PaymentCountry;
use App\Support\Plan;

/**
 * Fiche de connaissance du site donnée au professeur (resources/teacher/site-guide.md).
 * Les formules, prix et opérateurs viennent de la configuration : rien n'est recopié à la main.
 */
class SiteGuide
{
    public static function path(): string
    {
        return resource_path('teacher/site-guide.md');
    }

    public static function text(): string
    {
        // Les commentaires HTML sont des notes pour la personne qui modifie la fiche.
        $text = trim((string) preg_replace('/<!--.*?-->\s*/s', '', (string) file_get_contents(self::path())));

        return strtr($text, [
            ':platform' => (string) config('platform.name'),
            ':teacher' => (string) config('avatar.model.name'),
            ':assessment' => Duration::format((int) config('platform.assessment.max_minutes') * 60),
            ':plans' => self::plans(),
            ':operators' => self::operators(),
        ]);
    }

    private static function plans(): string
    {
        return implode("\n", array_map(
            fn (Plan $plan) => "- {$plan->name} : {$plan->formattedPrice()} {$plan->periodLabel()}, "
                .Duration::format($plan->dailyMinutes * 60)." de séance par jour, pendant {$plan->durationDays} jours.",
            array_values(Plan::all()),
        ));
    }

    private static function operators(): string
    {
        return implode("\n", array_map(
            fn (PaymentCountry $country) => "- {$country->name} : "
                .implode(', ', array_map(fn ($operator) => $operator->name, $country->operators)).'.',
            array_values(PaymentCountry::all()),
        ));
    }
}
