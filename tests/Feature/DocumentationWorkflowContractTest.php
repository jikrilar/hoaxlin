<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Media\TranscriptionMediaContract;
use App\Support\SubmissionFormOptions;
use App\View\Presenters\DetectionPresentation;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentationWorkflowContractTest extends TestCase
{
    #[DataProvider('informationPages')]
    public function test_information_pages_are_public_readable_and_link_to_the_real_examination_form(string $route, string $title, ?bool $verified): void
    {
        if ($verified !== null) {
            $this->actingAs(User::factory()->make(['email_verified_at' => $verified ? now() : null]));
        }

        $response = $this->get(route($route))->assertOk()->assertSee($title)
            ->assertSee('Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.');
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET);
            $dom = new DOMXPath($document);
            $this->assertSame(1, $dom->query('//main')->length);
            $this->assertSame(1, $dom->query('//main//h1')->length);
            $this->assertSame($title, $dom->evaluate('string(//main//h1)'));
            $this->assertSame(1, $dom->query('//main//a[@href="'.route('home').'#cek-berita"]')->length);
            $this->assertSame(0, $dom->query('//main//*[@style or @onmouseover or @onmouseout]')->length);
            $this->assertStringContainsString('prediksi model, bukan verifikasi kebenaran yang definitif', $dom->evaluate('string(//meta[@name="description"]/@content)'));
            $ids = [];
            foreach ($dom->query('//main//section') as $section) {
                $id = $section->getAttribute('aria-labelledby');
                $this->assertNotSame('', $id);
                $this->assertSame(1, $dom->query('.//h2[@id="'.$id.'"]', $section)->length);
                $this->assertNotContains($id, $ids);
                $ids[] = $id;
            }
            if ($route === 'kebijakan-privasi') {
                $accountRoute = $verified === null ? 'login' : 'profile';
                $this->assertSame(1, $dom->query('//main//a[@href="'.route($accountRoute).'"]')->length);
                $this->assertSame(1, $dom->query('//main//a[@href="https://developers.openai.com/api/docs/guides/your-data" and @target="_blank" and contains(@rel,"noopener") and contains(@rel,"noreferrer")]')->length);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public static function informationPages(): array
    {
        $cases = [];
        foreach (['cara-kerja' => 'Cara kerja Hoaxlin', 'tentang' => 'Tentang Hoaxlin', 'kebijakan-privasi' => 'Kebijakan privasi'] as $route => $title) {
            foreach (['guest' => null, 'unverified' => false, 'verified' => true] as $viewer => $verified) {
                $cases[$route.' '.$viewer] = [$route, $title, $verified];
            }
        }

        return $cases;
    }

    #[DataProvider('mediaRetentionSettings')]
    public function test_privacy_retention_matches_the_configured_media_cutoff_without_promising_a_deletion_deadline(int $configured, int $hours): void
    {
        config(['data_retention.media_hours' => $configured]);
        $this->get(route('kebijakan-privasi'))->assertOk()
            ->assertSee('selesai atau gagal menjadi layak dihapus setelah '.$hours.' jam sejak waktu akhir pemrosesan tercatat')
            ->assertSee('bergantung pada scheduler')->assertSee('keberhasilan penghapusan file')
            ->assertSee('bukan jaminan bahwa file pasti hilang tepat pada batas waktu')
            ->assertSee('masih diproses tidak dibersihkan')->assertSee('bukan teks, terjemahan, hasil, referensi terkait, atau akun')
            ->assertSee('termasuk milik tamu')->assertDontSee('maksimal 24 jam');
    }

    public static function mediaRetentionSettings(): array
    {
        return ['default' => [24, 24], 'custom' => [72, 72], 'zero clamped' => [0, 1], 'negative clamped' => [-8, 1]];
    }

    public function test_privacy_revision_date_is_explicit_and_does_not_follow_the_request_date(): void
    {
        $this->travelTo('2032-01-06 12:00:00');
        $this->get(route('kebijakan-privasi'))->assertOk()
            ->assertSee('<time datetime="2026-10-14">14 Oktober 2026</time>', false)
            ->assertDontSee('18 September 2026');
    }

    public function test_privacy_copy_preserves_actual_data_provider_access_and_deletion_boundaries(): void
    {
        Http::preventStrayRequests();
        $this->get(route('kebijakan-privasi'))->assertOk()
            ->assertSeeInOrder(['Data yang diproses', 'Pemrosesan dan layanan pendukung', 'Penyimpanan dan akses', 'Retensi media dan data lain', 'Pilihan akun dan penghapusan', 'Pembaruan kebijakan'])
            ->assertSee('kata sandi yang disimpan sebagai hash')->assertSee('verifikasi dan pemulihan kata sandi')
            ->assertSee('file media untuk transkripsi')->assertSee('teks untuk terjemahan Inggris ke Indonesia')
            ->assertSee('prediksi, cuplikan teks, dan referensi')->assertSee('tidak berlangsung hanya di perangkatmu')
            ->assertSee('Alamat IP digunakan untuk pembatasan permintaan')->assertSee('informasi sesi dan perangkat')
            ->assertSee('pemilik dan admin sesuai izin aplikasi')->assertSee('termasuk ketika peminta adalah admin')
            ->assertSee('memerlukan email terverifikasi')->assertSee('ID submission saja bukan izin berbagi publik')
            ->assertSee('tidak menghapus data pemeriksaan dari server')->assertSee('cache')->assertSee('Log dan catatan penggunaan')
            ->assertSee('tidak otomatis menjadi data training model')->assertSee('kata sandi saat ini dan konfirmasi')
            ->assertSee('referensi terkait, feedback terkait, catatan pemrosesan, dan file media terkait')
            ->assertSee('tidak menjalankan pembersihan menyeluruh pada cache, log, atau cadangan')
            ->assertSee('tidak mengirim permintaan penghapusan ke layanan eksternal')
            ->assertDontSee('tanpa identitas personal')->assertDontSee('form di halaman Tentang')
            ->assertDontSee('Perubahan material akan diumumkan');
        Http::assertNothingSent();
    }

    public function test_how_it_works_explains_the_real_pipeline_and_preserves_result_semantics(): void
    {
        $response = $this->get(route('cara-kerja'))->assertOk()
            ->assertSeeInOrder(['Pilih input yang sesuai', 'Ekstraksi teks', 'Terjemahan bila diperlukan', 'Klasifikasi IndoBERT', 'Retrieval referensi terkait', 'Penjelasan bila tersedia', 'Membaca hasil dengan hati-hati', 'Tinjau sumber secara mandiri'])
            ->assertSee('Tamu hanya dapat mengirim teks')->assertSee('teks Inggris diterjemahkan ke Indonesia')
            ->assertSee('bukan kelas training ketiga')->assertSee('Ini bukan bukti bahwa informasi benar atau palsu.')
            ->assertSee('tidak mengubah label atau skor keyakinan classifier')
            ->assertSee('bukan persentase kebenaran berita')->assertSee('bukan confidence classifier')
            ->assertSee('basis pengetahuan saat ini')->assertSee('statusnya ditampilkan secara jujur')
            ->assertSee('Hasil final ditampilkan setelah pemrosesan selesai')
            ->assertSee('Halaman YouTube, TikTok, Instagram, Facebook, atau halaman pemutar tidak didukung.');
        foreach (['valid', 'hoax', 'meragukan'] as $label) {
            $response->assertSee(DetectionPresentation::label($label)['text']);
        }
    }

    public function test_documented_media_limits_follow_the_same_contract_as_the_examination_form(): void
    {
        config(['media.transcription.max_bytes' => 7 * 1048576, 'media.transcription.max_duration_seconds' => 93]);
        $options = SubmissionFormOptions::from(app(TranscriptionMediaContract::class));
        $this->get(route('cara-kerja'))->assertOk()->assertSee('50–50.000 karakter')
            ->assertSee(strtoupper(implode(', ', $options['image']['extensions'])))
            ->assertSee($options['image']['maxSizeLabel'])
            ->assertSee($options['image']['maxWidth'].' × '.$options['image']['maxHeight'].' piksel')
            ->assertSee(strtoupper(implode(', ', $options['video']['extensions'])))
            ->assertSee('maksimal 7 MiB dan 93 detik')
            ->assertSee(strtoupper(implode(', ', $options['videoUrl']['extensions'])));
    }

    public function test_about_explains_actual_access_and_provider_boundaries_with_real_links(): void
    {
        $this->get(route('tentang'))->assertOk()->assertSee('Tamu dapat memeriksa teks tanpa akun')
            ->assertSee('memerlukan akun dengan email terverifikasi')->assertSee('pemilik dan admin sesuai izin aplikasi')
            ->assertSee('ID submission saja bukan tautan berbagi publik')->assertSee('layanan eksternal')
            ->assertSee('tidak otomatis mendukung prediksi')->assertSee('tidak membatalkan klasifikasi yang sudah selesai')
            ->assertSee(route('kebijakan-privasi'), false)->assertSee(route('cara-kerja'), false)
            ->assertDontSee('Abdan Dzul Ghaffar Razaq')->assertDontSee('Avatar pengembang');
    }

    public function test_primary_documentation_matches_pipeline_queues_and_retention_contract(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $runbook = file_get_contents(base_path('RUNBOOK.md'));
        $dockerSetup = file_get_contents(base_path('DOCKER-SETUP.md'));
        $docs = $readme."\n".$runbook."\n".$dockerSetup;

        foreach ([
            'ProcessSubmission',
            'ExtractSubmissionText',
            'TranslateSubmissionText',
            'ClassifySubmission',
            'RetrieveSubmissionEvidence',
            'GenerateSubmissionExplanation',
        ] as $stage) {
            $this->assertStringContainsString($stage, $readme);
        }

        $this->assertStringContainsString(
            'extract-text,extract-media,inference,retrieval,explanation,default',
            $readme,
        );
        $this->assertStringContainsString('MEDIA_RETENTION_HOURS', $docs);
        $this->assertStringContainsString('media:prune --hours=24', $docs);
        $this->assertStringNotContainsString('media:prune --days', $docs);
        $this->assertStringNotContainsString('daily 03:00', $docs);
    }

    public function test_documented_all_in_one_development_processes_match_composer_script(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $devScript = implode("\n", $composer['scripts']['dev']);
        $readme = file_get_contents(base_path('README.md'));

        foreach (['php artisan serve', 'queue:listen', 'php artisan pail', 'npm run dev', 'uvicorn'] as $process) {
            $this->assertStringContainsString($process, $devScript);
        }

        $this->assertStringContainsString('lima proses', $readme);
    }

    public function test_public_copy_does_not_claim_completion_notifications(): void
    {
        $register = file_get_contents(resource_path('views/auth/register.blade.php'));

        $this->assertStringNotContainsString('Notifikasi Hasil', $register);
        $this->assertStringContainsString('Gunakan akun untuk memeriksa tautan dan media.', $register);
        $this->assertStringContainsString('verifikasi email untuk mengakses riwayat dan memberikan umpan balik.', $register);
    }

    public function test_bootstrap_scripts_start_the_scheduler_service(): void
    {
        $powershell = file_get_contents(base_path('scripts/docker-setup.ps1'));
        $shell = file_get_contents(base_path('scripts/docker-setup.sh'));

        $this->assertStringContainsString("@('up', '-d', 'queue', 'scheduler')", $powershell);
        $this->assertStringContainsString('up -d queue scheduler', $shell);
        $this->assertStringContainsString('wait_service "$service" healthy,running', $shell);
    }

    public function test_primary_document_references_exist(): void
    {
        foreach (['PRD.md', 'DOCKER-SETUP.md', 'RUNBOOK.md', 'bert-service/README.md', 'TASK.md'] as $path) {
            $this->assertFileExists(base_path($path));
        }
    }
}
