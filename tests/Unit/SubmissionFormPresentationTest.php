<?php

namespace Tests\Unit;

use App\Http\Requests\StoreSubmissionRequest;
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
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\Component;
use Illuminate\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class SubmissionFormPresentationTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $this->previousBootedModels = (new ReflectionProperty(Model::class, 'booted'))->getValue();
        $this->compiledPath = sys_get_temp_dir().'/hoaxlin-submission-view-tests-'.bin2hex(random_bytes(6));
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
        $this->app['view']->share('errors', new ViewErrorBag);
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

    public function test_guest_text_form_matches_server_limits_and_security_contract(): void
    {
        $dom = $this->render();
        $request = StoreSubmissionRequest::create('/', 'POST', ['input_type' => 'text']);
        $rules = $request->rules()['raw_input'];
        $this->assertContains('min:'.$dom->evaluate('string(//textarea/@minlength)'), $rules);
        $this->assertContains('max:'.$dom->evaluate('string(//textarea/@maxlength)'), $rules);
        $this->assertSame('POST', $dom->evaluate('string(//form[@id="form-teks"]/@method)'));
        $this->assertSame('http://localhost/deteksi', $dom->evaluate('string(//form[@id="form-teks"]/@action)'));
        $this->assertSame('text', $dom->evaluate('string(//form[@id="form-teks"]/input[@name="input_type"]/@value)'));
        $this->assertSame('fixture-token', $dom->evaluate('string(//form[@id="form-teks"]/input[@name="_token"]/@value)'));
        $this->assertSame(1, $dom->query('//form[@id="form-teks"]//textarea[@name="raw_input" and @required]')->length);
        $this->assertSame(1, $dom->query('//input[@name="captcha_answer" and @required and @autocomplete="off"]')->length);
        $this->assertSame(1, $dom->query('//input[@name="website" and @tabindex="-1" and @aria-hidden="true"]')->length);
        $this->assertSame(0, $dom->query('//input[@name="source_url" or @name="media_file"]')->length);
        $this->assertSame(0, $dom->query('//*[@role="tab" or @role="tabpanel"]')->length);
        $this->assertSame(1, $dom->query('//button[@type="submit" and @id="submit-teks"]')->length);
        $this->assertSame(0, $dom->query('//*[@onsubmit or @onclick or @onchange] | //*[@id="loading-overlay"]')->length);
        $this->assertStringContainsString('bukan verifikasi kebenaran yang definitif', $dom->evaluate('string(//meta[@name="description"]/@content)'));
        $this->assertSame(1, $dom->query('//*[@id="cara-kerja"]/preceding::*[@id="form-teks"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="submission-model"]/preceding::*[@id="form-teks"]')->length);
    }

    #[DataProvider('oldText')]
    public function test_old_text_is_escaped_and_captcha_and_honeypot_are_never_restored(mixed $old, string $expected): void
    {
        $this->session->put('_old_input', ['input_type' => 'text', 'raw_input' => $old, 'captcha_answer' => 999, 'website' => 'spam-marker']);
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['raw_input' => ['Teks <script id="attack">x</script> salah.'], 'captcha_answer' => ['Jawaban CAPTCHA salah.']]));
        $this->app['view']->share('errors', $errors);
        $dom = $this->render();

        $this->assertSame($expected, $dom->evaluate('string(//textarea[@name="raw_input"])'));
        $this->assertSame(0, $dom->query('//script[@id="attack"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="submission-errors" and @role="alert" and @autofocus]')->length);
        $this->assertSame(1, $dom->query('//*[@id="submission-errors"]//a[@href="#teks-input"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="submission-errors"]//a[@href="#captcha-teks"]')->length);
        $this->assertSame('true', $dom->evaluate('string(//textarea/@aria-invalid)'));
        $this->assertStringContainsString('teks-input-error', $dom->evaluate('string(//textarea/@aria-describedby)'));
        $this->assertSame('captcha-teks-hint captcha-teks-error', $dom->evaluate('string(//input[@name="captcha_answer"]/@aria-describedby)'));
        $this->assertSame(0, $dom->query('//input[@name="captcha_answer" or @name="website"]/@value')->length);
        $this->assertStringNotContainsString('spam-marker', $dom->document->saveHTML());
    }

    public static function oldText(): array
    {
        return ['text' => ['</textarea><script id="attack">x</script>& teks', '</textarea><script id="attack">x</script>& teks'], 'array' => [['invalid'], ''], 'long' => [str_repeat('a', 50001), str_repeat('a', 50001)]];
    }

    public function test_authenticated_media_forms_and_restored_tab_keep_existing_domain_contracts(): void
    {
        $this->user = new User;
        $this->session->put('_old_input', ['input_type' => 'video_url', 'source_url' => 'https://example.test/video.mp4']);
        $dom = $this->render();
        $this->assertSame(4, $dom->query('//*[@data-input-tab]')->length);
        $this->assertSame('true', $dom->evaluate('string(//*[@id="tab-video"]/@aria-selected)'));
        $this->assertSame('tab-content active', $dom->evaluate('string(//*[@id="panel-video"]/@class)'));
        $this->assertSame('video_url', $dom->evaluate('string(//*[@id="video-input-type"]/@value)'));
        $this->assertSame('https://example.test/video.mp4', $dom->evaluate('string(//*[@id="video-url-input"]/@value)'));
        $this->assertSame(2, $dom->query('//form[@enctype="multipart/form-data"]')->length);
        $this->assertSame(4, $dom->query('//main//form[@method="POST" and @action="http://localhost/deteksi" and @data-submit-label]/input[@name="_token"]')->length);
    }

    private function render(): DOMXPath
    {
        $html = $this->app['view']->make('welcome', ['captchaQuestion' => '2 + 3', 'modelStatistics' => [['label' => 'Model', 'value' => 'fixture', 'description' => 'Data sintetis']], 'videoMaxBytes' => 1024, 'videoMaxLabel' => '1 KiB', 'blockedVideoHosts' => ['youtube.com']])->render();
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
