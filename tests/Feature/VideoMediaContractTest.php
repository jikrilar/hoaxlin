<?php

namespace Tests\Feature;

use App\Contracts\HostResolver;
use App\Contracts\Translator;
use App\Enums\EventOutcome;
use App\Enums\InputType;
use App\Exceptions\AiServiceException;
use App\Jobs\ExtractSubmissionText;
use App\Models\Submission;
use App\Models\User;
use App\Services\Extraction\OpenAiVideoExtractor;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Media\MediaDurationProbe;
use App\Services\Media\TranscriptionMediaContract;
use App\Services\Network\ExternalUrlGuard;
use App\Services\Network\SafeExternalHttpClient;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Pipeline\SubmissionStateMachine;
use App\Services\Resilience\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VideoMediaContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        config([
            'filesystems.media_disk' => 'local',
            'services.openai.key' => 'test-openai-key',
            'services.openai.rate_limit_per_minute' => 1000,
            'services.openai.monthly_quota_usd' => 1000,
            'services.openai.translation_model' => 'gpt-4o-mini',
            'services.openai.translation_prompt_version' => 'media-contract-test',
        ]);

        $this->get(route('home'));

        $probe = Mockery::mock(MediaDurationProbe::class);
        $probe->shouldReceive('probePath')->andReturnNull()->byDefault();
        $probe->shouldReceive('probeBytes')->andReturnNull()->byDefault();
        $this->app->instance(MediaDurationProbe::class, $probe);
    }

    public function test_valid_video_upload_uses_the_shared_contract(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'video',
            'media_file' => UploadedFile::fake()->create('berita.mp4', 1024, 'video/mp4'),
        ]))->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame('video', $submission->input_type);
        Storage::disk('local')->assertExists($submission->media_path);
    }

    public function test_video_upload_at_the_exact_size_limit_is_accepted(): void
    {
        Queue::fake();
        $media = app(TranscriptionMediaContract::class);

        $this->actingAs(User::factory()->create())->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'video',
            'media_file' => UploadedFile::fake()->create('batas.mp4', $media->maxKilobytes(), 'video/mp4'),
        ]))->assertRedirect();

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_oversized_video_upload_is_rejected(): void
    {
        Queue::fake();
        $media = app(TranscriptionMediaContract::class);

        $this->actingAs(User::factory()->create())->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'video',
            'media_file' => UploadedFile::fake()->create('besar.mp4', $media->maxKilobytes() + 1, 'video/mp4'),
        ]))->assertSessionHasErrors(['media_file' => 'Ukuran video melebihi batas 24 MiB.']);

        $this->assertDatabaseEmpty('submissions');
        Queue::assertNothingPushed();
    }

    public function test_video_limit_and_docker_upload_headroom_are_consistent(): void
    {
        $media = app(TranscriptionMediaContract::class);
        $this->assertSame(24 * 1024 * 1024, $media->maxBytes());
        $this->assertSame(24 * 1024, $media->maxKilobytes());
        $this->assertSame('24 MiB', $media->maxSizeLabel());

        $limits = parse_ini_file(base_path('docker/php/uploads.ini'));
        $this->assertMatchesRegularExpression('/^\d+M$/', $limits['upload_max_filesize']);
        $this->assertMatchesRegularExpression('/^\d+M$/', $limits['post_max_size']);
        $this->assertGreaterThan(24, (int) $limits['upload_max_filesize']);
        $this->assertGreaterThan((int) $limits['upload_max_filesize'], (int) $limits['post_max_size']);
    }

    public function test_video_upload_ui_uses_the_configured_limit(): void
    {
        config(['media.transcription.max_bytes' => 2 * 1024 * 1024]);

        $this->actingAs(User::factory()->create())->get(route('home'))
            ->assertOk()
            ->assertSee('data-video-max-bytes="2097152"', false)
            ->assertSee('id="video-size-error" role="alert" hidden', false)
            ->assertSee('Ukuran video melebihi batas 2 MiB. Pilih file yang lebih kecil.');
    }

    #[DataProvider('unsupportedUploads')]
    public function test_unsupported_upload_extension_or_mime_is_rejected(string $filename, string $mime): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'video',
            'media_file' => UploadedFile::fake()->create($filename, 1024, $mime),
        ]))->assertSessionHasErrors('media_file');
    }

    /** @return array<string, array{string, string}> */
    public static function unsupportedUploads(): array
    {
        return [
            'unsupported avi' => ['berita.avi', 'video/x-msvideo'],
            'extension mime mismatch' => ['berita.mp4', 'audio/mpeg'],
        ];
    }

    public function test_duration_over_limit_is_rejected_when_ffprobe_is_available(): void
    {
        Queue::fake();
        $probe = Mockery::mock(MediaDurationProbe::class);
        $probe->shouldReceive('probePath')->once()->andReturn(301.0);
        $this->app->instance(MediaDurationProbe::class, $probe);

        $this->actingAs(User::factory()->create())->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'video',
            'media_file' => UploadedFile::fake()->create('panjang.mp4', 1024, 'video/mp4'),
        ]))->assertSessionHasErrors('media_file');
    }

    public function test_missing_ffprobe_does_not_reject_an_otherwise_valid_upload(): void
    {
        Queue::fake();
        config(['media.transcription.ffprobe_binary' => 'definitely-missing-ffprobe-binary']);
        $this->app->forgetInstance(MediaDurationProbe::class);

        $this->actingAs(User::factory()->create())->post(route('deteksi'), $this->withCaptcha([
            'input_type' => 'video',
            'media_file' => UploadedFile::fake()->create('berita.webm', 1024, 'video/webm'),
        ]))->assertRedirect();
    }

    #[DataProvider('validRemoteMedia')]
    public function test_valid_direct_video_and_audio_urls_are_transcribed(string $url, string $mime, string $bytes): void
    {
        Http::fake(function (Request $request) use ($url, $mime, $bytes) {
            return match ($request->url()) {
                $url => Http::response($bytes, 200, ['Content-Type' => $mime]),
                'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Isi berita hasil transkripsi yang dapat dianalisis dengan benar.']),
                default => Http::response('', 500),
            };
        });

        $result = $this->extractorFor(parse_url($url, PHP_URL_HOST))->extract($this->remoteSubmission($url));

        $this->assertSame('Isi berita hasil transkripsi yang dapat dianalisis dengan benar.', $result->text);
    }

    /** @return array<string, array{string, string, string}> */
    public static function validRemoteMedia(): array
    {
        return [
            'video' => ['https://media.test/news.mp4', 'video/mp4', self::mp4Bytes()],
            'audio' => ['https://media.test/news.mp3', 'audio/mpeg', self::mp3Bytes()],
        ];
    }

    public function test_transcription_does_not_force_language_and_english_enters_translation_pipeline(): void
    {
        $url = 'https://media.test/english.mp3';
        $english = 'The government announced a new policy for all residents after the official meeting today.';
        Http::fake(function (Request $request) use ($url, $english) {
            return match ($request->url()) {
                $url => Http::response(self::mp3Bytes(), 200, ['Content-Type' => 'audio/mpeg']),
                'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => $english]),
                'https://api.openai.com/v1/responses' => Http::response([
                    'output_text' => json_encode([
                        'source_language' => 'en',
                        'translated' => true,
                        'indonesian_text' => 'Pemerintah mengumumkan kebijakan baru untuk seluruh warga setelah rapat resmi hari ini.',
                    ], JSON_THROW_ON_ERROR),
                    'usage' => ['input_tokens' => 18, 'output_tokens' => 16],
                ]),
                default => Http::response('', 500),
            };
        });

        $transcription = $this->extractorFor('media.test')->extract($this->remoteSubmission($url));
        $translation = app(Translator::class)->translate($transcription->text);

        $this->assertSame('en', $translation->sourceLanguage);
        $this->assertTrue($translation->translated);
        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.openai.com/v1/audio/transcriptions') {
                return false;
            }

            return ! collect($request->data())->contains(fn (array $part): bool => ($part['name'] ?? null) === 'language');
        });
    }

    public function test_indonesian_transcription_bypasses_translation_provider(): void
    {
        $url = 'https://media.test/indonesia.wav';
        $indonesian = 'Pemerintah mengumumkan kebijakan baru untuk masyarakat setelah rapat resmi hari ini.';
        Http::fake(function (Request $request) use ($url, $indonesian) {
            return match ($request->url()) {
                $url => Http::response(self::wavBytes(), 200, ['Content-Type' => 'audio/wav']),
                'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => $indonesian]),
                default => Http::response('', 500),
            };
        });

        $transcription = $this->extractorFor('media.test')->extract($this->remoteSubmission($url));
        $translation = app(Translator::class)->translate($transcription->text);

        $this->assertSame('id', $translation->sourceLanguage);
        $this->assertFalse($translation->translated);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses');
    }

    #[DataProvider('blockedPageUrls')]
    public function test_platform_and_non_direct_page_urls_are_rejected_before_network(string $url): void
    {
        Http::preventStrayRequests();
        $this->expectException(AiServiceException::class);

        $this->extractorFor(parse_url($url, PHP_URL_HOST))->extract($this->remoteSubmission($url));
    }

    /** @return array<string, array{string}> */
    public static function blockedPageUrls(): array
    {
        return [
            'youtube' => ['https://www.youtube.com/watch/video.mp4'],
            'tiktok' => ['https://www.tiktok.com/watch/video.mp4'],
            'instagram' => ['https://www.instagram.com/reel/video.mp4'],
            'facebook' => ['https://www.facebook.com/watch/video.mp4'],
            'generic player page' => ['https://media.test/player?id=1'],
        ];
    }

    #[DataProvider('invalidRemoteResponses')]
    public function test_non_media_and_mismatched_remote_responses_are_rejected(string $mime, string $bytes): void
    {
        $url = 'https://media.test/news.mp4';
        Http::fake([$url => Http::response($bytes, 200, ['Content-Type' => $mime])]);

        try {
            $this->extractorFor('media.test')->extract($this->remoteSubmission($url));
            $this->fail('Invalid remote media should be rejected.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function invalidRemoteResponses(): array
    {
        return [
            'html' => ['text/html', '<html>'.str_repeat('x', 2048).'</html>'],
            'json' => ['application/json', str_repeat('{"x":1}', 300)],
            'unsupported content type' => ['image/png', str_repeat('x', 2048)],
            'mime extension mismatch' => ['audio/mpeg', self::mp4Bytes()],
        ];
    }

    public function test_content_length_over_limit_is_rejected(): void
    {
        config(['media.transcription.max_bytes' => 2048]);
        $url = 'https://media.test/news.mp4';
        Http::fake([$url => Http::response(self::mp4Bytes(), 200, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => '2049',
        ])]);

        $this->expectPermanentMediaFailure(fn () => $this->extractorFor('media.test')->extract($this->remoteSubmission($url)));
    }

    public function test_actual_body_over_limit_is_rejected_without_trusting_content_length(): void
    {
        config(['media.transcription.max_bytes' => 1200]);
        $url = 'https://media.test/news.mp4';
        Http::fake([$url => Http::response(self::mp4Bytes(2048), 200, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => '1000',
        ])]);

        $this->expectPermanentMediaFailure(fn () => $this->extractorFor('media.test')->extract($this->remoteSubmission($url)));
    }

    public function test_octet_stream_requires_matching_extension_and_signature(): void
    {
        $url = 'https://media.test/news.webm';
        Http::fake(function (Request $request) use ($url) {
            return match ($request->url()) {
                $url => Http::response(self::webmBytes(), 200, ['Content-Type' => 'application/octet-stream']),
                'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Transkripsi media biner yang valid untuk dianalisis.']),
                default => Http::response('', 500),
            };
        });

        $result = $this->extractorFor('media.test')->extract($this->remoteSubmission($url));

        $this->assertStringContainsString('valid', $result->text);
    }

    public function test_timeout_hierarchy_is_explicit_and_extract_job_uses_it(): void
    {
        $contract = app(TranscriptionMediaContract::class);
        $contract->assertTimeoutHierarchy();

        $this->assertLessThan(config('media.transcription.job_timeout_seconds'), config('media.transcription.provider_timeout_seconds'));
        $this->assertLessThan(config('media.transcription.worker_timeout_seconds'), config('media.transcription.job_timeout_seconds'));
        $this->assertLessThan(config('media.transcription.retry_after_seconds'), config('media.transcription.worker_timeout_seconds'));
        $this->assertSame((int) config('media.transcription.job_timeout_seconds'), (new ExtractSubmissionText(1))->timeout);
        $this->assertSame((int) config('media.transcription.retry_after_seconds'), config('queue.connections.redis.retry_after'));
    }

    public function test_provider_connection_and_server_failures_remain_retryable(): void
    {
        foreach (['connection', 'server'] as $case) {
            Cache::flush();
            $url = "https://media.test/{$case}.mp3";
            Http::fake(function (Request $request) use ($url, $case) {
                if ($request->url() === $url) {
                    return Http::response(self::mp3Bytes(), 200, ['Content-Type' => 'audio/mpeg']);
                }
                if ($case === 'connection') {
                    throw new ConnectionException('provider unavailable');
                }

                return Http::response([], 503);
            });

            try {
                $this->extractorFor('media.test')->extract($this->remoteSubmission($url));
                $this->fail('Expected retryable provider failure.');
            } catch (AiServiceException $exception) {
                $this->assertTrue($exception->retryable);
            }
        }
    }

    public function test_permanent_media_contract_failure_is_terminal_without_retry(): void
    {
        $submission = Submission::create([
            'input_type' => InputType::Video->value,
            'source_url' => 'https://www.youtube.com/watch/video.mp4',
            'status' => 'processing',
        ]);
        $job = (new ExtractSubmissionText($submission->id))->withFakeQueueInteractions();
        $job->job->attempts = 1;

        $job->handle(
            new TextExtractorResolver([$this->extractorFor('www.youtube.com')]),
            app(SubmissionStateMachine::class),
        );

        $job->assertFailed()->assertNotReleased();
        $submission->refresh();
        $this->assertSame('failed', $submission->status);
        $this->assertSame(0, $submission->processingEvents()->where('outcome', EventOutcome::Retried->value)->count());
    }

    public function test_downloaded_direct_media_uses_existing_retention_lifecycle(): void
    {
        $url = 'https://media.test/retained.mp4';
        Http::fake(function (Request $request) use ($url) {
            return match ($request->url()) {
                $url => Http::response(self::mp4Bytes(), 200, ['Content-Type' => 'video/mp4']),
                'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Transkripsi berita yang disimpan untuk proses analisis.']),
                default => Http::response('', 500),
            };
        });
        $submission = Submission::create([
            'input_type' => InputType::Video->value,
            'source_url' => $url,
            'status' => 'processing',
        ]);

        $this->extractorFor('media.test')->extract($submission);
        $submission->refresh();
        $this->assertNotNull($submission->media_path);
        Storage::disk('local')->assertExists($submission->media_path);

        $submission->update([
            'status' => 'completed',
            'processing_completed_at' => now()->subHours(25),
        ]);
        $this->assertSame(0, Artisan::call('media:prune'));

        Storage::disk('local')->assertMissing($submission->media_path);
        $this->assertDatabaseHas('submissions', ['id' => $submission->id]);
    }

    private function extractorFor(string $host): OpenAiVideoExtractor
    {
        $resolver = new VideoContractHostResolver([$host => ['8.8.8.8']]);

        return new OpenAiVideoExtractor(
            new CircuitBreaker('openai'),
            new OpenAiQuota,
            new SafeExternalHttpClient(new ExternalUrlGuard($resolver)),
            app(TranscriptionMediaContract::class),
            app(MediaDurationProbe::class),
        );
    }

    private function remoteSubmission(string $url): Submission
    {
        return new Submission(['input_type' => InputType::Video->value, 'source_url' => $url]);
    }

    private function expectPermanentMediaFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected permanent media contract failure.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
        }
    }

    private static function mp4Bytes(int $length = 2048): string
    {
        return "\x00\x00\x00\x18ftypisom".str_repeat("\0", max(0, $length - 12));
    }

    private static function mp3Bytes(): string
    {
        return 'ID3'.str_repeat("\0", 2045);
    }

    private static function wavBytes(): string
    {
        return 'RIFF'."\x00\x08\x00\x00".'WAVE'.str_repeat("\0", 2036);
    }

    private static function webmBytes(): string
    {
        return "\x1A\x45\xDF\xA3".str_repeat("\0", 2044);
    }

    /** @param array<string, mixed> $payload */
    private function withCaptcha(array $payload): array
    {
        return [...$payload, 'captcha_answer' => (int) session('submission_captcha.answer')];
    }
}

class VideoContractHostResolver implements HostResolver
{
    /** @param array<string, list<string>> $addresses */
    public function __construct(private readonly array $addresses) {}

    public function resolve(string $host): array
    {
        return $this->addresses[$host] ?? [];
    }
}
