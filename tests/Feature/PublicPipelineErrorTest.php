<?php

namespace Tests\Feature;

use App\Enums\ProcessingStage;
use App\Exceptions\AiServiceException;
use App\Models\Submission;
use App\Models\User;
use App\Services\Pipeline\PipelineFailureReporter;
use App\Services\Pipeline\SubmissionStateMachine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use League\Flysystem\UnableToReadFile;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class PublicPipelineErrorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk-proj-FAKE-SECRET Authorization: Bearer token-123 C:\\private\\media.mp4 SELECT * FROM users';

    public function test_generic_exception_is_stored_and_returned_only_as_a_safe_failure(): void
    {
        $submission = $this->submission();
        $loggedContext = null;

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use (&$loggedContext): bool {
                $loggedContext = $context;

                return $message === 'Submission pipeline failure.';
            });

        $failure = app(SubmissionStateMachine::class)->markFailed(
            $submission,
            ProcessingStage::Classifying,
            new RuntimeException(self::SECRET),
            2,
        );

        $this->assertSame(PipelineFailureReporter::UNKNOWN_ERROR, $failure->errorCode);
        $this->assertStringNotContainsString(self::SECRET, $failure->publicMessage);

        $submission->refresh();
        $event = $submission->processingEvents()->latest('id')->firstOrFail();

        $this->assertSame('failed', $submission->status);
        $this->assertSame(PipelineFailureReporter::UNKNOWN_ERROR, $submission->last_error_code);
        $this->assertSame('pipeline', $submission->last_error_service);
        $this->assertSame(app(PipelineFailureReporter::class)->publicMessageForCode(PipelineFailureReporter::UNKNOWN_ERROR), $submission->failure_reason);
        $this->assertStringNotContainsString(self::SECRET, $submission->failure_reason);
        $this->assertSame($loggedContext['error_reference'], $event->metadata['error_reference']);
        $this->assertSame($submission->id, $loggedContext['submission_id']);
        $this->assertSame('classifying', $loggedContext['stage']);
        $this->assertSame(2, $loggedContext['attempt']);
        $this->assertSame(RuntimeException::class, $loggedContext['exception_class']);
        $this->assertStringNotContainsString(self::SECRET, json_encode($loggedContext, JSON_THROW_ON_ERROR));
    }

    public function test_database_exception_maps_without_sql_or_connection_details(): void
    {
        $previous = new PDOException('password=database-secret host=10.0.0.5');
        $exception = new QueryException(
            'mysql-secret',
            'select * from users where api_key = ?',
            ['sk-database-secret'],
            $previous,
            ['driver' => 'mysql', 'host' => 'private-db', 'database' => 'secret-db'],
        );

        $failure = app(PipelineFailureReporter::class)->describe($exception);

        $this->assertSame(PipelineFailureReporter::PERSISTENCE_ERROR, $failure->errorCode);
        $this->assertSame('database', $failure->service);
        $this->assertStringNotContainsString('secret', strtolower($failure->publicMessage));
        $this->assertStringNotContainsString('select', strtolower($failure->publicMessage));
    }

    public function test_filesystem_exception_maps_without_exposing_media_path(): void
    {
        $failure = app(PipelineFailureReporter::class)->describe(
            UnableToReadFile::fromLocation('C:\\private\\uploads\\secret-video.mp4', 'token=media-secret'),
        );

        $this->assertSame(PipelineFailureReporter::MEDIA_IO_ERROR, $failure->errorCode);
        $this->assertSame('filesystem', $failure->service);
        $this->assertStringNotContainsString('private', strtolower($failure->publicMessage));
        $this->assertStringNotContainsString('token', strtolower($failure->publicMessage));
    }

    public function test_bert_and_openai_http_failures_use_stable_provider_categories(): void
    {
        $reporter = app(PipelineFailureReporter::class);
        $bert = $reporter->describe(AiServiceException::transient('bert', self::SECRET, 503));
        $openai = $reporter->describe(AiServiceException::permanent('openai', self::SECRET, 401));

        $this->assertSame(PipelineFailureReporter::DEPENDENCY_UNAVAILABLE, $bert->errorCode);
        $this->assertSame('bert', $bert->service);
        $this->assertSame(503, $bert->providerStatus);
        $this->assertSame(PipelineFailureReporter::PROVIDER_REQUEST_REJECTED, $openai->errorCode);
        $this->assertSame('openai', $openai->service);
        $this->assertSame(401, $openai->providerStatus);
        $this->assertStringNotContainsString(self::SECRET, $bert->publicMessage);
        $this->assertStringNotContainsString(self::SECRET, $openai->publicMessage);
    }

    public function test_configuration_exception_uses_safe_pipeline_message(): void
    {
        $failure = app(PipelineFailureReporter::class)->describe(new InvalidArgumentException(self::SECRET));

        $this->assertSame(PipelineFailureReporter::PIPELINE_CONFIGURATION_ERROR, $failure->errorCode);
        $this->assertSame('pipeline', $failure->service);
        $this->assertStringNotContainsString(self::SECRET, $failure->publicMessage);
    }

    public function test_status_json_and_result_page_ignore_legacy_raw_failure_reason(): void
    {
        $user = User::factory()->create();
        $submission = $this->submission([
            'user_id' => $user->id,
            'status' => 'failed',
            'failure_reason' => self::SECRET,
            'last_error_service' => 'pipeline',
            'last_error_code' => 'LegacyRawException',
        ]);
        $publicMessage = app(PipelineFailureReporter::class)->publicMessageForCode('LegacyRawException');

        $this->actingAs($user)
            ->getJson(route('hasil.status', $submission))
            ->assertOk()
            ->assertJsonPath('failure_reason', $publicMessage)
            ->assertDontSee(self::SECRET, false);

        $this->actingAs($user)
            ->get(route('hasil', $submission))
            ->assertOk()
            ->assertSee($publicMessage)
            ->assertDontSee(self::SECRET, false);

        $this->actingAs($user)
            ->get(route('riwayat.show', $submission))
            ->assertOk()
            ->assertSee($publicMessage)
            ->assertDontSee(self::SECRET, false);
    }

    /** @param array<string, mixed> $attributes */
    private function submission(array $attributes = []): Submission
    {
        return Submission::create(array_merge([
            'input_type' => 'text',
            'raw_input' => str_repeat('Berita untuk pengujian sanitasi. ', 4),
            'status' => 'processing',
            'processing_stage' => ProcessingStage::Classifying->value,
        ], $attributes));
    }
}
