<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PdfResultPresentationTest extends TestCase
{
    public function test_pdf_video_category_does_not_misrepresent_a_normalized_link_as_an_upload(): void
    {
        foreach ([['media_path' => 'private/video.mp4'], ['source_url' => 'https://example.test/video.mp4']] as $source) {
            $data = $this->data(['input_type' => 'video', 'raw_input' => null, ...$source]);
            $html = view('hasil-pdf', $data)->render();

            $this->assertStringContainsString('Jenis input: Video</p>', $html);
            $this->assertStringNotContainsString('Video unggahan', $html);
            $this->assertStringNotContainsString('private/video.mp4', $html);
            $pdf = Pdf::loadView('hasil-pdf', $data)->setPaper('a4', 'portrait');
            $this->assertStringStartsWith('%PDF-', $pdf->output());
            $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        }
    }

    #[DataProvider('labels')]
    public function test_pdf_labels_and_score_match_the_web_and_render_on_a4(string $label, string $expected): void
    {
        $data = $this->data(result: ['label' => $label]);
        $html = view('hasil-pdf', $data)->render();

        $this->assertStringContainsString($expected, $html);
        $this->assertSame($label === 'meragukan', str_contains($html, 'Ini bukan bukti bahwa informasi benar atau palsu.'));
        $this->assertStringContainsString('Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.', $html);
        $this->assertStringContainsString('<th>Skor Keyakinan Model</th><td>91%</td>', $html);
        $this->assertStringContainsString('bukan persentase kebenaran berita.', $html);
        $this->assertStringContainsString('<td>v1.0.0</td>', $html);
        $this->assertStringNotContainsString('vv1.0.0', $html);
        $this->assertStringContainsString('Penjelasan final: “Periksa sumbernya.”', $html);

        $pdf = Pdf::loadView('hasil-pdf', $data)->setPaper('a4', 'portrait');
        $bytes = $pdf->output();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('%%EOF', $bytes);
        $this->assertEqualsWithDelta(595.28, $canvas->get_width(), 0.1);
        $this->assertEqualsWithDelta(841.89, $canvas->get_height(), 0.1);
        $this->assertSame(1, $canvas->get_page_count());
    }

    public static function labels(): array
    {
        return [
            'valid' => ['valid', 'Prediksi model: Valid'],
            'hoax' => ['hoax', 'Prediksi model: Hoaks'],
            'abstention' => ['meragukan', 'Informasi belum terverifikasi oleh sumber terpercaya'],
        ];
    }

    #[DataProvider('scores')]
    public function test_missing_and_zero_scores_are_distinct(mixed $score, string $expected): void
    {
        $html = view('hasil-pdf', $this->data(result: ['label' => null, 'confidence_score' => $score, 'model_version' => null]))->render();

        $this->assertStringContainsString('Label prediksi belum tersedia', $html);
        $this->assertStringContainsString('<th>Skor Keyakinan Model</th><td>'.$expected.'</td>', $html);
        $this->assertStringContainsString('<th>Versi classifier</th><td>Tidak tersedia</td>', $html);
        $this->assertStringNotContainsString('Prediksi model: Valid', $html);
    }

    public static function scores(): array
    {
        return ['missing' => [null, 'Tidak tersedia'], 'zero' => [0, '0%'], 'out of range' => [1.5, 'Tidak tersedia']];
    }

    #[DataProvider('explanationStates')]
    public function test_explanation_state_controls_whether_stored_narrative_is_shown(?string $state, ?string $text, string $expected): void
    {
        $html = view('hasil-pdf', $this->data(result: ['explanation_status' => $state, 'explanation' => $text]))->render();

        $this->assertStringContainsString($expected, $html);
        $this->assertStringContainsString('Prediksi model: Valid', $html);
        $this->assertStringNotContainsString('NARASI_LAMA', $html);
    }

    public static function explanationStates(): array
    {
        return [
            'pending' => ['pending', 'NARASI_LAMA', 'Penjelasan AI belum tersedia untuk hasil ini.'],
            'unavailable' => ['unavailable', 'NARASI_LAMA', 'Penjelasan AI tidak tersedia. Prediksi classifier tetap ditampilkan.'],
            'unknown' => [null, 'NARASI_LAMA', 'Teks penjelasan tidak tersedia untuk hasil ini.'],
            'ready empty' => ['ready', ' ', 'Teks penjelasan tidak tersedia untuk hasil ini.'],
        ];
    }

    public function test_input_excerpts_are_explicit_and_content_is_escaped_without_storage_paths_or_fabricated_evidence(): void
    {
        $html = view('hasil-pdf', $this->data([
            'input_type' => 'image',
            'raw_input' => '<img src="https://example.test/tracker"> & “klaim”',
            'media_path' => 'submissions/private-sensitive.mp4',
            'extracted_text' => str_repeat('é', 1600).'EKSTRAK_TERPOTONG',
            'translated_text' => str_repeat('ü', 1600).'TERJEMAHAN_TERPOTONG',
            'translation_provider' => null,
            'translation_model' => null,
        ], ['explanation' => '<script>alert("x")</script>']))->render();

        $this->assertStringContainsString('&lt;img src=', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('private-sensitive', $html);
        $this->assertStringNotContainsString('EKSTRAK_TERPOTONG', $html);
        $this->assertStringNotContainsString('TERJEMAHAN_TERPOTONG', $html);
        $this->assertSame(2, substr_count($html, 'Cuplikan 1600 karakter pertama; teks tidak ditampilkan seluruhnya.'));
        $this->assertStringContainsString(str_repeat('é', 1600), $html);
        $this->assertStringContainsString('Berkas media tidak disertakan dalam PDF ini.', $html);
        $this->assertStringContainsString('<th>Provider Terjemahan</th><td>Tidak tersedia</td>', $html);
        $this->assertStringNotContainsString('Tidak diterjemahkan', $html);
        $this->assertStringNotContainsString('CekFakta', $html);
        $this->assertStringNotContainsString('Mafindo', $html);
    }

    public function test_raw_input_excerpt_and_timezone_are_explicit(): void
    {
        config(['app.timezone' => 'Asia/Makassar']);
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Makassar');
        try {
            $data = $this->data(['raw_input' => str_repeat('a', 1600).'INPUT_TERPOTONG']);
            $data['submission']->created_at = '2026-10-15 09:00:00';
            $data['submission']->processing_completed_at = '2026-10-15 09:05:00';
            $this->travelTo(Carbon::parse('2026-10-15 01:06:00', 'UTC'));
            $html = view('hasil-pdf', $data)->render();

            $this->assertStringContainsString('Cuplikan 1600 karakter pertama; input asli tidak ditampilkan seluruhnya.', $html);
            $this->assertStringNotContainsString('INPUT_TERPOTONG', $html);
            $this->assertStringContainsString('15 Oct 2026, 09:00 WITA', $html);
            $this->assertStringContainsString('15 Oct 2026, 09:05 WITA', $html);
            $this->assertStringContainsString('15 Oct 2026, 09:06 WITA', $html);
            $this->assertStringNotContainsString('WIB', $html);
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_unsafe_source_url_is_not_an_active_pdf_link_or_rendered_payload(): void
    {
        $html = view('hasil-pdf', $this->data(['raw_input' => null, 'source_url' => 'javascript:alert(1)']))->render();

        $this->assertStringContainsString('Tautan sumber tidak dapat ditampilkan karena formatnya tidak aman.', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('href=', $html);
    }

    public function test_long_explanation_and_unbroken_url_render_across_pages_without_web_assets(): void
    {
        $data = $this->data([
            'input_type' => 'url', 'raw_input' => null,
            'source_url' => 'https://example.test/'.str_repeat('abcdef', 300),
        ], ['explanation' => str_repeat("Masyarakat perlu memeriksa informasi, kutipan “asli”, serta café.\n", 180).'AKHIR_PENJELASAN']);
        $html = view('hasil-pdf', $data)->render();
        $this->assertStringContainsString('AKHIR_PENJELASAN', $html);
        $this->assertStringNotContainsString('woff2', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('/build/', $html);
        $pdf = Pdf::loadView('hasil-pdf', $data)->setPaper('a4', 'portrait');
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertGreaterThan(3, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    private function data(array $submission = [], array $result = []): array
    {
        return [
            'submission' => Submission::factory()->make(array_merge([
                'id' => 7, 'user_id' => null, 'status' => 'completed', 'processing_stage' => 'done',
                'raw_input' => 'Informasi berbahasa Indonesia perlu diperiksa bersama sumber tepercaya.',
                'created_at' => '2026-10-15 09:00:00', 'source_language' => 'id',
            ], $submission)),
            'result' => DetectionResult::factory()->make(array_merge([
                'submission_id' => 7, 'label' => 'valid', 'confidence_score' => 0.91, 'model_version' => 'v1.0.0',
                'explanation_status' => 'ready', 'explanation' => 'Penjelasan final: “Periksa sumbernya.”',
            ], $result)),
        ];
    }
}
