<?php

namespace Tests\Unit;

use App\Support\CsvCellSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvCellSanitizerTest extends TestCase
{
    #[DataProvider('dangerousValues')]
    public function test_it_neutralizes_formula_prefixes_and_disguising_characters(string $value): void
    {
        $this->assertSame("'{$value}", (new CsvCellSanitizer)->sanitize($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dangerousValues(): iterable
    {
        yield 'equals' => ['=1+1'];
        yield 'plus' => ['+SUM(A1:A2)'];
        yield 'minus' => ['-2+3'];
        yield 'at sign' => ['@SUM(1,1)'];
        yield 'tab' => ["\t=cmd|' /C calc'!A0"];
        yield 'carriage return' => ["\r=1+1"];
        yield 'line feed' => ["\n=1+1"];
        yield 'whitespace and control characters' => [" \t\r\n@SUM(1,1)"];
    }

    #[DataProvider('safeValues')]
    public function test_it_preserves_normal_text(string $value): void
    {
        $this->assertSame($value, (new CsvCellSanitizer)->sanitize($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeValues(): iterable
    {
        yield 'empty' => [''];
        yield 'normal' => ['Berita Indonesia biasa'];
        yield 'formula already made literal' => ["'=1+1"];
        yield 'symbol within text' => ['Nilai + hasil'];
        yield 'unicode' => ['Berita palsu — masyarakat perlu waspada'];
    }
}
