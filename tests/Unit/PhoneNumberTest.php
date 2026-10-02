<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function numbers(): array
    {
        return [
            'local' => ['90123456', '+22890123456'],
            'local avec espaces' => ['90 12 34 56', '+22890123456'],
            'local avec tirets et points' => ['90-12.34-56', '+22890123456'],
            'international' => ['+22890123456', '+22890123456'],
            'international avec espaces' => ['+228 90 12 34 56', '+22890123456'],
            'préfixe 00' => ['00228 90 12 34 56', '+22890123456'],
            'indicatif sans +' => ['22890123456', '+22890123456'],
            'autre pays (Bénin)' => ['+229 01 97 00 00 00', '+2290197000000'],
            'vide' => ['', null],
            'null' => [null, null],
            'trop court' => ['1234', null],
            'longueur locale inattendue' => ['901234567', null],
            'lettres' => ['abcdefgh', null],
            'trop long' => ['+1234567890123456', null],
            'indicatif commençant par 0' => ['+0123456789', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalizes_phone_numbers(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public function test_default_country_code_is_configurable(): void
    {
        config(['platform.phone.default_country_code' => '225', 'platform.phone.local_length' => 10]);

        $this->assertSame('+2250701020304', PhoneNumber::normalize('07 01 02 03 04'));
    }
}
