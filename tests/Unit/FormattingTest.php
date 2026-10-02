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
        $this->assertSame('0 min', Duration::format(0));
        $this->assertSame('0 min', Duration::format(-30));
        $this->assertSame('14 min', Duration::format(899));
        $this->assertSame('1 h 05 min', Duration::format(3900));
    }

    public function test_default_plans_match_the_business_requirements(): void
    {
        $plans = Plan::all();

        $this->assertSame(['essentiel', 'intensif', 'semaine'], array_keys($plans));

        $this->assertSame([15, 5000, 'par mois'], [$plans['essentiel']->dailyMinutes, $plans['essentiel']->price, $plans['essentiel']->periodLabel()]);
        $this->assertSame([30, 9000, 'par mois'], [$plans['intensif']->dailyMinutes, $plans['intensif']->price, $plans['intensif']->periodLabel()]);
        $this->assertSame([15, 1500, 'par semaine'], [$plans['semaine']->dailyMinutes, $plans['semaine']->price, $plans['semaine']->periodLabel()]);

        $this->assertNull(Plan::find('inconnue'));
    }
}
