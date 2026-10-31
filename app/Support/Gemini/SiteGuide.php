<?php

namespace App\Support\Gemini;

use App\Models\Assessment;
use App\Support\DailyTime;
use App\Support\Duration;
use App\Support\PaymentCountry;
use App\Support\Plan;
use App\Support\Seo;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;

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

    /**
     * Fiche du site, entière (null) ou réduite aux parties utiles à une conversation : chaque partie marquée
     * « <!-- pour : assessment, lesson --> » n'est donnée qu'à ces conversations (assessment, lesson, course,
     * level_test, trial). Les consignes sont refacturées à chaque réponse du professeur : une partie inutile à
     * une conversation (le paiement pendant une séance d'abonné, par exemple) coûte à chaque tour.
     * Professeur : celui de la conversation (nom du marqueur :teacher), sinon le professeur par défaut.
     */
    public static function text(?string $kind = null, ?Teacher $teacher = null): string
    {
        $teacher ??= Teachers::default();

        $raw = (string) file_get_contents(self::path());

        if ($kind !== null) {
            $raw = self::forKind($raw, $kind);
        }

        // Les commentaires HTML sont des notes pour la personne qui modifie la fiche.
        $text = trim((string) preg_replace('/<!--.*?-->\s*/s', '', $raw));

        $text = strtr($text, [
            ':platform' => (string) config('platform.name'),
            ':teacher' => $teacher->name(),
            ':reminder_minutes' => Duration::format((int) config('platform.reminders.soon_minutes') * 60),
            ':assessment' => Duration::format((int) config('platform.assessment.max_minutes') * 60),
            ':disconnect_rule' => (string) config('platform.live.disconnect_rule'),
            // Après le verdict : mini-leçon découverte, questions et conclusion ; durée totale de l'évaluation.
            ':after_verdict' => Duration::format(Assessment::afterVerdictSeconds()),
            ':assessment_total' => Duration::format(Assessment::totalMinutes() * 60),
            // « Mes cours » et temps du jour : lus dans config/platform.php (courses, daily_time).
            ':course_minutes' => Duration::format((int) config('platform.courses.max_session_minutes') * 60),
            ':min_start' => Duration::format(DailyTime::minStartSeconds()),
            ':answer_edits' => (string) config('program.answer_edits_per_day'),
            ':course_photos' => (string) (int) config('platform.courses.max_photos'),
            ':part_pages' => (string) (int) config('platform.courses.part_max_pages'),
            ':course_pdf' => (int) (config('platform.courses.pdf_max_kilobytes') / 1024).' mégaoctets et '.(int) config('platform.courses.pdf_max_pages').' pages au plus',
            ':plans' => self::plans(),
            ':operators' => self::operators(),
            // Programme d'ambassadeurs : formule, durées et pourcentage lus dans la configuration.
            ':ambassador_page' => preg_replace('#^https?://#', '', Seo::url(route('ambassador.join', [], false))),
            ':ambassador_plan' => (string) Plan::find((string) config('platform.ambassadors.plan'))?->name,
            ':ambassador_days' => (string) (int) config('platform.ambassadors.month_days'),
            ':ambassador_months' => (string) (int) config('platform.ambassadors.max_months'),
            ':ambassador_percent' => (string) (int) config('platform.ambassadors.commission_percent'),
            ':ambassador_window' => (string) (int) config('platform.ambassadors.commission_days'),
            ':ambassador_link_days' => (string) (int) config('platform.ambassadors.link_days'),
        ]);

        // Accords propres au professeur (Teacher Rose : « Elle explique… ») ; Teacher Joe : aucun changement.
        return strtr($text, $teacher->siteGuideTexts());
    }

    /**
     * Garde les parties sans marque « pour » et celles qui citent cette conversation.
     */
    private static function forKind(string $raw, string $kind): string
    {
        $sections = preg_split('/^(?=## )/m', $raw) ?: [$raw];

        return implode('', array_filter($sections, function (string $section) use ($kind): bool {
            if (! preg_match('/<!--\s*pour\s*:\s*([^>]*?)\s*-->/u', $section, $match)) {
                return true;
            }

            return in_array($kind, array_map('trim', explode(',', $match[1])), true);
        }));
    }

    private static function plans(): string
    {
        return implode("\n", array_map(
            fn (Plan $plan) => "- {$plan->name} : {$plan->formattedPrice()} {$plan->periodLabel()}, "
                .Duration::format($plan->dailyMinutes * 60)." par jour, à partager entre la séance orale et « Mes cours », pendant {$plan->durationDays} jours.",
            array_values(Plan::all()),
        ));
    }

    private static function operators(): string
    {
        $countries = PaymentCountry::available();

        if ($countries === []) {
            return '- Aucun pour l\'instant : le paiement n\'est pas encore ouvert, l\'apprenant doit contacter l\'équipe sur WhatsApp (« Aide »).';
        }

        // Seulement ceux dont le numéro de réception est saisi dans les Réglages.
        return implode("\n", array_map(
            fn (PaymentCountry $country) => "- {$country->name} : "
                .implode(', ', array_map(fn ($operator) => $operator->name, $country->operators)).'.',
            array_values($countries),
        ));
    }
}
