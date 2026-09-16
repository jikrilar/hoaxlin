<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class HoaxlinDoctor extends Command
{
    private const EXPECTED_MODEL_VERSION = 'v1.0.0';

    private const EXPECTED_LABELS = ['valid', 'hoax'];

    private const EXPECTED_THRESHOLD = 0.99;

    protected $signature = 'hoaxlin:doctor';

    protected $description = 'Diagnose the Hoaxlin runtime environment without changing application data';

    private int $criticalFailures = 0;

    private DatabaseManager $database;

    private Migrator $migrator;

    private FilesystemManager $filesystems;

    public function handle(
        DatabaseManager $database,
        Migrator $migrator,
        FilesystemManager $filesystems,
    ): int {
        $this->database = $database;
        $this->migrator = $migrator;
        $this->filesystems = $filesystems;

        $this->line('Hoaxlin Environment Check');
        $this->newLine();

        $this->reportPass(sprintf(
            'Laravel %s; PHP %s; environment %s',
            Application::VERSION,
            PHP_VERSION,
            app()->environment(),
        ));

        $this->checkAppKey();
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkQueue();
        $this->checkStorage();
        $this->checkBert();
        $this->checkOpenAi();
        $this->checkOptionalBinaries();

        $this->newLine();

        if ($this->criticalFailures > 0) {
            $this->line(sprintf('Environment belum siap: %d dependency kritis gagal.', $this->criticalFailures));

            return self::FAILURE;
        }

        if (blank(config('services.openai.key'))) {
            $this->line('Environment siap dengan keterbatasan OpenAI.');
        } else {
            $this->line('Environment siap.');
        }

        return self::SUCCESS;
    }

    private function checkAppKey(): void
    {
        try {
            $configuredKey = (string) config('app.key', '');
            $key = str_starts_with($configuredKey, 'base64:')
                ? base64_decode(substr($configuredKey, 7), true)
                : $configuredKey;

            if ($key === false || ! Encrypter::supported($key, (string) config('app.cipher'))) {
                throw new \RuntimeException('format atau panjang key tidak valid');
            }

            app('encrypter');
            $this->reportPass('APP_KEY valid');
        } catch (Throwable $exception) {
            $this->recordFailure('APP_KEY', $exception);
        }
    }

    private function checkDatabase(): void
    {
        try {
            $connection = $this->database->connection();
            $connection->getPdo();
            $connection->selectOne('SELECT 1');

            $driver = $connection->getDriverName();
            $databaseName = $connection->getDatabaseName();
            $this->reportPass(sprintf('Database: %s (%s)', $driver, $databaseName));

            if (! $this->migrator->repositoryExists()) {
                $migrationCount = count($this->migrator->getMigrationFiles(database_path('migrations')));
                $this->reportWarning("Migration table belum tersedia; {$migrationCount} migration pending");

                return;
            }

            $migrationFiles = array_keys($this->migrator->getMigrationFiles(database_path('migrations')));
            $ranMigrations = $this->migrator->getRepository()->getRan();
            $pending = array_values(array_diff($migrationFiles, $ranMigrations));

            if ($pending !== []) {
                $this->reportWarning(sprintf('Database migrations: %d pending', count($pending)));
            } else {
                $this->reportPass('Database migrations: current');
            }
        } catch (Throwable $exception) {
            $this->recordFailure('Database', $exception);
        }
    }

    private function checkRedis(): void
    {
        $connection = null;
        $key = 'hoaxlin:doctor:'.Str::uuid();
        $value = Str::random(32);
        $written = false;
        $cleanupFailure = null;

        try {
            $connection = Redis::connection();
            $connection->set($key, $value);
            $written = true;

            if ($connection->get($key) !== $value) {
                throw new \RuntimeException('write/read probe returned an unexpected value');
            }
        } catch (Throwable $exception) {
            $this->recordFailure('Redis', $exception);

            return;
        } finally {
            if ($written && $connection !== null) {
                try {
                    $connection->del($key);
                } catch (Throwable $exception) {
                    $cleanupFailure = $exception;
                }
            }
        }

        if ($cleanupFailure !== null) {
            $this->recordFailure('Redis cleanup', $cleanupFailure);

            return;
        }

        $this->reportPass('Redis write/read/delete probe');
    }

    private function checkQueue(): void
    {
        try {
            $connectionName = (string) config('queue.default');
            $connection = config("queue.connections.{$connectionName}");

            if (! is_array($connection) || blank($connection['driver'] ?? null)) {
                throw new \RuntimeException("queue connection '{$connectionName}' tidak terdefinisi");
            }

            $driver = (string) $connection['driver'];
            if ($driver !== 'redis') {
                throw new \RuntimeException("Docker environment mengharapkan redis, bukan {$driver}");
            }

            $this->reportPass("Queue: {$connectionName} ({$driver})");
        } catch (Throwable $exception) {
            $this->recordFailure('Queue', $exception);
        }
    }

    private function checkStorage(): void
    {
        $disk = null;
        $path = '.hoaxlin-doctor/'.Str::uuid().'.tmp';
        $contents = Str::random(32);
        $written = false;
        $cleanupFailure = null;

        try {
            $disk = $this->filesystems->disk('submissions');
            if (! $disk->put($path, $contents)) {
                throw new \RuntimeException('write probe ditolak oleh storage backend');
            }
            $written = true;

            if ($disk->get($path) !== $contents) {
                throw new \RuntimeException('write/read probe returned an unexpected value');
            }
        } catch (Throwable $exception) {
            $this->recordFailure('Storage submissions', $exception);

            return;
        } finally {
            if ($written && $disk !== null) {
                try {
                    if (! $disk->delete($path)) {
                        throw new \RuntimeException('temporary probe could not be deleted');
                    }
                } catch (Throwable $exception) {
                    $cleanupFailure = $exception;
                }
            }
        }

        if ($cleanupFailure !== null) {
            $this->recordFailure('Storage cleanup', $cleanupFailure);

            return;
        }

        $this->reportPass('Storage: storage/app/private/submissions writable');
    }

    private function checkBert(): void
    {
        try {
            $url = rtrim((string) config('services.bert.url'), '/');
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new \RuntimeException('BERT_SERVICE_URL tidak valid');
            }
            if (blank(config('services.bert.internal_token'))) {
                throw new \RuntimeException('BERT service token belum dikonfigurasi');
            }

            $client = Http::baseUrl($url)
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(5);

            $readiness = $client->get('/health/ready');
            if (! $readiness->successful()) {
                throw new \RuntimeException("readiness endpoint returned HTTP {$readiness->status()}");
            }
            if ($readiness->json('status') !== 'ok' || $readiness->json('model_status') !== 'ready') {
                throw new \RuntimeException('model status is not ready');
            }
            $this->reportPass('BERT readiness');

            $version = $client->get('/version');
            if (! $version->successful()) {
                throw new \RuntimeException("version endpoint returned HTTP {$version->status()}");
            }

            $modelVersion = $version->json('model_version');
            $labels = $version->json('model_labels');
            $threshold = $version->json('threshold');
            $temperature = $version->json('temperature');

            if ($modelVersion !== self::EXPECTED_MODEL_VERSION) {
                throw new \RuntimeException('model version must be '.self::EXPECTED_MODEL_VERSION);
            }
            if ($labels !== self::EXPECTED_LABELS) {
                throw new \RuntimeException('model labels must be valid, hoax');
            }
            if (! is_numeric($threshold) || abs((float) $threshold - self::EXPECTED_THRESHOLD) > 1.0E-12) {
                throw new \RuntimeException('model threshold must be 0.99');
            }
            if (! is_numeric($temperature) || (float) $temperature <= 0) {
                throw new \RuntimeException('calibration temperature is unavailable');
            }

            $this->reportPass(sprintf(
                'BERT model: %s; labels valid, hoax; threshold 0.99; temperature %s',
                $modelVersion,
                (string) $temperature,
            ));
        } catch (Throwable $exception) {
            $this->recordFailure('BERT service', $exception);
        }
    }

    private function checkOpenAi(): void
    {
        if (blank(config('services.openai.key'))) {
            $this->reportWarning('OpenAI API key belum dikonfigurasi; OCR, transkripsi, terjemahan, dan penjelasan tidak tersedia');

            return;
        }

        $this->reportPass('OpenAI API key configured');
    }

    private function checkOptionalBinaries(): void
    {
        $finder = new ExecutableFinder;

        foreach (['ffprobe', 'clamdscan'] as $binary) {
            if ($finder->find($binary) !== null) {
                $this->line("[INFO] Optional binary {$binary}: available");
            } else {
                $this->reportWarning("Optional binary {$binary}: not available");
            }
        }
    }

    private function reportPass(string $message): void
    {
        $this->line("[PASS] {$message}");
    }

    private function reportWarning(string $message): void
    {
        $this->line("[WARN] {$message}");
    }

    private function recordFailure(string $check, Throwable $exception): void
    {
        $this->criticalFailures++;
        $this->line(sprintf('[FAIL] %s: %s', $check, $this->safeExceptionMessage($exception)));
    }

    private function safeExceptionMessage(Throwable $exception): string
    {
        $message = trim(strtok($exception->getMessage(), "\r\n") ?: 'unknown error');
        $secrets = [
            config('app.key'),
            config('database.connections.mysql.password'),
            config('services.bert.internal_token'),
            config('services.openai.key'),
        ];

        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '[REDACTED]', $message);
            }
        }

        return $message;
    }
}
