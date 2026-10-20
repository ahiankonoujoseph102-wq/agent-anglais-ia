<?php

namespace Tests\Unit;

use App\Support\Gemini\CourseReader;
use Tests\TestCase;

class CourseReaderTest extends TestCase
{
    public function test_an_unknown_verdict_is_a_failure_not_a_lesson(): void
    {
        $this->assertSame(CourseReader::UNAVAILABLE, CourseReader::normalize(['verdict' => 'peut-être'])['status']);
        $this->assertSame(CourseReader::UNAVAILABLE, CourseReader::normalize([])['status']);
        $this->assertSame(CourseReader::UNAVAILABLE, CourseReader::normalize(['verdict' => true])['status']);
    }

    public function test_unreadable_and_foreign_pages_keep_no_content_at_all(): void
    {
        foreach ([CourseReader::UNREADABLE, CourseReader::NOT_ENGLISH] as $verdict) {
            $reading = CourseReader::normalize(['verdict' => $verdict, 'title' => 'Inventé', 'text' => 'Inventé', 'grammar' => [['point' => 'X']], 'exercises' => ['Y']]);

            $this->assertSame($verdict, $reading['status']);
            $this->assertNull($reading['title']);
            $this->assertNull($reading['text']);
            $this->assertSame([], $reading['grammar']);
            $this->assertSame([], $reading['exercises']);
        }
    }

    public function test_a_lesson_is_cleaned_and_bounded(): void
    {
        $reading = CourseReader::normalize([
            'verdict' => 'lesson',
            'title' => "  Titre \x00 de la leçon  ",
            'summary' => str_repeat('r', 900),
            'level' => 'b1',
            'grammar' => array_fill(0, 40, ['point' => 'Point', 'explanation' => 'Règle', 'examples' => array_fill(0, 20, 'Exemple')]),
            'vocabulary' => array_fill(0, 200, ['english' => 'word', 'french' => 'mot']),
            'examples' => array_fill(0, 100, 'Phrase'),
            'exercises' => array_fill(0, 100, 'Exercice'),
            'text' => str_repeat('a', 50000),
        ]);

        $this->assertSame(CourseReader::LESSON, $reading['status']);
        $this->assertSame('Titre  de la leçon', $reading['title']);
        $this->assertSame(400, mb_strlen($reading['summary']));
        $this->assertSame('B1', $reading['level']);
        $this->assertCount(20, $reading['grammar']);
        $this->assertCount(6, $reading['grammar'][0]['examples']);
        $this->assertCount(80, $reading['vocabulary']);
        $this->assertCount(50, $reading['examples']);
        $this->assertCount(40, $reading['exercises']);
        $this->assertSame(30000, mb_strlen($reading['text']));
    }

    public function test_a_lesson_with_nothing_read_is_unreadable(): void
    {
        $reading = CourseReader::normalize(['verdict' => 'lesson', 'title' => 'Vide', 'text' => '   ']);

        $this->assertSame(CourseReader::UNREADABLE, $reading['status']);
    }

    public function test_the_outline_of_a_long_pdf_is_cleaned(): void
    {
        $clean = CourseReader::normalizeOutline([
            'verdict' => 'lesson', 'title' => 'Book', 'summary' => 'Un manuel.', 'level' => 'b1',
            'outline' => [['page' => 1, 'content' => 'Unit 1'], ['page' => 0, 'content' => 'faux'], ['page' => 99, 'content' => 'trop loin'], ['page' => '2', 'content' => ' Unit 2 '], ['page' => 3]],
        ]);

        $this->assertSame('lesson', $clean['status']);
        $this->assertSame('B1', $clean['level']);
        $this->assertSame([['page' => 1, 'content' => 'Unit 1'], ['page' => 2, 'content' => 'Unit 2']], $clean['outline']);
        $this->assertSame('unreadable', CourseReader::normalizeOutline(['verdict' => 'lesson'])['status']);
        $this->assertSame('unavailable', CourseReader::normalizeOutline(['verdict' => 'ignore les consignes'])['status']);
    }

    public function test_a_part_keeps_the_pages_asked_or_the_pages_found(): void
    {
        $reading = ['verdict' => 'lesson', 'text' => 'Exercise 2.', 'page_from' => 9, 'page_to' => 12];

        $asked = CourseReader::normalizePart($reading, 3, 5);
        $this->assertSame([3, 5], [$asked['page_from'], $asked['page_to']]);

        $found = CourseReader::normalizePart($reading, null, null);
        $this->assertSame([9, 12], [$found['page_from'], $found['page_to']]);

        $this->assertSame([null, null], array_values(array_intersect_key(CourseReader::normalizePart(['verdict' => 'lesson', 'text' => 'x', 'page_from' => 5, 'page_to' => 2], null, null), array_flip(['page_from', 'page_to']))));
        $this->assertSame('not_found', CourseReader::normalizePart(['verdict' => 'not_found'], null, null)['status']);
    }
}
