<?php

namespace Tests\Unit;

use App\Support\Videos\VoiceSplitter;
use PHPUnit\Framework\TestCase;

/**
 * Voix d'une vidéo en peu de demandes : regroupement des parties et recoupage au bon silence.
 */
class VoiceSplitterTest extends TestCase
{
    private const RATE = 24000;

    /** « Voix » : un son de cette durée ; silence : des zéros. */
    private function tone(float $seconds): string
    {
        $data = '';

        for ($i = 0; $i < (int) (self::RATE * $seconds); $i++) {
            $data .= pack('v', (int) (8000 * sin($i / 7)) & 0xFFFF);
        }

        return $data;
    }

    private function silence(float $seconds): string
    {
        return str_repeat("\0\0", (int) (self::RATE * $seconds));
    }

    public function test_parts_are_grouped_under_the_character_limit_in_order(): void
    {
        $texts = [str_repeat('a', 600), str_repeat('b', 600), str_repeat('c', 600), str_repeat('d', 100)];

        $this->assertSame([[0, 1], [2, 3]], VoiceSplitter::groups($texts, 1500));
        $this->assertSame([[0], [1], [2], [3]], VoiceSplitter::groups($texts, 500));
        $this->assertSame("Un.\n\nDeux.", VoiceSplitter::join([' Un. ', 'Deux.']));
    }

    public function test_the_audio_is_cut_in_the_silences_between_the_parts(): void
    {
        // Trois parties : 3 s de voix (avec un petit silence de 0,2 s au milieu, entre deux phrases), 0,6 s de
        // silence, 2 s de voix, 0,6 s de silence, 4 s de voix. Textes de longueur à peu près proportionnelle.
        $data = $this->tone(1.4).$this->silence(0.2).$this->tone(1.4).$this->silence(0.6)
            .$this->tone(2).$this->silence(0.6).$this->tone(4);
        $texts = [str_repeat('a', 33), str_repeat('b', 19), str_repeat('c', 36)];

        $pieces = VoiceSplitter::split(['rate' => self::RATE, 'channels' => 1, 'data' => $data], $texts);
        $seconds = array_map(fn (string $piece) => strlen($piece) / 2 / self::RATE, $pieces);

        $this->assertCount(3, $pieces);
        $this->assertSame(strlen($data), array_sum(array_map('strlen', $pieces)));
        // Coupes dans les longs silences (de 3 à 3,6 s, puis de 5,6 à 6,2 s), jamais dans le petit silence (1,4 s)
        // entre deux phrases d'une même partie.
        $this->assertGreaterThanOrEqual(3.0, $seconds[0]);
        $this->assertLessThanOrEqual(3.6, $seconds[0]);
        $this->assertGreaterThanOrEqual(5.6, $seconds[0] + $seconds[1]);
        $this->assertLessThanOrEqual(6.2, $seconds[0] + $seconds[1]);
    }

    public function test_a_single_part_is_kept_whole(): void
    {
        $data = $this->tone(1);

        $this->assertSame([$data], VoiceSplitter::split(['rate' => self::RATE, 'channels' => 1, 'data' => $data], ['Bonjour.']));
    }
}
