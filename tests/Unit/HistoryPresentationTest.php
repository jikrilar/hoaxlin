<?php

namespace Tests\Unit;

use App\Http\Requests\HistoryRequest;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\RequestGuard;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Component;
use Illuminate\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class HistoryPresentationTest extends TestCase
{
    private string $compiledPath;

    private Container $previousContainer;

    private ?Container $previousFacadeApplication;

    private ?ConnectionResolverInterface $previousResolver;

    private ?Dispatcher $previousDispatcher;

    private array $previousBootedModels;

    private Store $session;

    private ?User $user = null;

    private Application $app;

    private LengthAwarePaginator $submissions;

    private array $stats = ['valid' => 13, 'hoax' => 4, 'meragukan' => 2];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $this->previousBootedModels = (new ReflectionProperty(Model::class, 'booted'))->getValue();
        $this->compiledPath = sys_get_temp_dir().'/hoaxlin-history-tests-'.bin2hex(random_bytes(6));
        mkdir($this->compiledPath);
        $root = dirname(__DIR__, 2);
        $this->app = new Application($root);
        $this->app->instance('config', new Repository([
            'app' => ['url' => 'http://localhost', 'locale' => 'id'],
            'view' => ['paths' => [$root.'/resources/views'], 'compiled' => $this->compiledPath],
            'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]],
            'auth' => ['defaults' => ['guard' => 'fixture'], 'guards' => ['fixture' => ['driver' => 'fixture']]],
        ]));
        Facade::clearResolvedInstances();
        Component::flushCache();
        Component::forgetFactory();
        Facade::setFacadeApplication($this->app);
        foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, ViewServiceProvider::class, RoutingServiceProvider::class, TranslationServiceProvider::class, DatabaseServiceProvider::class] as $provider) {
            $this->app->register($provider);
        }
        $request = Request::create('http://localhost/');
        $this->app->instance('request', $request);
        $this->session = new Store('navigation-fixture', new ArraySessionHandler(120));
        $this->session->put('_token', 'fixture-token');
        $this->session->put('_old_input', ['name' => 'Nama <script id="attack">x</script>', 'email' => 'email"@example.test', 'password' => 'secret-marker', 'password_confirmation' => 'secret-marker']);
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['email' => ['Email <script id="attack">x</script> salah.'], 'password' => ['Kata sandi salah.']]));
        $this->app['view']->share('errors', $errors);
        $vite = $this->createMock(Vite::class);
        $vite->method('__invoke')->willReturn(new HtmlString('<link id="fixture-vite" href="/fixture.css" rel="stylesheet">'));
        $this->app->instance(Vite::class, $vite);
        $this->app['blade.compiler']->directive('livewireStyles', fn () => '<style id="fixture-livewire-style"></style>');
        $this->app['blade.compiler']->directive('livewireScripts', fn () => '<script id="fixture-livewire-script"></script>');
        $this->app->instance('session', $this->session);
        $request->setLaravelSession($this->session);
        $auth = new AuthManager($this->app);
        $auth->extend('fixture', fn () => new RequestGuard(fn () => $this->user, $request));
        $this->app->instance('auth', $auth);
        require $root.'/routes/web.php';
        // Native Filament panel route, registered only in this isolated renderer.
        $this->app['router']->get('/admin', fn () => '')->name('filament.admin.pages.dashboard');
        $this->app['router']->getRoutes()->refreshNameLookups();
        $request->setRouteResolver(fn () => $this->app['router']->getRoutes()->match($request));
        $this->app->boot();
        $this->user = new User;
        $this->user->setRawAttributes(['id' => 17, 'name' => 'Pengguna', 'email' => 'fixture@example.test', 'email_verified_at' => '2026-10-13 00:00:00']);
        $this->session->forget('_old_input');
        $this->app['view']->share('errors', new ViewErrorBag);
        $this->submissions = new LengthAwarePaginator([], 0, 15, 1, ['path' => 'http://localhost/riwayat']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->compiledPath);
        Facade::clearResolvedInstances();
        Component::flushCache();
        Component::forgetFactory();
        (new ReflectionProperty(Model::class, 'booted'))->setValue(null, $this->previousBootedModels);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        if ($this->previousDispatcher) {
            Model::setEventDispatcher($this->previousDispatcher);
        } else {
            Model::unsetEventDispatcher();
        }
        parent::tearDown();
    }

    public function test_get_filters_keep_all_five_values_while_resetting_page_and_csv_has_no_filter(): void
    {
        $this->submissions = new LengthAwarePaginator(array_fill(0, 15, $this->submission()), 31, 15, 2, ['path' => 'http://localhost/riwayat']);
        $query = ['search' => 'Informasi " & <script id="attack">', 'label' => 'valid', 'input_type' => 'text', 'status' => 'completed', 'sort' => 'confidence'];
        $dom = $this->render('riwayat', '/riwayat?'.http_build_query($query + ['page' => 2]));
        $this->assertSame(1, $dom->query('//form[@id="history-filter-form" and @method="GET" and @action="http://localhost/riwayat"]')->length);
        $this->assertSame(5, $dom->query('//form[@id="history-filter-form"]//*[@name]')->length);
        $this->assertSame(0, $dom->query('//main//input[@name="page" or @name="_token"] | //*[@id="attack"] | //main//*[@style or @onclick]')->length);
        $this->assertSame($query['search'], $dom->evaluate('string(//input[@name="search"]/@value)'));
        foreach (array_diff_key($query, ['search' => true]) as $name => $value) {
            $this->assertSame($value, $dom->evaluate('string(//select[@name="'.$name.'"]/option[@selected]/@value)'));
        }
        $this->assertSame(0, $dom->query('//select[@name="input_type"]/option[@value="video_url"]')->length);
        $this->assertSame(1, $dom->query('//main//a[@href="http://localhost/riwayat-export/csv"]')->length);
        $this->assertStringContainsString('seluruh riwayat akun', $dom->evaluate('string(//main/header | //main//header)'));
        $this->assertStringContainsString('Angka ini tidak mengikuti filter atau halaman aktif', $dom->evaluate('string(//section[@aria-labelledby="history-summary-title"])'));
        $this->assertSame(15, $dom->query('//ul[@id="history-list"]/li')->length);
        $this->assertStringContainsString('Menampilkan 16–30 dari 31', $dom->evaluate('string(//main)'));
        foreach (['prev' => '1', 'next' => '3'] as $rel => $page) {
            $url = $dom->evaluate('string(//nav[@aria-label="Navigasi halaman riwayat"]//a[@rel="'.$rel.'"]/@href)');
            parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
            $this->assertEquals($query + ['page' => $page], $parameters);
        }
    }

    #[DataProvider('paginationEdges')]
    public function test_pagination_edges_use_real_links_and_noninteractive_disabled_text(int $page, string $available, string $disabled): void
    {
        $this->submissions = new LengthAwarePaginator([$this->submission()], 16, 15, $page, ['path' => 'http://localhost/riwayat']);
        $dom = $this->render('riwayat', '/riwayat?page='.$page);
        $this->assertSame(1, $dom->query('//nav[@aria-label="Navigasi halaman riwayat"]//a[@rel="'.$available.'"]')->length);
        $this->assertSame(0, $dom->query('//nav[@aria-label="Navigasi halaman riwayat"]//a[@rel="'.$disabled.'"]')->length);
        $this->assertSame(1, $dom->query('//nav[@aria-label="Navigasi halaman riwayat"]//span[@aria-disabled="true" and not(@tabindex) and not(@href)]')->length);
    }

    public static function paginationEdges(): array
    {
        return [[1, 'next', 'prev'], [2, 'prev', 'next']];
    }

    #[DataProvider('rowStates')]
    public function test_only_final_results_for_the_same_submission_have_label_and_confidence(string $status, ?string $label, ?float $score, int $resultSubmissionId, string $expectedLabel, string $expectedScore): void
    {
        $submission = $this->submission($status, $label, $score, $resultSubmissionId);
        $this->submissions = new LengthAwarePaginator([$submission], 1, 15, 1, ['path' => 'http://localhost/riwayat']);
        $dom = $this->render('riwayat', '/riwayat');
        $this->assertSame($expectedLabel, $dom->evaluate('string(//*[@data-history-label])'));
        $this->assertSame($expectedScore, $dom->evaluate('string(//*[@data-history-confidence])'));
        $this->assertSame(1, $dom->query('//a[@href="http://localhost/riwayat/101"]')->length);
        $this->assertSame(0, $dom->query('//nav[@aria-label="Navigasi halaman riwayat"]')->length);
        $this->assertStringContainsString('bukan persentase kebenaran berita', $dom->evaluate('string(//main)'));
    }

    public static function rowStates(): array
    {
        return [
            ['pending', 'valid', 0.99, 101, 'Belum tersedia', 'Belum tersedia'],
            ['processing', 'hoax', 0.99, 101, 'Belum tersedia', 'Belum tersedia'],
            ['failed', 'valid', 0.99, 101, 'Belum tersedia', 'Belum tersedia'],
            ['unexpected', 'valid', 0.99, 101, 'Belum tersedia', 'Belum tersedia'],
            ['completed', null, null, 101, 'Belum tersedia', 'Belum tersedia'],
            ['completed', 'valid', 0.99, 102, 'Belum tersedia', 'Belum tersedia'],
            ['completed', 'valid', 0.91, 101, 'Prediksi model: Valid', '91%'],
            ['completed', 'hoax', null, 101, 'Prediksi model: Hoaks', 'Belum tersedia'],
            ['completed', 'meragukan', 0.0, 101, 'Informasi belum terverifikasi oleh sumber terpercaya', '0%'],
            ['completed', 'valid', 1.1, 101, 'Prediksi model: Valid', 'Belum tersedia'],
        ];
    }

    #[DataProvider('emptyStates')]
    public function test_initial_filtered_and_out_of_range_empty_states_have_the_correct_recovery(string $query, int $total, int $page, string $heading, string $href): void
    {
        $this->submissions = new LengthAwarePaginator([], $total, 15, $page, ['path' => 'http://localhost/riwayat']);
        $dom = $this->render('riwayat', '/riwayat'.$query);
        $this->assertStringContainsString($heading, $dom->evaluate('string(//section[@aria-labelledby="history-results-title"])'));
        $this->assertSame(1, $dom->query('//*[contains(@class,"hl-empty-state")]//a[@href="'.$href.'"]')->length);
        $this->assertSame(1, $dom->query('//section[@aria-labelledby="history-summary-title"]')->length);
        $this->assertSame('13', $dom->evaluate('string(//section[@aria-labelledby="history-summary-title"]//dd[1])'));
        $this->assertSame(0, $dom->query('//nav[@aria-label="Navigasi halaman riwayat"]')->length);
    }

    public static function emptyStates(): array
    {
        return [
            ['', 0, 1, 'Belum ada riwayat pemeriksaan', 'http://localhost#cek-berita'],
            ['?search=missing', 0, 1, 'Tidak ada pemeriksaan yang sesuai filter', 'http://localhost/riwayat'],
            ['?sort=oldest', 0, 1, 'Belum ada riwayat pemeriksaan', 'http://localhost#cek-berita'],
            ['?label=valid&page=99', 31, 99, 'Halaman riwayat kosong', 'http://localhost/riwayat?label=valid'],
        ];
    }

    public function test_filter_error_focus_field_connections_and_safe_recovery_use_old_input(): void
    {
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['search' => ['Pencarian <script id="attack">error</script>'], 'label' => ['Label tidak tersedia.'], 'input_type' => ['Jenis tidak tersedia.'], 'status' => ['Status tidak tersedia.'], 'sort' => ['Urutan tidak tersedia.']]));
        $this->app['view']->share('errors', $errors);
        $this->session->put('_old_input', ['search' => 'Old <script id="attack">literal</script>', 'label' => 'hoax', 'input_type' => 'image', 'status' => 'failed', 'sort' => 'oldest']);
        $dom = $this->render('riwayat', '/riwayat?label=valid');
        $this->assertSame(1, $dom->query('//*[@id="history-errors" and @role="alert" and @autofocus and @tabindex="-1"]')->length);
        $this->assertSame(5, $dom->query('//*[@id="history-errors"]//a')->length);
        foreach (['search', 'label', 'input-type', 'status', 'sort'] as $field) {
            $id = 'history-'.$field;
            $this->assertSame(1, $dom->query('//*[@id="'.$id.'" and @aria-invalid="true"]')->length);
            $this->assertSame(1, $dom->query('//*[@id="'.$id.'-error"]')->length);
        }
        $this->assertSame('Old <script id="attack">literal</script>', $dom->evaluate('string(//input[@name="search"]/@value)'));
        $this->assertSame('hoax', $dom->evaluate('string(//select[@name="label"]/option[@selected]/@value)'));
        $this->assertSame(0, $dom->query('//*[@id="attack"]')->length);
    }

    #[DataProvider('inputKinds')]
    public function test_media_kinds_and_escaped_previews_do_not_promote_video_url_to_an_available_filter(string $kind, string $expected): void
    {
        $submission = $this->submission();
        $submission->input_type = $kind;
        $submission->raw_input = '';
        $submission->source_url = 'https://example.test/<script id="attack">literal</script>';
        $this->submissions = new LengthAwarePaginator([$submission], 1, 15, 1, ['path' => 'http://localhost/riwayat']);
        $dom = $this->render('riwayat', '/riwayat');
        $this->assertStringContainsString($expected, $dom->evaluate('string(//*[@id="history-list"])'));
        $this->assertStringContainsString($submission->source_url, $dom->evaluate('string(//*[@id="history-list"]/li/h3)'));
        $this->assertSame(0, $dom->query('//*[@id="attack"] | //option[@value="video_url"] | //a[contains(@href,"example.test")]')->length);
    }

    public static function inputKinds(): array
    {
        return [['text', 'Teks'], ['image', 'Gambar'], ['video', 'Video'], ['video_url', 'Tautan video'], ['url', 'Tautan artikel']];
    }

    public function test_csv_link_is_not_offered_to_an_unverified_viewer(): void
    {
        $this->user->email_verified_at = null;
        $dom = $this->render('riwayat', '/riwayat');
        $this->assertSame(0, $dom->query('//main//a[@href="http://localhost/riwayat-export/csv"]')->length);
        foreach (['riwayat', 'riwayat.show', 'riwayat.csv'] as $name) {
            $middleware = $this->app['router']->getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('verified', $middleware);
        }
    }

    #[DataProvider('invalidFilters')]
    public function test_actual_history_request_rejects_invalid_filters_without_coercing_arrays(array $payload, string $field): void
    {
        $request = $this->filterRequest($payload);
        try {
            $request->validateResolved();
            $this->fail('Invalid history filter must remain a server validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public static function invalidFilters(): array
    {
        return [[['search' => str_repeat('a', 101)], 'search'], [['search' => ['bad']], 'search'], [['label' => 'unknown'], 'label'], [['input_type' => 'video_url'], 'input_type'], [['status' => 'unknown'], 'status'], [['sort' => 'unknown'], 'sort']];
    }

    public function test_actual_history_request_trims_search_preserves_the_limit_and_defaults_sort(): void
    {
        $request = $this->filterRequest(['search' => '  '.str_repeat('a', 100).'  ', 'page' => 2]);
        $request->validateResolved();
        $this->assertSame(['search' => str_repeat('a', 100), 'sort' => 'newest'], $request->validated());
    }

    private function filterRequest(array $payload): HistoryRequest
    {
        $this->app->instance('validator', new ValidationFactory($this->app['translator'], $this->app));
        $request = HistoryRequest::create('http://localhost/riwayat', 'GET', $payload);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $this->app['url']->setRequest($request);

        return $request;
    }

    private function submission(string $status = 'pending', ?string $label = null, ?float $score = null, int $resultId = 101): Submission
    {
        $submission = new Submission;
        $submission->forceFill(['id' => 101, 'user_id' => 17, 'input_type' => 'text', 'raw_input' => 'Informasi fixture', 'status' => $status, 'created_at' => '2026-10-13 10:00:00']);
        $result = $label === null ? null : new DetectionResult(['submission_id' => $resultId, 'label' => $label, 'confidence_score' => $score]);
        $submission->setRelation('detectionResult', $result);

        return $submission;
    }

    private function render(string $view, string $path): DOMXPath
    {
        $request = Request::create('http://localhost'.$path);
        $request->setLaravelSession($this->session);
        $request->setRouteResolver(fn () => $this->app['router']->getRoutes()->match($request));
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
        $this->submissions->appends($request->query());
        $html = $this->app['view']->make($view, ['request' => $request, 'submissions' => $this->submissions, 'stats' => $this->stats])->render();
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
