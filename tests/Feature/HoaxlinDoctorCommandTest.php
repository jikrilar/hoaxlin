<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HoaxlinDoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_APP_KEY = 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

    private const TEST_BERT_TOKEN = 'doctor-test-bert-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => self::TEST_APP_KEY,
            'app.cipher' => 'AES-256-CBC',
            'queue.default' => 'redis',
            'services.bert.url' => 'http://bert.test',
            'services.bert.internal_token' => self::TEST_BERT_TOKEN,
            'services.bert.model_version' => 'v1.0.0',
            'services.openai.key' => 'doctor-test-openai-key',
        ]);

        Storage::fake('submissions');
    }

    public function test_all_critical_dependencies_ready_returns_success(): void
    {
        $this->fakeHealthyDependencies();

        $this->artisan('hoaxlin:doctor')
            ->expectsOutputToContain('[PASS] Laravel')
            ->expectsOutputToContain('[PASS] Database')
            ->expectsOutputToContain('[PASS] Redis')
            ->expectsOutputToContain('[PASS] BERT model: v1.0.0')
            ->assertExitCode(0);
    }

    public function test_database_failure_returns_non_zero(): void
    {
        $this->fakeHealthyDependencies();
        $originalConnection = config('database.default');

        try {
            config(['database.default' => 'missing-doctor-connection']);

            $this->artisan('hoaxlin:doctor')
                ->expectsOutputToContain('[FAIL] Database')
                ->assertExitCode(1);
        } finally {
            config(['database.default' => $originalConnection]);
        }
    }

    public function test_redis_failure_returns_non_zero(): void
    {
        $this->fakeHealthyBert();
        $redis = Mockery::mock(RedisManager::class);
        $redis->shouldReceive('connection')->once()->andThrow(new RuntimeException('redis unavailable'));
        Redis::swap($redis);

        $this->artisan('hoaxlin:doctor')
            ->expectsOutputToContain('[FAIL] Redis')
            ->assertExitCode(1);
    }

    public function test_bert_not_ready_returns_non_zero(): void
    {
        $this->fakeRedis();
        Http::swap(new HttpFactory);
        Http::fake([
            'http://bert.test/health/ready' => Http::response([
                'status' => 'error',
                'model_status' => 'failed',
            ], 503),
        ]);

        $this->artisan('hoaxlin:doctor')
            ->expectsOutputToContain('[FAIL] BERT service')
            ->assertExitCode(1);
    }

    public function test_wrong_bert_version_returns_non_zero(): void
    {
        $this->fakeRedis();
        $this->fakeHealthyBert('v9.9.9');

        $this->artisan('hoaxlin:doctor')
            ->expectsOutputToContain('model version must be v1.0.0')
            ->assertExitCode(1);
    }

    public function test_missing_openai_key_warns_but_returns_success(): void
    {
        $this->fakeHealthyDependencies();
        config(['services.openai.key' => null]);

        $this->artisan('hoaxlin:doctor')
            ->expectsOutputToContain('[WARN] OpenAI API key belum dikonfigurasi')
            ->expectsOutputToContain('Environment siap dengan keterbatasan OpenAI.')
            ->assertExitCode(0);
    }

    public function test_unwritable_storage_returns_non_zero(): void
    {
        $this->fakeHealthyDependencies();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $filesystems = Mockery::mock(FilesystemManager::class);
        $filesystems->shouldReceive('disk')->with('submissions')->once()->andReturn($disk);
        $this->app->instance(FilesystemManager::class, $filesystems);

        $this->artisan('hoaxlin:doctor')
            ->expectsOutputToContain('[FAIL] Storage submissions')
            ->assertExitCode(1);
    }

    public function test_output_never_contains_configured_secrets(): void
    {
        $this->fakeHealthyDependencies();
        $secrets = [
            self::TEST_APP_KEY,
            self::TEST_BERT_TOKEN,
            'doctor-test-openai-key',
            'doctor-test-database-password',
        ];
        config(['database.connections.sqlite.password' => $secrets[3]]);

        $exitCode = Artisan::call('hoaxlin:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    private function fakeRedis(): void
    {
        $connection = new class
        {
            /** @var array<string, string> */
            private array $values = [];

            public function set(string $key, string $value): bool
            {
                $this->values[$key] = $value;

                return true;
            }

            public function get(string $key): ?string
            {
                return $this->values[$key] ?? null;
            }

            public function del(string $key): int
            {
                $existed = array_key_exists($key, $this->values);
                unset($this->values[$key]);

                return $existed ? 1 : 0;
            }
        };

        $redis = Mockery::mock(RedisManager::class);
        $redis->shouldReceive('connection')->once()->andReturn($connection);
        Redis::swap($redis);
    }

    private function fakeHealthyBert(string $modelVersion = 'v1.0.0'): void
    {
        Http::swap(new HttpFactory);
        Http::fake([
            'http://bert.test/health/ready' => Http::response([
                'status' => 'ok',
                'model_status' => 'ready',
                'model_version' => $modelVersion,
            ]),
            'http://bert.test/version' => Http::response([
                'service' => 'hoaxlin-bert',
                'service_version' => '1.0.0',
                'model_version' => $modelVersion,
                'model_status' => 'ready',
                'model_labels' => ['valid', 'hoax'],
                'threshold' => 0.99,
                'temperature' => 0.8706620666110391,
            ]),
        ]);
    }

    private function fakeHealthyDependencies(): void
    {
        $this->fakeRedis();
        $this->fakeHealthyBert();
    }
}
