<?php

namespace Tests\Feature;

use App\Contracts\HostResolver;
use App\Enums\InputType;
use App\Exceptions\AiServiceException;
use App\Models\Submission;
use App\Services\Extraction\ArticleExtractor;
use App\Services\Extraction\OpenAiVideoExtractor;
use App\Services\Network\ExternalUrlGuard;
use App\Services\Network\SafeExternalHttpClient;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Resilience\CircuitBreaker;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExternalUrlSecurityTest extends TestCase
{
    public function test_public_https_url_is_accepted(): void
    {
        $guard = $this->guard(['news.test' => ['8.8.8.8', '2606:4700:4700::1111']]);

        $this->assertSame(
            'https://news.test:8443/story?id=7',
            $guard->validate('https://news.test:8443/story?id=7', 'article'),
        );
    }

    #[DataProvider('unsafeLiteralTargets')]
    public function test_unsafe_literal_targets_are_rejected(string $url): void
    {
        $this->expectException(AiServiceException::class);

        $this->guard()->validate($url, 'article');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeLiteralTargets(): array
    {
        return [
            'localhost' => ['http://localhost/article'],
            'ipv4 loopback' => ['http://127.0.0.1/article'],
            'ipv6 loopback' => ['http://[::1]/article'],
            'private ipv4' => ['http://10.20.30.40/article'],
            'link local' => ['http://169.254.169.254/latest/meta-data'],
            'multicast' => ['http://224.0.0.1/article'],
            'ipv6 multicast' => ['http://[ff02::1]/article'],
            'unspecified' => ['http://0.0.0.0/article'],
            'unsupported scheme' => ['ftp://8.8.8.8/article'],
        ];
    }

    public function test_hostname_resolving_to_any_private_ip_is_rejected(): void
    {
        $this->expectException(AiServiceException::class);

        $this->guard(['mixed.test' => ['8.8.8.8', '192.168.1.10']])
            ->validate('https://mixed.test/article', 'article');
    }

    public function test_empty_or_failed_dns_resolution_is_rejected(): void
    {
        $this->expectException(AiServiceException::class);

        $this->guard(['missing.test' => []])
            ->validate('https://missing.test/article', 'article');
    }

    public function test_article_extractor_follows_a_public_redirect_manually(): void
    {
        $extractor = $this->articleExtractor([
            'start.test' => ['8.8.8.8'],
            'destination.test' => ['1.1.1.1'],
        ]);

        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://start.test/story' => Http::response('', 302, ['Location' => 'https://destination.test/final?id=9']),
                'https://destination.test/final?id=9' => $this->articleResponse(),
                default => Http::response('', 500),
            };
        });

        $result = $extractor->extract($this->articleSubmission('https://start.test/story'));

        $this->assertSame(InputType::Url, $result->inputType);
        Http::assertSentCount(2);
    }

    public function test_article_extractor_rejects_public_to_private_redirect_before_requesting_it(): void
    {
        $extractor = $this->articleExtractor(['public.test' => ['8.8.8.8']]);

        Http::fake([
            'https://public.test/story' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
        ]);

        try {
            $extractor->extract($this->articleSubmission('https://public.test/story'));
            $this->fail('A private redirect target should be rejected.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
        }

        Http::assertSentCount(1);
    }

    public function test_redirect_chain_over_the_configured_limit_is_rejected(): void
    {
        config()->set('services.external_http.max_redirects', 2);
        $extractor = $this->articleExtractor([
            'one.test' => ['8.8.8.8'],
            'two.test' => ['8.8.4.4'],
            'three.test' => ['1.1.1.1'],
            'four.test' => ['1.0.0.1'],
        ]);

        Http::fake([
            'https://one.test/*' => Http::response('', 302, ['Location' => 'https://two.test/next']),
            'https://two.test/*' => Http::response('', 302, ['Location' => 'https://three.test/next']),
            'https://three.test/*' => Http::response('', 302, ['Location' => 'https://four.test/next']),
        ]);

        $this->expectException(AiServiceException::class);

        try {
            $extractor->extract($this->articleSubmission('https://one.test/start'));
        } finally {
            Http::assertSentCount(3);
        }
    }

    public function test_relative_redirect_preserves_custom_port_path_and_query(): void
    {
        $extractor = $this->articleExtractor(['public.test' => ['8.8.8.8']]);

        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://public.test:8443/news/start?source=home' => Http::response('', 302, ['Location' => '../final/story?lang=id']),
                'https://public.test:8443/final/story?lang=id' => $this->articleResponse(),
                default => Http::response('', 500),
            };
        });

        $extractor->extract($this->articleSubmission('https://public.test:8443/news/start?source=home'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://public.test:8443/final/story?lang=id');
    }

    public function test_direct_media_url_uses_the_same_public_target_validation(): void
    {
        Storage::fake('local');
        config()->set('filesystems.media_disk', 'local');
        config()->set('services.openai.key', 'test-key');
        $extractor = $this->videoExtractor(['media.test' => ['8.8.8.8']]);

        Http::fake(function (Request $request) {
            if ($request->url() === 'https://media.test:9443/files/video.mp4?download=1') {
                return Http::response($this->mp4Bytes(), 200, ['Content-Type' => 'video/mp4']);
            }

            if ($request->url() === 'https://api.openai.com/v1/audio/transcriptions') {
                return Http::response(['text' => 'Transkripsi video yang cukup untuk dianalisis.']);
            }

            return Http::response('', 500);
        });

        $result = $extractor->extract(new Submission([
            'input_type' => InputType::Video->value,
            'source_url' => 'https://media.test:9443/files/video.mp4?download=1',
        ]));

        $this->assertSame('Transkripsi video yang cukup untuk dianalisis.', $result->text);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://media.test:9443/files/video.mp4?download=1');
    }

    public function test_direct_media_public_to_private_redirect_is_rejected_before_private_request(): void
    {
        $extractor = $this->videoExtractor(['media.test' => ['8.8.8.8']]);

        Http::fake([
            'https://media.test/video.mp4' => Http::response('', 302, ['Location' => 'http://10.0.0.2/internal.mp4']),
        ]);

        try {
            $extractor->extract(new Submission([
                'input_type' => InputType::Video->value,
                'source_url' => 'https://media.test/video.mp4',
            ]));
            $this->fail('A private media redirect target should be rejected.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
        }

        Http::assertSentCount(1);
    }

    /**
     * @param  array<string, list<string>>  $addresses
     */
    private function guard(array $addresses = []): ExternalUrlGuard
    {
        return new ExternalUrlGuard(new FakeHostResolver($addresses));
    }

    /**
     * @param  array<string, list<string>>  $addresses
     */
    private function articleExtractor(array $addresses): ArticleExtractor
    {
        return new ArticleExtractor(new SafeExternalHttpClient($this->guard($addresses)));
    }

    /**
     * @param  array<string, list<string>>  $addresses
     */
    private function videoExtractor(array $addresses): OpenAiVideoExtractor
    {
        return new OpenAiVideoExtractor(
            new CircuitBreaker('openai'),
            new OpenAiQuota,
            new SafeExternalHttpClient($this->guard($addresses)),
        );
    }

    private function articleSubmission(string $url): Submission
    {
        return new Submission([
            'input_type' => InputType::Url->value,
            'source_url' => $url,
        ]);
    }

    private function articleResponse(): PromiseInterface
    {
        return Http::response(
            '<html><article>'.str_repeat('Isi artikel berita yang dapat dianalisis. ', 12).'</article></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    private function mp4Bytes(): string
    {
        return "\x00\x00\x00\x18ftypisom".str_repeat("\0", 2036);
    }
}

class FakeHostResolver implements HostResolver
{
    /**
     * @param  array<string, list<string>>  $addresses
     */
    public function __construct(private readonly array $addresses) {}

    public function resolve(string $host): array
    {
        return $this->addresses[$host] ?? [];
    }
}
