<?php

namespace Tests\Unit;

use App\Models\DetectionResult;
use App\Models\EvidenceReference;
use App\Models\Submission;
use App\Models\User;
use App\View\Presenters\DetectionPresentation;
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
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\Component;
use Illuminate\View\ViewServiceProvider;
use Livewire\Mechanisms\CompileLivewireTags\CompileLivewireTags;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class DetectionPresentationTest extends TestCase
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
        $this->compiledPath = sys_get_temp_dir().'/hoaxlin-result-view-tests-'.bin2hex(random_bytes(6));
        mkdir($this->compiledPath);
        $root = dirname(__DIR__, 2);
        $this->app = new Application($root);
        $this->app->instance('config', new Repository([
            'media' => require $root.'/config/media.php',
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
        (new CompileLivewireTags)->boot();
        $this->app['blade.compiler']->directive('livewire', fn () => '<?php echo view("livewire.submission-progress", ["submission"=>$submission, "status"=>$submission->status, "isCompleted"=>$submission->isCompleted(), "isFailed"=>$submission->isFailed(), "progress"=>60, "stageLabel"=>"Klasifikasi BERT"])->render(); ?>');
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

    #[DataProvider('labels')]
    public function test_label_score_and_disclaimer_never_imply_verification(?string $label, mixed $score, string $text, string $tone, string $percentage): void
    {
        [$submission, $result] = $this->records();
        $result->label = $label;
        $result->confidence_score = $score;
        $dom = $this->renderResult($submission, $result);
        $this->assertSame($tone, $dom->evaluate('string(//*[@data-result-label]/@data-tone)'));
        $this->assertStringContainsString($text, $dom->evaluate('string(//*[@data-result-label])'));
        $this->assertSame($percentage, $dom->evaluate('string(//*[@data-result-confidence])'));
        $this->assertSame(0, $dom->query('//*[@role="progressbar"] | //*[@id="confidence-fill"]')->length);
        $this->assertStringContainsString('bukan verifikasi kebenaran yang definitif', $dom->evaluate('string(//main)'));
        $this->assertSame(0, $dom->query('//*[@id="attack"]')->length);
        if ($label === 'meragukan') {
            $this->assertStringContainsString('Ini bukan bukti bahwa informasi benar atau palsu.', $dom->evaluate('string(//*[@data-result-label])'));
        }
    }

    public static function labels(): array
    {
        return [
            'valid' => ['valid', '0.63', 'Prediksi model: Valid', 'neutral', '63%'],
            'hoax' => ['hoax', '0.9199', 'Prediksi model: Hoaks', 'danger', '92%'],
            'abstained' => ['meragukan', '0.5', 'Informasi belum terverifikasi oleh sumber terpercaya', 'warning', '50%'],
            'null' => [null, null, 'Label prediksi belum tersedia', 'neutral', 'Tidak tersedia'],
            'unknown' => ['<script id="attack">x</script>', 0, 'Label prediksi belum tersedia', 'neutral', '0%'],
        ];
    }

    #[DataProvider('scores')]
    public function test_confidence_mapping_handles_missing_zero_and_invalid_values(mixed $score, ?string $expected): void
    {
        $this->assertSame($expected, DetectionPresentation::confidence($score));
    }

    public static function scores(): array
    {
        return [[null, null], [0, '0%'], ['0', '0%'], ['0.63', '63%'], [1, '100%'], [NAN, null], [INF, null], [-0.2, null], [1.2, null], ['bad', null]];
    }

    #[DataProvider('notFinal')]
    public function test_nonfinal_or_unrelated_result_cannot_expose_labels_explanation_evidence_or_final_actions(string $status, bool $available, bool $sameSubmission): void
    {
        [$submission, $result] = $this->records();
        $submission->status = $status;
        $result->submission_id = $sameSubmission ? 101 : 999;
        $result->explanation = 'Private final narrative';
        $result->model_version = 'Private classifier version';
        $submission->setRelation('evidenceReferences', collect([new EvidenceReference(['title' => 'Private evidence'])]));
        $dom = $this->renderResult($submission, $available ? $result : null);
        $this->assertSame(0, $dom->query('//*[@data-result-final or @data-result-label]')->length);
        $this->assertStringNotContainsString('Private final narrative', $dom->evaluate('string(//main)'));
        $this->assertStringNotContainsString('Private classifier version', $dom->evaluate('string(//main)'));
        $this->assertStringNotContainsString('Private evidence', $dom->evaluate('string(//main)'));
        $this->assertSame(0, $dom->query('//a[contains(@href,"/pdf")] | //form[@id="feedback-form"]')->length);
        $this->assertSame(in_array($status, ['pending', 'processing'], true) ? 1 : 0, $dom->query('//*[@*[name()="wire:poll.2s.visible"]]')->length);
        if ($status === 'completed') {
            $this->assertStringContainsString('Hasil final tidak tersedia', $dom->evaluate('string(//h1)'));
        }
    }

    public static function notFinal(): array
    {
        return [['pending', true, true], ['processing', true, true], ['failed', true, true], ['completed', false, true], ['completed', true, false]];
    }

    #[DataProvider('explanations')]
    public function test_only_ready_explanation_is_rendered(?string $state, ?string $narrative, bool $visible, string $message): void
    {
        [$submission, $result] = $this->records();
        $result->explanation_status = $state;
        $result->explanation = $narrative;
        $dom = $this->renderResult($submission, $result);
        $section = $dom->evaluate('string(//*[@data-explanation-state])');
        $this->assertStringContainsString($message, $section);
        $this->assertSame(0, $dom->query('//*[@id="attack"]')->length);
        if ($narrative) {
            $this->assertSame($visible, str_contains($section, $narrative));
        }
        $this->assertSame(1, $dom->query('//*[@data-result-final]')->length);
    }

    public static function explanations(): array
    {
        return [
            ['ready', 'Teks literal <script id="attack">x</script>.', true, 'Tersedia'],
            ['ready', null, false, 'Teks penjelasan tidak tersedia'],
            ['pending', 'Stale private explanation', false, 'Penjelasan AI belum tersedia'],
            ['unavailable', 'Stale private explanation', false, 'Penjelasan AI tidak tersedia'],
            [null, null, false, 'Belum tersedia'],
            ['unknown', 'Stale private explanation', false, 'Teks penjelasan tidak tersedia'],
        ];
    }

    public function test_evidence_is_ranked_by_document_id_ties_and_escaped_without_similarity_as_confidence(): void
    {
        [$submission, $result] = $this->records();
        $result->confidence_score = 0.63;
        $records = [];
        foreach ([[2, 'doc-b'], [1, 'doc-z'], [1, 'doc-a']] as [$rank, $id]) {
            $records[] = new EvidenceReference(['submission_id' => 101, 'rank' => $rank, 'document_id' => $id, 'title' => 'Title '.$id.' <script id="attack">x</script>', 'source' => 'Source <b>literal</b>', 'snippet' => 'Quote <img id="attack" src=x>', 'source_url' => 'https://reference.test/'.$id, 'similarity_score' => 0.99, 'knowledge_base_version' => 'kb-fixture']);
        }
        $submission->setRelation('evidenceReferences', collect($records));
        $dom = $this->renderResult($submission, $result);
        $this->assertSame(['doc-a', 'doc-z', 'doc-b'], array_map(fn ($node) => $node->getAttribute('data-evidence-document'), iterator_to_array($dom->query('//*[@data-evidence-document]'))));
        $this->assertSame(0, $dom->query('//*[@id="attack"]')->length);
        $this->assertSame(3, $dom->query('//a[@rel="noopener noreferrer" and @target="_blank" and starts-with(@href,"https://reference.test/")]')->length);
        $this->assertSame('63%', $dom->evaluate('string(//*[@data-result-confidence])'));
        $this->assertStringNotContainsString('99%', $dom->evaluate('string(//main)'));
        $this->assertStringContainsString('Tanggal publikasi tidak tersedia', $dom->evaluate('string(//main)'));
    }

    #[DataProvider('urls')]
    public function test_source_urls_require_http_https_and_reject_credentials_control_characters_and_ambiguous_paths(?string $url, bool $safe): void
    {
        $this->assertSame($safe ? $url : null, DetectionPresentation::sourceUrl($url));
    }

    public static function urls(): array
    {
        return [[null, false], ['https://reference.test/doc?x=1&y=2', true], ['http://reference.test', true], ['https://reference.test/berita-é', true], ['javascript:alert(1)', false], ['//reference.test', false], ['https://u:secret@reference.test', false], ["https://reference.test/\npath", false], ['https://reference.test\\@evil.test/', false], ['https://reference.test/has space', false]];
    }

    public function test_input_provenance_uses_persisted_versions_and_times_with_full_escaped_text_and_private_media(): void
    {
        [$submission, $result] = $this->records();
        $submission->forceFill(['input_type' => 'video_url', 'source_url' => 'https://input.test/file.mp4', 'raw_input' => str_repeat('Long <script id="attack">input</script> ', 1000), 'extracted_text' => 'Extracted <b>literal</b>', 'translated_text' => 'Translation <img id="attack" src=x>', 'source_language' => 'en', 'translation_provider' => 'provider-fixture', 'translation_model' => 'translation-fixture', 'pipeline_version' => 'pipeline-fixture', 'processing_completed_at' => '2026-09-01 13:45:00', 'media_path' => 'private/path.mp4']);
        $result->model_version = 'stored-version';
        $result->explanation_model = 'stored-explainer';
        $result->inference_ms = 0;
        $this->app['config']->set('services.openai.prompt_version', 'Unrelated current prompt');
        $dom = $this->renderResult($submission, $result);
        $text = $dom->evaluate('string(//main)');
        foreach (['Tautan video', 'Long <script id="attack">input</script>', 'Extracted <b>literal</b>', 'Translation <img id="attack" src=x>', 'provider-fixture', 'translation-fixture', 'pipeline-fixture', 'stored-version', 'stored-explainer', '0 ms', '01 Sep 2026, 13:45'] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        $this->assertStringNotContainsString('Unrelated current prompt', $text);
        $this->assertStringNotContainsString('private/path.mp4', $text);
        $this->assertStringNotContainsString('5 menit', $text);
        $this->assertSame(0, $dom->query('//*[@id="attack"]')->length);
        $this->assertSame(1, $dom->query('//a[contains(@href,"/hasil/101/media") and @rel="noopener noreferrer"]')->length);
        $this->assertSame(2, $dom->query('//details[@id="input-provenance" or @id="model-provenance"]')->length);
        $this->assertSame(1, $dom->query('//time[starts-with(@datetime,"2026-09-01T13:45")]')->length);
    }

    private function records(): array
    {
        $submission = new Submission(['status' => 'completed', 'input_type' => 'text', 'raw_input' => 'Fixture input']);
        $submission->id = 101;
        $submission->created_at = '2026-09-01 12:00:00';
        $submission->setRelation('evidenceReferences', collect());
        $result = new DetectionResult(['submission_id' => 101, 'label' => 'valid', 'confidence_score' => 0.63, 'explanation_status' => 'ready', 'explanation' => 'Fixture explanation']);

        return [$submission, $result];
    }

    private function renderResult(Submission $submission, ?DetectionResult $result): DOMXPath
    {
        $html = $this->app['view']->make('hasil', ['submission' => $submission, 'result' => $result, 'feedback' => null, 'failureReason' => $submission->isFailed() ? 'Safe public failure' : null])->render();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}
