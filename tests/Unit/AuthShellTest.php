<?php

namespace Tests\Unit;

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

class AuthShellTest extends TestCase
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
        $this->compiledPath = sys_get_temp_dir().'/hoaxlin-auth-shell-tests-'.bin2hex(random_bytes(6));
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

    #[DataProvider('pages')]
    public function test_auth_shell_preserves_native_form_contracts_and_never_restores_passwords(string $view, string $path, array $actions, array $autocompletes): void
    {
        $this->user = new User;
        $this->user->setRawAttributes(['email' => 'Email <script id="attack">x</script>']);
        $dom = $this->render($view, $path);

        $this->assertSame(1, $dom->query('//main[@id="main-content" and @tabindex="-1"]')->length);
        $this->assertSame(1, $dom->query('//a[@class="hl-skip-link" and @href="#main-content"]')->length);
        $this->assertSame(1, $dom->query('//header/a[@href="http://localhost" and contains(@class,"hl-wordmark")]')->length);
        $this->assertSame(0, $dom->query('//nav | //*[@class="hl-footer"] | //script[@id="attack"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="fixture-vite"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="fixture-livewire-style"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="fixture-livewire-script"]')->length);
        $this->assertSame(1, $dom->query('//meta[@name="csrf-token" and @content="fixture-token"]')->length);
        $this->assertSame(1, $dom->query('//main/*[@role="alert"]')->length);
        $this->assertStringContainsString('Email <script id="attack">x</script> salah.', $dom->evaluate('string(//main/*[@role="alert"])'));
        $this->assertStringNotContainsString('secret-marker', $dom->document->saveHTML());
        $this->assertSame(count($actions), $dom->query('//main//form')->length);
        foreach ($actions as $action) {
            $this->assertSame(1, $dom->query('//main//form[@method="POST" and @action="http://localhost'.$action.'" and @data-submit-label]/input[@name="_token" and @value="fixture-token"]')->length);
        }
        foreach ($autocompletes as $name => $autocomplete) {
            $this->assertSame($autocomplete, $dom->evaluate('string(//input[@name="'.$name.'"]/@autocomplete)'));
            $this->assertSame(1, $dom->query('//label[@for="'.$name.'"]')->length);
            $this->assertSame(1, $dom->query('//input[@name="'.$name.'"]/@required')->length);
        }
        $passwords = $dom->query('//input[@type="password"]');
        $this->assertSame($passwords->length, $dom->query('//button[@data-password-toggle and @hidden and @type="button"]')->length);
        foreach ($passwords as $password) {
            $this->assertSame('', $password->getAttribute('value'));
            $this->assertSame(1, $dom->query('//button[@aria-controls="'.$password->getAttribute('id').'"]')->length);
        }
        if ($view === 'auth.reset-password') {
            $this->assertSame('broker-token', $dom->evaluate('string(//input[@name="token"]/@value)'));
        }
        if ($view !== 'auth.verify-email') {
            $this->assertSame('email"@example.test', $dom->evaluate('string(//input[@name="email"]/@value)'));
        }
    }

    public static function pages(): array
    {
        return [
            'login' => ['auth.login', '/login', ['/login'], ['email' => 'email', 'password' => 'current-password']],
            'register' => ['auth.register', '/register', ['/register'], ['name' => 'name', 'email' => 'email', 'password' => 'new-password', 'password_confirmation' => 'new-password']],
            'forgot' => ['auth.forgot-password', '/forgot-password', ['/forgot-password'], ['email' => 'email']],
            'reset' => ['auth.reset-password', '/reset-password/broker-token', ['/reset-password'], ['email' => 'email', 'password' => 'new-password', 'password_confirmation' => 'new-password']],
            'verify' => ['auth.verify-email', '/verify-email', ['/email/verification-notification', '/logout'], []],
        ];
    }

    private function render(string $view, string $path): DOMXPath
    {
        $request = Request::create('http://localhost'.$path);
        $request->setLaravelSession($this->session);
        $request->setRouteResolver(fn () => $this->app['router']->getRoutes()->match($request));
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
        $html = $this->app['view']->make($view, ['request' => $request])->render();
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
