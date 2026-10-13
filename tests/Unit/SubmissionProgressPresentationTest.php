<?php

namespace Tests\Unit;

use App\Enums\ProcessingStage;
use App\Livewire\SubmissionProgress;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionAccess;
use DOMDocument;
use DOMXPath;
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
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\Component;
use Illuminate\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubmissionProgressPresentationTest extends TestCase
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
        $this->compiledPath = sys_get_temp_dir().'/hoaxlin-progress-view-tests-'.bin2hex(random_bytes(6));
        mkdir($this->compiledPath);
        $root = dirname(__DIR__, 2);
        $this->app = new Application($root);
        $this->app->instance('config', new Repository([
            'app' => ['url' => 'http://localhost', 'locale' => 'id'],
            'view' => ['paths' => [$root.'/resources/views'], 'compiled' => $this->compiledPath],
            'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]],
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

    #[DataProvider('progressStates')]
    public function test_backend_progress_and_terminal_presentation_are_preserved(string $status, ?string $stage, int $percentage): void
    {
        $submission = $this->submission($status, $stage);
        $submission->expects($this->once())->method('refresh')->willReturnSelf();
        $component = new SubmissionProgress;
        $component->mount($submission);
        $this->assertSame($percentage, $component->progress);
        $this->assertSame($status, $component->status);
        $this->assertSame($status === 'completed', $component->isCompleted);
        $this->assertSame($status === 'failed', $component->isFailed);
        $dom = $this->renderProgress($component);
        $poll = $dom->query('//*[@*[name()="wire:poll.2s.visible"]]');
        $this->assertSame($submission->isTerminal() ? 0 : 1, $poll->length);
        $this->assertSame($status === 'failed' ? 0 : 1, $dom->query('//*[@role="progressbar"]')->length);
        if ($status !== 'failed') {
            $this->assertSame((string) $percentage, $dom->evaluate('string(//*[@role="progressbar"]/@aria-valuenow)'));
        }
        $this->assertSame(1, $dom->query('//*[@role="status" and @aria-live="polite" and @aria-atomic="true"]')->length);
        $this->assertSame(0, $dom->query('//*[@data-progress-percent and @aria-live]')->length);
        $this->assertSame('http://localhost/hasil/101', $dom->evaluate('string(//a[@data-progress-refresh]/@href)'));
        $this->assertSame(0, $dom->query('//a[@data-progress-refresh and @*[name()="wire:navigate"]]')->length);
        if ($status === 'failed') {
            $this->assertStringContainsString('Hasil final belum tersedia', $dom->evaluate('string(//section)'));
            $this->assertSame(0, $dom->query('//*[@data-progress-percent]')->length);
        }
    }

    public static function progressStates(): array
    {
        $states = [];
        foreach (ProcessingStage::cases() as $stage) {
            $states['processing '.$stage->value] = ['processing', $stage->value, $stage->progressPercentage()];
            $states['failed '.$stage->value] = ['failed', $stage->value, $stage->progressPercentage()];
        }

        return $states + [
            'queued pending' => ['pending', 'queued', 0],
            'pending fallback' => ['pending', null, 5],
            'processing fallback' => ['processing', null, 30],
            'unknown stage' => ['processing', '<script id="attack">unsafe</script>', 30],
            'completed without result' => ['completed', null, 100],
            'failed without stage' => ['failed', null, 0],
        ];
    }

    public function test_unknown_status_and_stage_are_honest_and_partial_result_is_not_rendered(): void
    {
        $submission = $this->submission('unexpected', '<script id="attack">unsafe</script>');
        $submission->setRelation('detectionResult', new DetectionResult(['label' => 'hoax', 'confidence_score' => 0.9, 'explanation' => 'Partial secret']));
        $submission->method('refresh')->willReturnSelf();
        $component = new SubmissionProgress;
        $component->mount($submission);
        $dom = $this->renderProgress($component);
        $this->assertStringContainsString('Status pemrosesan belum tersedia', $dom->evaluate('string(//section)'));
        $this->assertSame(0, $dom->query('//*[@role="progressbar"] | //*[@id="attack"]')->length);
        $this->assertStringNotContainsString('Partial secret', $dom->evaluate('string(//section)'));
        $this->assertStringNotContainsString('hoax', $dom->evaluate('string(//section)'));
        $this->assertSame(0, $component->progress);
    }

    public function test_each_refresh_reads_new_stage_and_retains_history_refresh_route(): void
    {
        $submission = $this->submission('processing', 'classifying');
        $submission->expects($this->exactly(2))->method('refresh')->willReturnSelf();
        $component = new SubmissionProgress;
        $component->mount($submission);
        $submission->processing_stage = 'retrieving';
        $component->refreshProgress();
        $this->assertSame(75, $component->progress);
        $this->assertSame('Pencarian bukti', $component->stageLabel);
        $request = Request::create('http://localhost/riwayat/101');
        $request->setRouteResolver(fn () => $this->app['router']->getRoutes()->match($request));
        $this->app->instance('request', $request);
        $this->assertSame('http://localhost/riwayat/101', $this->renderProgress($component)->evaluate('string(//a[@data-progress-refresh]/@href)'));
    }

    #[DataProvider('partialStates')]
    public function test_partial_result_never_appears_as_final(string $status): void
    {
        $submission = $this->submission($status, 'explaining');
        $submission->setRelation('detectionResult', new DetectionResult([
            'label' => 'hoax', 'confidence_score' => 0.91, 'explanation' => 'Partial secret',
        ]));
        $submission->method('refresh')->willReturnSelf();
        $component = new SubmissionProgress;
        $component->mount($submission);
        $text = $this->renderProgress($component)->evaluate('string(//section)');
        $this->assertFalse($component->isCompleted);
        $this->assertStringNotContainsString('Partial secret', $text);
        $this->assertStringNotContainsString('hoax', $text);
        $this->assertStringNotContainsString('91%', $text);
        $this->assertStringNotContainsString('Lihat Hasil', $text);
    }

    public static function partialStates(): array
    {
        return ['pending' => ['pending'], 'processing' => ['processing'], 'failed' => ['failed']];
    }

    #[DataProvider('revokedAccess')]
    public function test_refresh_reauthorizes_before_reading_submission(bool $owned, int $expectedStatus): void
    {
        $submission = $this->submission('processing', 'classifying');
        $submission->expects($this->once())->method('refresh')->willReturnSelf();
        if ($owned) {
            $submission->user_id = 11;
            $this->user = new User;
            $this->user->id = 11;
        }
        $this->app['request']->setUserResolver(fn () => $this->user);
        $component = new SubmissionProgress;
        $component->mount($submission);
        $this->user = null;
        $this->session->forget(SubmissionAccess::sessionKey($submission));
        try {
            $component->refreshProgress();
            $this->fail('Refresh must reject revoked access.');
        } catch (HttpException $exception) {
            $this->assertSame($expectedStatus, $exception->getStatusCode());
        }
    }

    public static function revokedAccess(): array
    {
        return ['guest capability' => [false, 404], 'account owner' => [true, 403]];
    }

    private function submission(string $status, ?string $stage): Submission
    {
        $submission = $this->getMockBuilder(Submission::class)->onlyMethods(['refresh'])->getMock();
        $submission->forceFill(['id' => 101, 'status' => $status, 'processing_stage' => $stage, 'guest_access_token_hash' => hash('sha256', 'guest-fixture')]);
        $this->session->put(SubmissionAccess::sessionKey($submission), 'guest-fixture');

        return $submission;
    }

    private function renderProgress(SubmissionProgress $component): DOMXPath
    {
        $html = $this->app['view']->make('livewire.submission-progress', [
            'submission' => $component->submission, 'status' => $component->status,
            'stageLabel' => $component->stageLabel, 'progress' => $component->progress,
            'isCompleted' => $component->isCompleted, 'isFailed' => $component->isFailed,
        ])->render();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}
