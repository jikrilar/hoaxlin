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
use Illuminate\Http\Request;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\Component;
use Illuminate\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PublicNavigationTest extends TestCase
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
        $this->compiledPath = sys_get_temp_dir().'/hoaxlin-navigation-tests-'.bin2hex(random_bytes(6));
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

    #[DataProvider('roles')]
    public function test_account_routes_follow_server_state_and_logout_remains_post(string $role, bool $verified, bool $admin): void
    {
        if ($role !== 'guest') {
            $this->user = new User;
            $this->user->setRawAttributes(['name' => 'User <script id="attack">x</script>', 'is_admin' => $admin, 'email_verified_at' => $verified ? '2026-10-13 00:00:00' : null]);
        }
        $dom = $this->render('@include("partials.navigation")');
        $links = fn (string $path) => $dom->query('//a[@href="'.rtrim('http://localhost'.$path, '/').'"]')->length;

        $this->assertSame(1, $dom->query('//a[@aria-current="page" and @href="http://localhost"]')->length);
        $this->assertSame(0, $dom->query('//*[@role="menu" or @role="dialog"] | //script[@id="attack"]')->length);
        $this->assertSame(1, $dom->query('//nav[@id="public-navigation"]')->length);
        $this->assertSame(1, $dom->query('//button[@data-mobile-toggle and @hidden]')->length);
        $this->assertSame(0, $dom->query('//nav[@id="public-navigation"]/@hidden')->length);
        $this->assertSame($role === 'guest' ? 1 : 0, $links('/login'));
        $this->assertSame($role === 'guest' ? 1 : 0, $links('/register'));
        $this->assertSame($role === 'guest' ? 0 : 1, $links('/profil'));
        $this->assertSame($role !== 'guest' && $verified ? 1 : 0, $links('/riwayat'));
        $this->assertSame($role !== 'guest' && ! $verified ? 1 : 0, $links('/verify-email'));
        $this->assertSame($admin ? 1 : 0, $links('/admin'));
        $this->assertSame($role === 'guest' ? 0 : 1, $dom->query('//form[@action="http://localhost/logout" and @method="POST"]/input[@name="_token" and @value="fixture-token"]')->length);
        $this->assertSame(0, $links('/logout'));
    }

    public static function roles(): array
    {
        return [
            'guest' => ['guest', false, false],
            'unverified user' => ['user', false, false],
            'verified user' => ['user', true, false],
            'unverified admin' => ['admin', false, true],
            'verified admin' => ['admin', true, true],
        ];
    }

    #[DataProvider('activeRoutes')]
    public function test_active_links_follow_named_routes(string $path, string $target): void
    {
        $this->user = new User;
        $this->user->setRawAttributes(['name' => 'Fixture', 'is_admin' => false, 'email_verified_at' => '2026-10-13 00:00:00']);
        $request = Request::create('http://localhost'.$path);
        $request->setLaravelSession($this->session);
        $request->setRouteResolver(fn () => $this->app['router']->getRoutes()->match($request));
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
        $dom = $this->render('@include("partials.navigation")');
        $this->assertSame(1, $dom->query('//a[@aria-current="page"]')->length);
        $this->assertSame(rtrim('http://localhost'.$target, '/'), $dom->evaluate('string(//a[@aria-current="page"]/@href)'));
    }

    public static function activeRoutes(): array
    {
        return [
            'home' => ['/', '/'],
            'how it works' => ['/cara-kerja', '/cara-kerja'],
            'about' => ['/tentang', '/tentang'],
            'profile' => ['/profil', '/profil'],
            'history detail' => ['/riwayat/101', '/riwayat'],
        ];
    }

    public function test_footer_links_have_real_destinations(): void
    {
        $dom = $this->render('@include("partials.footer")');
        foreach (['/', '/cara-kerja', '/tentang', '/kebijakan-privasi', '/statistik'] as $path) {
            $this->assertSame(1, $dom->query('//a[@href="'.rtrim('http://localhost'.$path, '/').'"]')->length);
        }
        $this->assertSame(0, $dom->query('//a[starts-with(@href, "#")] | //*[@role="button"]')->length);
        $this->assertStringNotContainsString('Sistem aktif', $dom->document->textContent);
        $this->assertStringContainsString('bukan verifikasi kebenaran yang definitif', $dom->document->textContent);
    }

    public function test_simultaneous_flash_messages_are_escaped_and_have_distinct_roles(): void
    {
        foreach (['success', 'error', 'status'] as $key) {
            $this->session->put($key, 'Pesan <script id="attack">x</script> & teks');
        }
        $dom = $this->render('@include("partials.flash")');
        $this->assertSame(3, $dom->query('//*[@data-flash]')->length);
        $this->assertSame(1, $dom->query('//*[@data-flash and @role="alert"]')->length);
        $this->assertSame(2, $dom->query('//*[@data-flash and @role="status"]')->length);
        $this->assertSame(3, $dom->query('//button[@data-dismiss-flash and @type="button" and @aria-label]')->length);
        $this->assertSame(0, $dom->query('//script | //*[@onclick]')->length);
        $this->assertStringContainsString('<script id="attack">x</script> & teks', $dom->document->textContent);
    }

    private function render(string $template): DOMXPath
    {
        $html = Blade::render($template, deleteCachedView: true);
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
