<?php

namespace Tests\Unit;

use App\Services\Bert\BertCsvValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BertCsvValidatorTest extends TestCase
{
    private BertCsvValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BertCsvValidator;
    }

    public function test_accepts_valid_utf8_csv_with_bom_whitespace_headers_and_label_aliases(): void
    {
        $csv = "\xEF\xBB\xBF"
            ." Text , LABEL , source \n"
            ."\"Berita resmi Kementerian Kesehatan\",valid,kemkes\n"
            ."\"Informasi resmi BI\",0,bi\n"
            ."\"Fakta lapangan\",true,antara\n"
            ."\"Benar adanya\",benar,kominfo\n"
            ."\"Sesuai data\",fakta,bps\n"
            ."\"Fakta terverifikasi\",fact,reuters\n"
            ."\"Pengumuman jadwal\",info,resmi\n"
            ."\n"
            ."\"Hoaks hadiah gratis\",hoax,medsos\n"
            ."\"Kabar bohong berantai\",hoaks,wa\n"
            ."\"Hoaks bansos\",hoaks:,fb\n"
            ."\"Hoax berhadiah\",hoax:,ig\n"
            ."\"Palsu total\",1,x\n"
            ."\"Salah besar\",false,yt\n"
            ."\"Klaim keliru\",keliru,blog\n"
            ."\"Disinformasi vaksin\",disinformasi,anon\n"
            ."\"Misinformasi gempa\",misinformasi,anon\n"
            ."\"Penipuan tautan\",penipuan,sms\n"
            ."\"Pernyataan salah\",salah,chat\n";

        $stats = $this->validateString($csv);

        $this->assertSame([
            'total_rows' => 18,
            'valid_rows' => 7,
            'hoax_rows' => 11,
        ], $stats);
    }

    public function test_validate_path_validates_real_file_and_rejects_missing_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bert_csv_');
        $this->assertIsString($path);

        try {
            file_put_contents($path, "text,label\nKlaim resmi pemerintah,valid\nKabar bohong berhadiah,hoax\n");
            $stats = $this->validator->validatePath($path);

            $this->assertSame([
                'total_rows' => 2,
                'valid_rows' => 1,
                'hoax_rows' => 1,
            ], $stats);
        } finally {
            @unlink($path);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->validator->validatePath($path);
    }

    public function test_rejects_empty_file_and_oversized_file(): void
    {
        try {
            $this->validateString('');
            $this->fail('Expected empty CSV to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('kosong', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('melebihi batas maksimal');
        $this->validateString("text,label\nContoh berita valid,valid\n", 20);
    }

    #[DataProvider('invalidCsvCases')]
    public function test_rejects_invalid_csv_contracts(string $content, string $expectedMessageFragment): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessageFragment);

        $this->validateString($content);
    }

    public static function invalidCsvCases(): array
    {
        return [
            'non-utf8 header' => ["text,label\xFF\nHalo,valid\n", 'UTF-8'],
            'non-utf8 body' => ["text,label\nHalo \xFF dunia,valid\n", 'UTF-8'],
            'missing text header' => ["content,label\nHalo dunia,valid\n", 'wajib memiliki kolom `text` dan `label`'],
            'missing label header' => ["text,category\nHalo dunia,valid\n", 'wajib memiliki kolom `text` dan `label`'],
            'header only' => ["text,label\n\n", 'tidak memiliki baris data pelatihan yang valid'],
            'empty text cell' => ["text,label\n   ,valid\n", 'kolom `text` tidak boleh kosong'],
            'empty label cell' => ["text,label\nBerita tanpa label,\n", 'kolom `label` tidak boleh kosong'],
            'meragukan label' => ["text,label\nBerita ambigu,meragukan\n", 'meragukan'],
            'uncertain label' => ["text,label\nUncertain claim,uncertain\n", 'meragukan'],
            'unknown label' => ["text,label\nBerita aneh,satire\n", 'tidak dikenali'],
        ];
    }

    /**
     * @return array{total_rows: int, valid_rows: int, hoax_rows: int}
     */
    private function validateString(string $content, ?int $maxBytes = null): array
    {
        $handle = fopen('php://temp', 'w+b');
        $this->assertIsResource($handle);

        try {
            fwrite($handle, $content);
            rewind($handle);

            return $this->validator->validateStream($handle, strlen($content), $maxBytes);
        } finally {
            fclose($handle);
        }
    }
}
