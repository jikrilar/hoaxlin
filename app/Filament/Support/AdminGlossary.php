<?php

namespace App\Filament\Support;

/**
 * Plain-language explanations for technical admin terms (Lampiran A "Khusus admin").
 * One source of truth for tooltips, hints and stat descriptions.
 */
final class AdminGlossary
{
    public const TERMS = [
        'threshold' => 'Batas skor keyakinan agar model berani memberi label valid atau hoaks; bukan persentase kebenaran informasi.',
        'f1_score' => 'Ukuran keseimbangan ketepatan dan kelengkapan model pada data evaluasi.',
        'baseline' => 'Versi model acuan v1.0.0 yang tidak ditimpa oleh pelatihan bertahap.',
        'baseline_comparison' => 'Selisih akurasi dan F1 versi ini dibanding model acuan v1.0.0 pada split validasi yang sama.',
        'hot_swap' => 'Mengganti model yang melayani pengguna tanpa memulai ulang layanan; berlaku hanya setelah layanan konfirmasi berhasil.',
        'incremental_fine_tuning' => 'Melatih lanjutan model dengan data tambahan tanpa menimpa model acuan v1.0.0.',
        'active_inference_status' => 'Kondisi kesiapan layanan model yang sedang melayani pemeriksaan pengguna.',
        'failed_jobs' => 'Jumlah tugas latar belakang yang berhenti karena kendala teknis; bukan jumlah pemeriksaan unik.',
        'pipeline_stage' => 'Langkah pemeriksaan yang sedang atau terakhir dijalankan (baca teks, terjemahan, analisis, rujukan, penjelasan).',
        'attempts' => 'Berapa kali tahap pemrosesan dicoba dijalankan sebelum berhasil atau dinyatakan gagal.',
        'error_code' => 'Kategori penyebab kegagalan teknis pada tahap pemrosesan.',
        'not_available' => 'Data belum tercatat atau layanan tidak tersedia; bukan nilai nol dan bukan perkiraan.',
    ];

    public const NOT_AVAILABLE = 'Belum tersedia';

    public static function explain(string $term): string
    {
        return self::TERMS[$term] ?? self::NOT_AVAILABLE;
    }
}
