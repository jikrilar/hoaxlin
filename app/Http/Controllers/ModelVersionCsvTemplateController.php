<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloadable training-CSV template (T3.8).
 *
 * Three fictional rows using the two labels the validator accepts, so an admin
 * can see the exact header and shape without guessing. No real submission data.
 */
class ModelVersionCsvTemplateController extends Controller
{
    private const ROWS = [
        ['Contoh berita yang layak dipercaya: lembaga resmi merilis panduan layanan dan warga dapat memeriksa kanal resminya.', 'valid'],
        ['Contoh klaim palsu: pesan berantai menjanjikan hadiah uang dari lembaga yang tidak pernah mengumumkannya.', 'hoax'],
        ['Contoh berita lain yang perlu diverifikasi: pengumuman jadwal layanan dengan nomor kontak resmi yang dapat dicek.', 'valid'],
    ];

    public function __invoke(): StreamedResponse
    {
        abort_unless(auth()->user()?->is_admin === true, 403);

        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['text', 'label'], ',', '"', '', "\r\n");
            foreach (self::ROWS as $row) {
                fputcsv($stream, $row, ',', '"', '', "\r\n");
            }
            fclose($stream);
        }, 'templat-dataset-pelatihan.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
