<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\RequestGuard;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\PasswordBroker;
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
use Illuminate\Validation\ValidationException;
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

    #[DataProvider('passwordPages')]
    public function test_password_recovery_pages_have_native_forms_focus_and_existing_recovery_links(string $view, string $path, string $heading): void
    {
        $this->app['view']->share('errors', new ViewErrorBag);
        $dom = $this->render($view, $path);
        $this->assertSame($heading, $dom->evaluate('string(//h1)'));
        $this->assertSame(0, $dom->query('//main//*[@style or @onclick] | //form[@novalidate]')->length);
        $this->assertSame(1, $dom->query('//input[@id="email" and @autofocus and @required and @autocomplete="email"]')->length);
        $this->assertSame(1, $dom->query('//main//form[@method="POST" and @data-submit-label]/input[@name="_token"]')->length);
        $this->assertSame(1, $dom->query('//main//a[@href="http://localhost/login"]')->length);
        $this->assertStringNotContainsString('secret-marker', $dom->document->saveHTML());
        if ($view === 'auth.forgot-password') {
            $this->assertStringContainsString('kotak masuk dan folder spam', $dom->evaluate('string(//main)'));
            $this->assertStringNotContainsString('Kami akan mengirimkan', $dom->evaluate('string(//main)'));
        } else {
            $this->assertSame('broker-token', $dom->evaluate('string(//input[@name="token" and @type="hidden"]/@value)'));
            $this->assertSame(1, $dom->query('//main//a[@href="http://localhost/forgot-password"]')->length);
            $this->assertStringContainsString('diperiksa saat perubahan dikirim', $dom->evaluate('string(//main)'));
            $this->assertSame(2, $dom->query('//input[@type="password" and @autocomplete="new-password"]')->length);
        }
    }

    public static function passwordPages(): array
    {
        return [['auth.forgot-password', '/forgot-password', 'Lupa kata sandi?'], ['auth.reset-password', '/reset-password/broker-token', 'Buat kata sandi baru']];
    }

    #[DataProvider('resetEmails')]
    public function test_reset_email_is_safe_and_old_token_or_passwords_never_replace_route_values(array $previous, string $query, string $expected): void
    {
        $this->session->put('_old_input', $previous + ['token' => 'wrong-old-token', 'password' => 'secret-marker', 'password_confirmation' => 'secret-marker']);
        $dom = $this->render('auth.reset-password', '/reset-password/broker-token'.$query);
        $this->assertSame($expected, $dom->evaluate('string(//input[@name="email"]/@value)'));
        $this->assertSame('broker-token', $dom->evaluate('string(//input[@name="token"]/@value)'));
        $this->assertSame(0, $dom->query('//*[@id="attack"]')->length);
        $this->assertStringNotContainsString('secret-marker', $dom->document->saveHTML());
        $this->assertStringNotContainsString('wrong-old-token', $dom->document->saveHTML());
    }

    public static function resetEmails(): array
    {
        return [
            [[], '?email=query%40example.test', 'query@example.test'],
            [['email' => 'old@example.test'], '?email=query%40example.test', 'old@example.test'],
            [['email' => ['bad']], '?email=query%40example.test', ''],
            [['email' => null], '?email=query%40example.test', ''],
            [[], '?email%5B%5D=bad', ''],
            [[], '?email=%22%3E%3Cscript%20id%3Dattack%3Eliteral', '"><script id=attack>literal'],
        ];
    }

    public function test_forgot_email_arrays_are_empty_and_broker_status_is_escaped_without_delivery_claim(): void
    {
        $this->session->put('_old_input', ['email' => ['bad']]);
        $this->session->put('status', 'Broker <script id="attack">status</script>');
        $dom = $this->render('auth.forgot-password', '/forgot-password');
        $this->assertSame('', $dom->evaluate('string(//input[@name="email"]/@value)'));
        $this->assertStringContainsString('Broker <script id="attack">status</script>', $dom->evaluate('string(//*[@data-flash])'));
        $this->assertSame(0, $dom->query('//*[@id="attack"]')->length);
    }

    public function test_invalid_token_error_is_readable_without_a_link_to_a_hidden_token(): void
    {
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['token' => ['Token tidak tersedia.'], 'email' => ['Tautan reset tidak dapat digunakan.']]));
        $this->app['view']->share('errors', $errors);
        $dom = $this->render('auth.reset-password', '/reset-password/broker-token');
        $this->assertSame(1, $dom->query('//*[@id="auth-errors" and @autofocus]')->length);
        $this->assertSame(0, $dom->query('//*[@id="auth-errors"]//a[@href="#token"] | //input[@autofocus]')->length);
        $this->assertSame(1, $dom->query('//*[@id="auth-errors"]//a[@href="#email"]')->length);
        $this->assertStringContainsString('Token tidak tersedia.', $dom->evaluate('string(//*[@id="auth-errors"])'));
        $this->assertSame(1, $dom->query('//main//a[@href="http://localhost/forgot-password"]')->length);
    }

    #[DataProvider('linkStatuses')]
    public function test_actual_reset_link_controller_keeps_broker_status_and_only_recovers_email(string $status): void
    {
        $request = $this->brokerRequest('/forgot-password', ['email' => 'fixture@example.test', 'password' => 'secret-marker']);
        $request->headers->set('referer', 'http://localhost/forgot-password');
        $broker = $this->createMock(PasswordBroker::class);
        $broker->expects($this->once())->method('sendResetLink')->with(['email' => 'fixture@example.test'])->willReturn($status);
        $this->app->instance('auth.password', $broker);
        $response = (new PasswordResetLinkController)->store($request);
        $this->assertSame('http://localhost/forgot-password', $response->getTargetUrl());
        if ($status === PasswordBroker::RESET_LINK_SENT) {
            $this->assertSame(__($status), $this->session->get('status'));
        } else {
            $this->assertSame(__($status), $this->session->get('errors')->first('email'));
            $this->assertSame(['email' => 'fixture@example.test'], $this->session->getOldInput());
        }
    }

    public static function linkStatuses(): array
    {
        return [[PasswordBroker::RESET_LINK_SENT], [PasswordBroker::INVALID_USER], [PasswordBroker::RESET_THROTTLED]];
    }

    #[DataProvider('resetStatuses')]
    public function test_actual_reset_controller_preserves_success_login_redirect_and_broker_error_recovery(string $status): void
    {
        $payload = ['email' => 'fixture@example.test', 'token' => 'broker-token', 'password' => 'fixture-password', 'password_confirmation' => 'fixture-password'];
        $request = $this->brokerRequest('/reset-password', $payload);
        $request->headers->set('referer', 'http://localhost/reset-password/broker-token');
        $broker = $this->createMock(PasswordBroker::class);
        $broker->expects($this->once())->method('reset')->with($payload, $this->isInstanceOf(\Closure::class))->willReturn($status);
        $this->app->instance('auth.password', $broker);
        $response = (new NewPasswordController)->store($request);
        if ($status === PasswordBroker::PASSWORD_RESET) {
            $this->assertSame('http://localhost/login', $response->getTargetUrl());
            $this->assertSame(__($status), $this->session->get('status'));
        } else {
            $this->assertSame('http://localhost/reset-password/broker-token', $response->getTargetUrl());
            $this->assertSame(__($status), $this->session->get('errors')->first('email'));
            $this->assertSame(['email' => 'fixture@example.test'], $this->session->getOldInput());
        }
    }

    public static function resetStatuses(): array
    {
        return [[PasswordBroker::PASSWORD_RESET], [PasswordBroker::INVALID_TOKEN], [PasswordBroker::INVALID_USER]];
    }

    #[DataProvider('invalidResetFields')]
    public function test_actual_reset_validation_rejects_invalid_fields_before_calling_the_broker(array $changes, string $errorField): void
    {
        $request = $this->brokerRequest('/reset-password', array_replace([
            'email' => 'fixture@example.test', 'token' => 'broker-token', 'password' => 'fixture-password', 'password_confirmation' => 'fixture-password',
        ], $changes));
        $broker = $this->createMock(PasswordBroker::class);
        $broker->expects($this->never())->method('reset');
        $this->app->instance('auth.password', $broker);
        try {
            (new NewPasswordController)->store($request);
            $this->fail('Invalid fields must not reach the broker.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorField, $exception->errors());
        }
    }

    public static function invalidResetFields(): array
    {
        return [[['token' => null], 'token'], [['email' => 'invalid'], 'email'], [['password' => 'short', 'password_confirmation' => 'short'], 'password'], [['password_confirmation' => 'different'], 'password']];
    }

    public function test_reset_get_does_not_validate_token_with_the_broker(): void
    {
        $request = Request::create('http://localhost/reset-password/invalid-token?email=fixture%40example.test');
        $request->setLaravelSession($this->session);
        $request->setRouteResolver(fn () => $this->app['router']->getRoutes()->match($request));
        $this->app->instance('request', $request);
        $broker = $this->createMock(PasswordBroker::class);
        $broker->expects($this->never())->method('reset');
        $broker->expects($this->never())->method('sendResetLink');
        $this->app->instance('auth.password', $broker);
        $view = (new NewPasswordController)->create($request);
        $this->assertSame('auth.reset-password', $view->name());
        $this->assertSame($request, $view->getData()['request']);
    }

    private function brokerRequest(string $path, array $payload): Request
    {
        $factory = new ValidationFactory($this->app['translator'], $this->app);
        $this->app->instance('validator', $factory);
        // Use controller rules and Laravel validation; the DB/mail broker callback stays fake.
        $prototype = new class extends Request
        {
            public function validate(array $rules, ...$params): array
            {
                return app('validator')->make($this->all(), $rules)->validate();
            }
        };
        $request = $prototype::create('http://localhost'.$path, 'POST', $payload);
        $request->setLaravelSession($this->session);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
        $this->app['redirect']->setSession($this->session);

        return $request;
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
