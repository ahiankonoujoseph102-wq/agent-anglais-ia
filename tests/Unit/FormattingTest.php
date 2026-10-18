<?php

namespace Tests\Unit;

use App\Support\Duration;
use App\Support\Money;
use App\Support\Plan;
use Tests\TestCase;

class FormattingTest extends TestCase
{
    public function test_money_is_formatted_the_french_way(): void
    {
        $this->assertSame("5\u{202F}000\u{00A0}FCFA", Money::format(5000));
        $this->assertSame("750\u{00A0}FCFA", Money::format(750));
    }

    public function test_durations_are_formatted(): void
    {
        $this->assertSame('0 minute', Duration::format(0));
        $this->assertSame('0 minute', Duration::format(-30));
        $this->assertSame('1 minute', Duration::format(60));
        $this->assertSame('14 minutes', Duration::format(899));
        $this->assertSame('1 heure 5 minutes', Duration::format(3900));
        $this->assertSame('2 heures', Duration::format(7200));
        $this->assertSame('45 secondes', Duration::formatPrecise(45));
        $this->assertSame('4 minutes 12 secondes', Duration::formatPrecise(252));
        $this->assertSame('3 minutes', Duration::formatPrecise(180));
    }

    public function test_default_plans_match_the_business_requirements(): void
    {
        $plans = Plan::all();

        $this->assertSame(['essentiel', 'intensif', 'semaine'], array_keys($plans));

        $this->assertSame([15, 7500, 'par mois'], [$plans['essentiel']->dailyMinutes, $plans['essentiel']->price, $plans['essentiel']->periodLabel()]);
        $this->assertSame([30, 14000, 'par mois'], [$plans['intensif']->dailyMinutes, $plans['intensif']->price, $plans['intensif']->periodLabel()]);
        $this->assertSame([15, 2000, 'par semaine'], [$plans['semaine']->dailyMinutes, $plans['semaine']->price, $plans['semaine']->periodLabel()]);

        // Prix définitifs, affichés à la française partout (formules, paiement, administration).
        $this->assertSame(["7\u{202F}500\u{00A0}FCFA", "14\u{202F}000\u{00A0}FCFA", "2\u{202F}000\u{00A0}FCFA"], [$plans['essentiel']->formattedPrice(), $plans['intensif']->formattedPrice(), $plans['semaine']->formattedPrice()]);
        $this->assertSame([30, 30, 7], [$plans['essentiel']->durationDays, $plans['intensif']->durationDays, $plans['semaine']->durationDays]);
        $this->assertTrue($plans['essentiel']->recommended);

        $this->assertNull(Plan::find('inconnue'));
    }
}
