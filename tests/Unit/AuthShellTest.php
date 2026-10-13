<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileDeleteRequest;
use App\Http\Requests\ProfileUpdateRequest;
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
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Vite;
use Illuminate\Hashing\HashManager;
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
use Illuminate\Validation\PresenceVerifierInterface;
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

    #[DataProvider('verificationMessages')]
    public function test_verification_notice_has_safe_identity_native_actions_and_one_server_message(?string $key, ?string $message): void
    {
        $email = str_repeat('long', 70).'<script id="attack">literal</script>@example.test';
        $this->user = new User;
        $this->user->setRawAttributes(['email' => $email, 'email_verified_at' => null]);
        $this->app['view']->share('errors', new ViewErrorBag);
        if ($key) {
            $this->session->put($key, $message);
        }
        $dom = $this->render('auth.verify-email', '/verify-email');
        $this->assertSame('Verifikasi email', $dom->evaluate('string(//h1)'));
        $this->assertSame($email, $dom->evaluate('string(//main//strong)'));
        $this->assertSame(0, $dom->query('//*[@id="attack"] | //main//*[@style] | //main//input[not(@name="_token")]')->length);
        $this->assertStringContainsString('riwayat dan mengirim umpan balik', $dom->evaluate('string(//main)'));
        $this->assertStringContainsString('kotak masuk dan folder spam', $dom->evaluate('string(//main)'));
        $this->assertStringContainsString('sudah kedaluwarsa', $dom->evaluate('string(//main)'));
        $this->assertStringNotContainsString('telah dikirim', $dom->evaluate('string(//main)'));
        $this->assertSame(1, $dom->query('//form[@id="verification-resend-form" and @method="POST" and @action="http://localhost/email/verification-notification"]/input[@name="_token" and @value="fixture-token"]')->length);
        $this->assertSame('Kirim ulang tautan', trim($dom->evaluate('string(//button[@id="verification-resend-submit"])')));
        $this->assertSame(1, $dom->query('//main//a[@href="http://localhost/profil"]')->length);
        $this->assertSame(1, $dom->query('//form[@id="verification-logout-form" and @method="POST" and @action="http://localhost/logout"]/input[@name="_token" and @value="fixture-token"]')->length);
        $this->assertSame($key ? 1 : 0, $dom->query('//*[@data-flash]')->length);
        if ($key) {
            $this->assertSame($key === 'error' ? 'alert' : 'status', $dom->evaluate('string(//*[@data-flash]/@role)'));
            $this->assertStringContainsString($key === 'status' ? 'Tautan verifikasi baru telah dikirim.' : $message, $dom->evaluate('string(//*[@data-flash])'));
        }
    }

    public static function verificationMessages(): array
    {
        return [[null, null], ['status', 'verification-link-sent'], ['error', 'Server <script id="attack">error</script>']];
    }

    #[DataProvider('verificationPromptStates')]
    public function test_actual_verification_prompt_preserves_unverified_view_and_verified_intended_redirect(bool $verified, bool $intended): void
    {
        $user = $this->createMock(User::class);
        $user->method('hasVerifiedEmail')->willReturn($verified);
        $user->expects($this->never())->method('sendEmailVerificationNotification');
        if ($intended) {
            $this->session->put('url.intended', 'http://localhost/riwayat');
        }
        $request = $this->verificationRequest('/verify-email', 'GET', $user);
        $response = (new EmailVerificationPromptController)($request);
        if ($verified) {
            $this->assertSame($intended ? 'http://localhost/riwayat' : 'http://localhost', $response->getTargetUrl());
            $this->assertFalse($this->session->has('url.intended'));
        } else {
            $this->assertSame('auth.verify-email', $response->name());
        }
        $this->assertFalse($this->session->has('status'));
    }

    public static function verificationPromptStates(): array
    {
        return [[false, false], [true, false], [true, true]];
    }

    #[DataProvider('verificationStates')]
    public function test_actual_verification_resend_only_notifies_an_unverified_user(bool $verified): void
    {
        $user = $this->createMock(User::class);
        $user->method('hasVerifiedEmail')->willReturn($verified);
        $user->expects($verified ? $this->never() : $this->once())->method('sendEmailVerificationNotification');
        $request = $this->verificationRequest('/email/verification-notification', 'POST', $user);
        $request->headers->set('referer', 'http://localhost/verify-email');
        $response = (new EmailVerificationNotificationController)->store($request);
        $this->assertSame($verified ? 'http://localhost' : 'http://localhost/verify-email', $response->getTargetUrl());
        $this->assertSame($verified ? null : 'verification-link-sent', $this->session->get('status'));
    }

    #[DataProvider('verificationStates')]
    public function test_actual_verify_controller_only_delegates_unverified_fulfillment_and_keeps_home_success(bool $verified): void
    {
        $user = $this->createMock(User::class);
        $user->method('hasVerifiedEmail')->willReturn($verified);
        $this->verificationRequest('/verify-email', 'GET', $user);
        // Count delegation without performing User persistence or dispatching Verified.
        $request = new class extends EmailVerificationRequest
        {
            public int $fulfillCalls = 0;

            public function fulfill()
            {
                $this->fulfillCalls++;
            }
        };
        $request->setUserResolver(fn () => $user);
        $response = (new VerifyEmailController)($request);
        $this->assertSame($verified ? 0 : 1, $request->fulfillCalls);
        $this->assertSame('http://localhost', $response->getTargetUrl());
        $this->assertSame('Alamat email berhasil diverifikasi.', $this->session->get('success'));
    }

    public static function verificationStates(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('verificationIdentities')]
    public function test_laravel_verification_request_requires_matching_account_id_and_email_hash(int $id, string $email, bool $allowed): void
    {
        $user = new User;
        $user->setRawAttributes(['id' => 17, 'email' => 'fixture@example.test']);
        $request = EmailVerificationRequest::create('http://localhost/verify-email/'.$id.'/'.sha1($email));
        $route = $this->app['router']->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);
        $this->assertSame($allowed, $request->authorize());
    }

    public static function verificationIdentities(): array
    {
        return [[17, 'fixture@example.test', true], [18, 'fixture@example.test', false], [17, 'old@example.test', false]];
    }

    public function test_verification_routes_retain_native_methods_auth_signature_and_server_throttle(): void
    {
        $routes = $this->app['router']->getRoutes();
        foreach (['verification.notice', 'verification.send', 'verification.verify', 'logout', 'profile'] as $name) {
            $this->assertContains('auth', $routes->getByName($name)->gatherMiddleware());
        }
        $this->assertSame(['POST'], $routes->getByName('verification.send')->methods());
        $this->assertSame(['POST'], $routes->getByName('logout')->methods());
        $this->assertContains('signed', $routes->getByName('verification.verify')->gatherMiddleware());
        foreach (['verification.send', 'verification.verify'] as $name) {
            $this->assertContains('throttle:6,1', $routes->getByName($name)->gatherMiddleware());
        }
        $this->app['url']->setKeyResolver(fn () => 'fixture-signing-key');
        $parameters = ['id' => 17, 'hash' => sha1('fixture@example.test')];
        $valid = $this->app['url']->temporarySignedRoute('verification.verify', now()->addMinutes(60), $parameters);
        $expired = $this->app['url']->temporarySignedRoute('verification.verify', now()->subMinute(), $parameters);
        $this->assertTrue($this->app['url']->hasValidSignature(Request::create($valid)));
        $this->assertFalse($this->app['url']->hasValidSignature(Request::create($valid.'&tampered=1')));
        $this->assertFalse($this->app['url']->hasValidSignature(Request::create($expired)));
    }

    #[DataProvider('verificationStates')]
    public function test_profile_preserves_three_native_actions_and_verified_access_without_restoring_secrets(bool $verified): void
    {
        $this->user = new User;
        $this->user->setRawAttributes(['id' => 17, 'name' => 'Pengguna', 'email' => 'fixture@example.test', 'email_verified_at' => $verified ? '2026-10-13 00:00:00' : null]);
        $this->app['view']->share('errors', new ViewErrorBag);
        $dom = $this->render('profile', '/profil');
        $this->assertSame(3, $dom->query('//main//form')->length);
        foreach (['profile-form' => 'PATCH', 'password-form' => 'PUT', 'delete-account-form' => 'DELETE'] as $id => $method) {
            $this->assertSame(1, $dom->query('//form[@id="'.$id.'" and @method="POST" and @data-submit-label]/input[@name="_method" and @value="'.$method.'"]')->length);
            $this->assertSame(1, $dom->query('//form[@id="'.$id.'"]/input[@name="_token" and @value="fixture-token"]')->length);
        }
        $this->assertSame('http://localhost/profil', $dom->evaluate('string(//form[@id="profile-form"]/@action)'));
        $this->assertSame('http://localhost/profil/password', $dom->evaluate('string(//form[@id="password-form"]/@action)'));
        $this->assertSame('http://localhost/profil', $dom->evaluate('string(//form[@id="delete-account-form"]/@action)'));
        $this->assertSame('Nama <script id="attack">x</script>', $dom->evaluate('string(//input[@name="name"]/@value)'));
        $this->assertSame('email"@example.test', $dom->evaluate('string(//input[@name="email"]/@value)'));
        $this->assertSame(0, $dom->query('//main//*[@style or @onmouseover or @novalidate] | //*[@id="attack"] | //*[@id="strength-container"]')->length);
        $this->assertSame(4, $dom->query('//main//input[@type="password" and @required and not(@value)]')->length);
        $this->assertSame(2, $dom->query('//main//input[@autocomplete="current-password"]')->length);
        $this->assertSame(2, $dom->query('//main//input[@autocomplete="new-password"]')->length);
        $this->assertStringNotContainsString('secret-marker', $dom->document->saveHTML());
        $this->assertSame(1, $dom->query('//details[@id="delete-confirmation"]//form[@id="delete-account-form"]')->length);
        $this->assertSame(1, $dom->query('//button[@id="delete-account-btn" and @hidden]')->length);
        $this->assertSame(0, $dom->query('//dialog//form | //details/@open')->length);
        $this->assertStringContainsString($verified ? 'Email terverifikasi' : 'Email belum terverifikasi', $dom->evaluate('string(//main//header)'));
        $this->assertSame(1, $dom->query('//main//header//a[@href="http://localhost/'.($verified ? 'riwayat' : 'verify-email').'"]')->length);
        $this->assertStringContainsString('verifikasi ulang', $dom->evaluate('string(//main)'));
        $this->assertStringNotContainsString('Seluruh data', $dom->evaluate('string(//main)'));
    }

    #[DataProvider('profileErrorBags')]
    public function test_profile_errors_stay_in_their_action_and_delete_errors_have_native_recovery(array $bags): void
    {
        $this->user = new User;
        $this->user->setRawAttributes(['id' => 17, 'name' => 'Pengguna', 'email' => 'fixture@example.test']);
        $errors = new ViewErrorBag;
        foreach ($bags as $bag => $messages) {
            $errors->put($bag, new MessageBag($messages));
        }
        $this->app['view']->share('errors', $errors);
        $dom = $this->render('profile', '/profil');
        foreach (['profile' => 'profile-errors', 'passwordUpdate' => 'password-errors', 'accountDeletion' => 'delete-errors'] as $bag => $id) {
            $this->assertSame(isset($bags[$bag]) ? 1 : 0, $dom->query('//*[@id="'.$id.'" and @role="alert"]')->length);
        }
        $this->assertSame(isset($bags['passwordUpdate']['password']) ? 1 : 0, $dom->query('//*[@id="password-error"]')->length);
        $this->assertSame(isset($bags['accountDeletion']['password']) ? 1 : 0, $dom->query('//*[@id="confirm-password-error"]')->length);
        $this->assertSame(isset($bags['accountDeletion']) ? 1 : 0, $dom->query('//details[@id="delete-confirmation" and @open]')->length);
        $this->assertSame(isset($bags['accountDeletion']) ? 1 : 0, $dom->query('//dialog[@data-dialog-auto-open]')->length);
        $this->assertSame(1, $dom->query('//main//*[@autofocus and not(@hidden)]')->length);
        foreach ($dom->query('//*[contains(@class,"hl-profile-errors")]//a') as $link) {
            $id = substr($link->getAttribute('href'), 1);
            $this->assertSame(1, $dom->query('//input[@id="'.$id.'"]')->length);
        }
        $this->assertSame(0, $dom->query('//*[@id="attack"]')->length);
        $this->assertStringNotContainsString('secret-marker', $dom->document->saveHTML());
    }

    public static function profileErrorBags(): array
    {
        return [
            [['profile' => ['email' => 'Email <script id="attack">error</script>']]],
            [['passwordUpdate' => ['current_password' => 'Kata sandi saat ini salah.', 'password' => 'Konfirmasi berbeda.']]],
            [['accountDeletion' => ['password' => 'Kata sandi penghapusan salah.']]],
            [['profile' => ['name' => 'Nama diperlukan.'], 'passwordUpdate' => ['password' => 'Konfirmasi berbeda.'], 'accountDeletion' => ['password' => 'Kata sandi penghapusan salah.']]],
        ];
    }

    public function test_profile_malformed_old_identity_is_empty_instead_of_rendering_array_values(): void
    {
        $this->user = new User;
        $this->user->setRawAttributes(['name' => 'Pengguna', 'email' => 'fixture@example.test']);
        $this->session->put('_old_input', ['name' => ['bad'], 'email' => ['bad']]);
        $this->app['view']->share('errors', new ViewErrorBag);
        $dom = $this->render('profile', '/profil');
        $this->assertSame('', $dom->evaluate('string(//input[@name="name"]/@value)'));
        $this->assertSame('', $dom->evaluate('string(//input[@name="email"]/@value)'));
    }

    #[DataProvider('invalidProfileRequests')]
    public function test_profile_form_requests_use_real_validation_and_the_action_error_bag(string $class, array $payload, string $field, string $bag): void
    {
        $request = $this->profileRequest($class, $payload);
        try {
            $request->validateResolved();
            $this->fail('Invalid profile fields must fail in the matching action bag.');
        } catch (ValidationException $exception) {
            $this->assertSame($bag, $exception->errorBag);
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    #[DataProvider('validProfileRequests')]
    public function test_profile_form_requests_accept_valid_account_fields_without_unrelated_operations(string $class, array $payload, array $expected): void
    {
        $request = $this->profileRequest($class, $payload + ['is_admin' => '1', 'notification_preferences' => '1']);
        $request->validateResolved();
        $this->assertSame($expected, $request->validated());
    }

    public static function validProfileRequests(): array
    {
        return [
            [ProfileUpdateRequest::class, ['name' => 'Pengguna', 'email' => 'fixture@example.test'], ['name' => 'Pengguna', 'email' => 'fixture@example.test']],
            [PasswordUpdateRequest::class, ['current_password' => 'Current-123!', 'password' => 'New-123!', 'password_confirmation' => 'New-123!'], ['current_password' => 'Current-123!', 'password' => 'New-123!']],
            [ProfileDeleteRequest::class, ['password' => 'Current-123!'], ['password' => 'Current-123!']],
        ];
    }

    private function profileRequest(string $class, array $payload): FormRequest
    {
        $this->user = new User;
        $this->user->setRawAttributes(['id' => 17, 'email' => 'fixture@example.test', 'password' => password_hash('Current-123!', PASSWORD_BCRYPT, ['cost' => 4])]);
        $this->app['config']->set('hashing.driver', 'bcrypt');
        $this->app->instance('hash', new HashManager($this->app));
        $factory = new ValidationFactory($this->app['translator'], $this->app);
        $presence = $this->createMock(PresenceVerifierInterface::class);
        $presence->method('getCount')->willReturn(0);
        $factory->setPresenceVerifier($presence);
        $this->app->instance('validator', $factory);
        $request = $class::create('http://localhost/profil', 'POST', $payload);
        $request->setUserResolver(fn () => $this->user);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $this->app['url']->setRequest($request);

        return $request;
    }

    public static function invalidProfileRequests(): array
    {
        return [
            [ProfileUpdateRequest::class, ['name' => '', 'email' => 'fixture@example.test'], 'name', 'profile'],
            [ProfileUpdateRequest::class, ['name' => 'Pengguna', 'email' => 'invalid'], 'email', 'profile'],
            [PasswordUpdateRequest::class, ['current_password' => 'wrong', 'password' => 'New-123!', 'password_confirmation' => 'New-123!'], 'current_password', 'passwordUpdate'],
            [PasswordUpdateRequest::class, ['current_password' => 'Current-123!', 'password' => 'New-123!', 'password_confirmation' => 'different'], 'password', 'passwordUpdate'],
            [ProfileDeleteRequest::class, ['password' => 'wrong'], 'password', 'accountDeletion'],
            [ProfileDeleteRequest::class, [], 'password', 'accountDeletion'],
        ];
    }

    private function verificationRequest(string $path, string $method, User $user): Request
    {
        $request = Request::create('http://localhost'.$path, $method);
        $request->setLaravelSession($this->session);
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
        $this->app['redirect']->setSession($this->session);

        return $request;
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
