<?php

namespace Tests\Unit;

use App\Support\PdfPages;
use PHPUnit\Framework\TestCase;

class PdfPagesTest extends TestCase
{
    private function plain(int $pages): string
    {
        $kids = implode(' ', array_map(fn (int $i) => (3 + $i).' 0 R', range(0, $pages - 1)));
        $objects = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count {$pages} >>\nendobj\n";

        foreach (range(0, $pages - 1) as $i) {
            $objects .= (3 + $i)." 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> >>\nendobj\n";
        }

        return "%PDF-1.4\n{$objects}%%EOF";
    }

    public function test_pages_of_a_plain_pdf(): void
    {
        $this->assertSame(1, PdfPages::count($this->plain(1)));
        $this->assertSame(10, PdfPages::count($this->plain(10)));
        $this->assertSame(11, PdfPages::count($this->plain(11)));
    }

    public function test_pages_of_a_pdf_whose_page_tree_is_in_a_compressed_stream(): void
    {
        $inner = "<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\n<< /Type /Page /Parent 2 0 R >>\n<< /Type /Page /Parent 2 0 R >>\n<< /Type /Page /Parent 2 0 R >>\n";
        $stream = gzcompress($inner);
        $pdf = "%PDF-1.5\n7 0 obj\n<< /Type /ObjStm /N 4 /First 0 /Filter /FlateDecode >>\nstream\n{$stream}\nendstream\nendobj\n%%EOF";

        $this->assertSame(3, PdfPages::count($pdf));
    }

    public function test_without_a_root_count_the_page_objects_are_counted(): void
    {
        $pdf = "%PDF-1.4\n3 0 obj\n<< /Type /Page >>\nendobj\n4 0 obj\n<< /Type /Page >>\nendobj\n%%EOF";

        $this->assertSame(2, PdfPages::count($pdf));
    }

    public function test_a_nested_subtree_does_not_hide_the_root(): void
    {
        $pdf = "%PDF-1.4\n2 0 obj\n<< /Type /Pages /Kids [9 0 R 10 0 R] /Count 7 >>\nendobj\n9 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 >>\nendobj\n%%EOF";

        $this->assertSame(7, PdfPages::count($pdf));
    }

    public function test_not_a_pdf_or_no_pages_is_unknown(): void
    {
        $this->assertNull(PdfPages::count('ceci n\'est pas un PDF'));
        $this->assertNull(PdfPages::count(''));
        $this->assertNull(PdfPages::count("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"));
    }

    public function test_a_compression_bomb_does_not_exhaust_memory(): void
    {
        // 40 mégaoctets de zéros une fois décompressés : la lecture s'arrête à sa borne.
        $stream = gzcompress(str_repeat('0', 40 * 1024 * 1024), 9);
        $pdf = "%PDF-1.5\n1 0 obj\n<< /Filter /FlateDecode >>\nstream\n{$stream}\nendstream\nendobj\n%%EOF";

        $before = memory_get_peak_usage(true);
        $this->assertNull(PdfPages::count($pdf));
        $this->assertLessThan(120 * 1024 * 1024, memory_get_peak_usage(true) - $before + 1);
    }
}
