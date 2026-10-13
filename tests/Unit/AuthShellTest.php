<?php

namespace Tests\Unit;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
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
use Illuminate\Validation\Factory as ValidationFactory;
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

    #[DataProvider('accountPages')]
    public function test_login_and_registration_have_native_fields_honest_copy_and_real_links(string $view, string $path, string $heading): void
    {
        $this->app['view']->share('errors', new ViewErrorBag);
        $dom = $this->render($view, $path);
        $this->assertSame($heading, $dom->evaluate('string(//h1)'));
        $this->assertSame(0, $dom->query('//main//*[@style or @onclick] | //form[@novalidate or @data-password-hints]')->length);
        $this->assertSame(1, $dom->query('//input[@autofocus]')->length);
        $this->assertSame(0, $dom->query('//input[@name="password"]/@minlength')->length);
        $this->assertSame(0, $dom->query('//*[@id="password-strength" or @id="confirm-match"]')->length);
        $this->assertSame(1, $dom->query('//main//button[@type="submit" and contains(@class,"hl-button")]')->length);
        if ($view === 'auth.login') {
            $this->assertSame('1', $dom->evaluate('string(//input[@name="remember"]/@value)'));
            $this->assertSame(1, $dom->query('//main//a[@href="http://localhost/register"]')->length);
            $this->assertSame(1, $dom->query('//main//a[@href="http://localhost/forgot-password"]')->length);
            $this->assertStringNotContainsString('30 hari', $dom->evaluate('string(//main)'));
        } else {
            $this->assertSame('255', $dom->evaluate('string(//input[@name="name"]/@maxlength)'));
            $this->assertSame('255', $dom->evaluate('string(//input[@name="email"]/@maxlength)'));
            $this->assertSame('1', $dom->evaluate('string(//input[@name="terms"]/@value)'));
            $this->assertSame(1, $dom->query('//input[@name="terms" and @required]')->length);
            $this->assertSame(1, $dom->query('//main//a[@href="http://localhost/login"]')->length);
            $this->assertSame(1, $dom->query('//label[@for="terms"]//a[@href="http://localhost/kebijakan-privasi"]')->length);
            $this->assertStringContainsString('verifikasi email', $dom->evaluate('string(//main)'));
            $this->assertStringContainsString('bukan verifikasi kebenaran', $dom->evaluate('string(//label[@for="terms"])'));
        }
    }

    public static function accountPages(): array
    {
        return [['auth.login', '/login', 'Masuk'], ['auth.register', '/register', 'Buat akun']];
    }

    #[DataProvider('rememberValues')]
    public function test_remember_recovery_only_checks_valid_boolean_values(mixed $value, bool $checked): void
    {
        $this->session->put('_old_input', ['email' => 'fixture@example.test', 'remember' => $value]);
        $dom = $this->render('auth.login', '/login');
        $this->assertSame($checked ? 1 : 0, $dom->query('//input[@name="remember" and @checked]')->length);
        $this->assertSame('1', $dom->evaluate('string(//input[@name="remember"]/@value)'));
    }

    public static function rememberValues(): array
    {
        return [[true, true], [1, true], ['1', true], [false, false], [0, false], ['0', false], ['on', false], [['1'], false], [null, false]];
    }

    public function test_registration_errors_link_to_fields_and_restore_only_safe_non_secret_values(): void
    {
        $this->session->put('_old_input', ['name' => ['bad'], 'email' => ['bad'], 'terms' => '1', 'password' => 'secret-marker', 'password_confirmation' => 'secret-marker']);
        $errors = new ViewErrorBag;
        $messages = [];
        foreach (['name', 'email', 'password', 'password_confirmation', 'terms', 'unknown'] as $field) {
            $messages[$field] = ['Error '.$field.' <script id="attack">literal</script>'];
        }
        $errors->put('default', new MessageBag($messages));
        $this->app['view']->share('errors', $errors);
        $dom = $this->render('auth.register', '/register');
        $this->assertSame(1, $dom->query('//*[@id="auth-errors" and @role="alert" and @tabindex="-1" and @autofocus]')->length);
        $this->assertSame(0, $dom->query('//input[@autofocus] | //*[@id="attack"]')->length);
        $this->assertSame(5, $dom->query('//*[@id="auth-errors"]//a')->length);
        $this->assertSame(1, $dom->query('//*[@id="auth-errors"]//a[@href="#password_confirmation"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="auth-errors"]//a[@href="#terms"]')->length);
        $this->assertSame('terms-error', $dom->evaluate('string(//input[@name="terms"]/@aria-describedby)'));
        $this->assertSame(1, $dom->query('//input[@name="terms" and @checked]')->length);
        $this->assertSame('', $dom->evaluate('string(//input[@name="name"]/@value)'));
        $this->assertSame('', $dom->evaluate('string(//input[@name="email"]/@value)'));
        $this->assertStringNotContainsString('secret-marker', $dom->document->saveHTML());
    }

    public function test_server_credential_and_throttle_messages_remain_literal_and_linked(): void
    {
        $message = 'Terlalu banyak percobaan masuk. Coba lagi dalam 37 detik.';
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['email' => [$message], 'remember' => ['Pilihan harus boolean.']]));
        $this->app['view']->share('errors', $errors);
        $dom = $this->render('auth.login', '/login');
        $this->assertStringContainsString($message, $dom->evaluate('string(//*[@id="auth-errors"])'));
        $this->assertSame(1, $dom->query('//*[@id="auth-errors"]//a[@href="#email"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="auth-errors"]//a[@href="#remember_me"]')->length);
        $this->assertSame('remember-error', $dom->evaluate('string(//input[@name="remember"]/@aria-describedby)'));
        $this->assertSame(0, $dom->query('//input[@autofocus]')->length);
    }

    #[DataProvider('accountFieldPayloads')]
    public function test_native_account_fields_follow_existing_request_rules_without_database_or_authentication(string $type, array $payload, bool $valid): void
    {
        $rules = $type === 'login' ? (new LoginRequest)->rules() : (new RegisterRequest)->rules();
        if ($type === 'register') {
            $rules['email'] = array_values(array_filter($rules['email'], fn ($rule) => ! str_starts_with($rule, 'unique:')));
        }
        $factory = new ValidationFactory($this->app['translator'], $this->app);
        $this->app->instance('validator', $factory);
        $validator = $factory->make($payload, $rules);
        $this->assertSame($valid, $validator->passes());
    }

    public static function accountFieldPayloads(): array
    {
        $login = ['email' => 'fixture@example.test', 'password' => 'x'];
        $register = ['name' => 'Fixture', 'email' => 'fixture@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'terms' => '1'];

        return [
            ['login', $login, true], ['login', $login + ['remember' => '1'], true],
            ['login', $login + ['remember' => 'on'], false], ['login', $login + ['remember' => ['1']], false],
            ['register', $register, true], ['register', array_replace($register, ['terms' => null]), false],
            ['register', array_replace($register, ['password_confirmation' => 'different']), false],
            ['register', array_replace($register, ['password' => 'short', 'password_confirmation' => 'short']), false],
            ['register', array_replace($register, ['name' => str_repeat('a', 256)]), false],
            ['register', array_replace($register, ['email' => 'Fixture@example.test']), false],
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
