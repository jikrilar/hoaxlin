<?php

namespace App\Services\Admin;

use App\Services\Bert\BertRuntimeMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only service health for the admin dashboard (T3.2).
 *
 * Every probe answers one of three states: ok, degraded, unavailable. A probe
 * that cannot be performed (no key configured, timeout, malformed payload) is
 * reported as unavailable — never guessed as healthy.
 */
final class ServiceHealth
{
    public const OK = 'ok';

    public const DEGRADED = 'degraded';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(private readonly BertRuntimeMetadata $bert) {}

    /**
     * @return array<string, array{state: string, label: string, detail: string}>
     */
    public function all(): array
    {
        return Cache::remember('admin:service-health', 60, fn (): array => [
            'bert' => $this->bert(),
            'rag' => $this->rag(),
            'openai' => $this->openai(),
        ]);
    }

    public function bert(): array
    {
        try {
            $metadata = $this->bert->fetch();
        } catch (Throwable) {
            return $this->result(self::UNAVAILABLE, 'BERT', 'Status layanan tidak dapat dibaca.');
        }

        if (! ($metadata['available'] ?? false)) {
            return $this->result(self::UNAVAILABLE, 'BERT', 'Layanan model tidak tersedia.');
        }

        return ($metadata['model_status'] ?? null) === 'ready'
            ? $this->result(self::OK, 'BERT', 'Model siap melayani pemeriksaan.')
            : $this->result(self::DEGRADED, 'BERT', 'Layanan merespons, tetapi model belum siap.');
    }

    public function rag(): array
    {
        $url = (string) config('services.rag.url', '');
        if ($url === '') {
            return $this->result(self::UNAVAILABLE, 'RAG', 'Alamat layanan rujukan belum dikonfigurasi.');
        }

        try {
            $request = Http::baseUrl(rtrim($url, '/'))
                ->acceptJson()
                ->connectTimeout(max(1, (int) config('services.rag.connect_timeout', 3)))
                ->timeout(max(1, (int) config('services.rag.timeout', 15)));

            if (filled(config('services.rag.internal_token'))) {
                $request = $request->withToken((string) config('services.rag.internal_token'));
            }

            $response = $request->get('/health/ready');
        } catch (Throwable) {
            return $this->result(self::UNAVAILABLE, 'RAG', 'Layanan rujukan tidak dapat dihubungi.');
        }

        if (! $response->successful()) {
            return $this->result(self::UNAVAILABLE, 'RAG', 'Readiness merespons HTTP '.$response->status().'.');
        }

        // The RAG service reports `ready` (503 + `not_ready` otherwise).
        return $response->json('status') === 'ready'
            ? $this->result(self::OK, 'RAG', 'Pencarian rujukan siap.')
            : $this->result(self::DEGRADED, 'RAG', 'Layanan merespons, tetapi indeks belum siap.');
    }

    public function openai(): array
    {
        // OpenAI has no health endpoint this app may call; configuration is the
        // only honest signal, and an absent key means "unavailable".
        if (blank(config('services.openai.key'))) {
            return $this->result(self::UNAVAILABLE, 'OpenAI', 'API key belum dikonfigurasi.');
        }

        return $this->result(self::OK, 'OpenAI', 'API key terkonfigurasi; kesehatan provider tidak diperiksa.');
    }

    private function result(string $state, string $service, string $detail): array
    {
        return [
            'state' => $state,
            'label' => match ($state) {
                self::OK => 'OK',
                self::DEGRADED => 'Terganggu',
                default => 'Belum tersedia',
            },
            'service' => $service,
            'detail' => $detail,
        ];
    }
}
