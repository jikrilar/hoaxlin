<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_MESSAGE = 'PRIVATE_ERROR_INPUT sk-fixture-secret /private/internal.php <script id="attack">literal</script>';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    #[DataProvider('errorCodes')]
    public function test_html_errors_keep_their_status_and_headers_with_safe_native_recovery(int $code, string $title): void
    {
        Route::get('/_fixture/error', fn () => throw new HttpException($code, self::PRIVATE_MESSAGE, headers: ['Retry-After' => '37', 'X-Fixture' => 'preserved']));
        $response = $this->get('/_fixture/error')->assertStatus($code)->assertHeader('X-Fixture', 'preserved')
            ->assertHeader('Retry-After', '37')->assertSee($title)->assertSee('Kode HTTP '.$code)
            ->assertDontSee('PRIVATE_ERROR_INPUT')->assertDontSee('sk-fixture-secret')->assertDontSee('/private/internal.php')->assertDontSee('<script id="attack">', false);
        $dom = $this->dom($response->getContent());
        $this->assertSame(1, $dom->query('//main')->length);
        $this->assertSame(1, $dom->query('//h1')->length);
        $this->assertSame($title, $dom->evaluate('string(//h1)'));
        $this->assertSame(0, $dom->query('//script | //meta[@http-equiv="refresh"] | //meta[@name="csrf-token"]')->length);
        $this->assertSame('noindex, nofollow', $dom->evaluate('string(//meta[@name="robots"]/@content)'));
        $this->assertSame(1, $dom->query('//a[@href="#main-content"]')->length);
        $this->assertSame(1, $dom->query('//link[@rel="stylesheet"]')->length);
        $this->assertSame(1, $dom->query('//main//a[@href="'.($code === 419 ? '/#cek-berita' : '/').'"]')->length);
        if (in_array($code, [429, 503], true)) {
            $response->assertSee('Server meminta jeda 37 detik');
        } else {
            $response->assertDontSee('Server meminta jeda');
        }
    }

    #[DataProvider('errorCodes')]
    public function test_accept_json_errors_remain_framework_json_instead_of_html(int $code, string $title): void
    {
        Route::get('/_fixture/error-json', fn () => throw new HttpException($code, 'Existing HTTP message', headers: ['Retry-After' => '37']));
        $this->getJson('/_fixture/error-json')->assertStatus($code)->assertHeader('Retry-After', '37')
            ->assertExactJson(['message' => 'Existing HTTP message'])->assertDontSee('<!DOCTYPE html>', false);
    }

    public static function errorCodes(): array
    {
        return [
            [403, 'Akses tidak diizinkan'], [404, 'Halaman atau hasil tidak ditemukan'],
            [419, 'Sesi atau form kedaluwarsa'], [429, 'Terlalu banyak permintaan'],
            [500, 'Layanan mengalami kendala'], [503, 'Layanan belum tersedia'],
        ];
    }

    #[DataProvider('unsafeRetryHeaders')]
    public function test_retry_copy_never_prints_unvalidated_header_content(string $header): void
    {
        Route::get('/_fixture/retry', fn () => throw new HttpException(429, 'Safe message', headers: ['Retry-After' => $header]));
        $this->get('/_fixture/retry')->assertStatus(429)->assertDontSee('Server meminta jeda')->assertDontSee('PRIVATE_RETRY_MARKER');
    }

    public static function unsafeRetryHeaders(): array
    {
        return [['0'], ['-1'], ['PRIVATE_RETRY_MARKER<script>'], ['Wed, 21 Oct 2032 07:28:00 GMT']];
    }

    public function test_generic_server_exception_is_safe_in_html_and_json_when_debug_is_off(): void
    {
        Route::get('/_fixture/server-error', fn () => throw new RuntimeException(self::PRIVATE_MESSAGE));
        $this->get('/_fixture/server-error')->assertStatus(500)->assertSee('Layanan mengalami kendala')->assertDontSee('PRIVATE_ERROR_INPUT');
        $this->getJson('/_fixture/server-error')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    }

    public function test_error_rendering_survives_a_missing_manifest_without_auth_session_database_or_ai(): void
    {
        $this->app->make(Vite::class)->useBuildDirectory('missing-error-build');
        // Laravel constructs its redirector with a session store when booting the
        // response factory. The error view itself must not resolve or read it.
        $this->app->make(ResponseFactory::class);
        $this->app->bind('auth', fn () => throw new RuntimeException('Auth unavailable'));
        $this->app->bind('session.store', fn () => throw new RuntimeException('Session unavailable'));
        Http::preventStrayRequests();
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $response = $this->app->make(ExceptionHandler::class)->render(Request::create('/_fixture/render'), new HttpException(500, self::PRIVATE_MESSAGE));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Layanan mengalami kendala', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE_ERROR_INPUT', $response->getContent());
        $this->assertSame(0, $this->dom($response->getContent())->query('//link[@rel="stylesheet"]')->length);
        $this->assertSame([], DB::connection()->getQueryLog());
        Http::assertNothingSent();
    }

    public function test_validation_keeps_the_html_error_bag_and_json_field_errors(): void
    {
        Route::middleware('web')->post('/_fixture/validate', function (Request $request) {
            $request->validate(['raw_input' => 'required|string|min:50']);
        });
        $this->from(route('home'))->post('/_fixture/validate', ['raw_input' => 'Teks pendek'])
            ->assertRedirect(route('home'))->assertSessionHasErrors('raw_input')->assertSessionHas('_old_input.raw_input', 'Teks pendek');
        $this->postJson('/_fixture/validate', ['raw_input' => 'Teks pendek'])->assertUnprocessable()
            ->assertJsonValidationErrors('raw_input')->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_real_csrf_rejection_uses_419_without_echoing_posted_secrets(): void
    {
        Route::middleware(['web', EnforcedCsrfForErrorPageTest::class])->post('/_fixture/csrf', fn () => 'Should not execute');
        $this->post('/_fixture/csrf', ['password' => 'PRIVATE_ERROR_INPUT', 'raw_input' => self::PRIVATE_MESSAGE])
            ->assertStatus(419)->assertSee('Sesi atau form kedaluwarsa')->assertSee('File perlu dipilih ulang')
            ->assertDontSee('PRIVATE_ERROR_INPUT')->assertDontSee('Should not execute');
    }

    public function test_real_throttle_keeps_retry_after_and_the_existing_json_contract(): void
    {
        Route::middleware('throttle:1,1')->get('/_fixture/throttle', fn () => 'First request');
        $this->get('/_fixture/throttle')->assertOk();
        $response = $this->get('/_fixture/throttle')->assertStatus(429);
        $seconds = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $seconds);
        $response->assertSee('Server meminta jeda '.$seconds.' detik');
        $this->getJson('/_fixture/throttle')->assertStatus(429)->assertJsonPath('message', 'Too Many Attempts.');
    }

    public function test_status_transport_error_does_not_change_a_processing_submission_into_failed(): void
    {
        $user = User::factory()->create();
        $submission = Submission::factory()->for($user)->create(['status' => 'processing']);
        Route::getRoutes()->getByName('hasil.status')->middleware(FailedTransportForErrorPageTest::class);
        $this->actingAs($user)->getJson(route('hasil.status', $submission))->assertStatus(503)
            ->assertExactJson(['message' => 'Fixture transport unavailable']);
        $this->assertSame('processing', $submission->fresh()->status);
    }

    private function dom(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}

class EnforcedCsrfForErrorPageTest extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

class FailedTransportForErrorPageTest
{
    public function handle(Request $request, \Closure $next)
    {
        throw new HttpException(503, 'Fixture transport unavailable');
    }
}
