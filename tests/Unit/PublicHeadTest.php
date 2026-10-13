<?php

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Illuminate\View\ViewServiceProvider;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PublicHeadTest extends TestCase
{
    private string $compiledPath;

    private Container $previousContainer;

    private ?Container $previousFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->compiledPath = sys_get_temp_dir().'/hoaxlin-head-tests-'.bin2hex(random_bytes(6));
        mkdir($this->compiledPath);

        // Exercise real Blade metadata without HTTP, .env, DB or asset requests.
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([
            'view' => ['paths' => [resource_path('views')], 'compiled' => $this->compiledPath],
        ]));
        Facade::clearResolvedInstances();
        Component::flushCache();
        Component::forgetFactory();
        Facade::setFacadeApplication($app);
        foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, ViewServiceProvider::class] as $provider) {
            $app->register($provider);
        }
        $session = new Store('head-fixture', new ArraySessionHandler(120));
        $session->put('_token', 'fixture-token');
        $app->instance('session', $session);
        $vite = $this->createMock(Vite::class);
        $vite->method('__invoke')->willReturn(new HtmlString(''));
        $app->instance(Vite::class, $vite);
        $app['blade.compiler']->directive('livewireStyles', [FrontendAssets::class, 'livewireStyles']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->compiledPath);
        Facade::clearResolvedInstances();
        Component::flushCache();
        Component::forgetFactory();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_default_metadata_does_not_derive_from_submission_data(): void
    {
        $private = 'Private-input-marker-123';
        $dom = $this->render('@include("partials.head")', [
            'raw_input' => $private,
            'submission' => (object) ['raw_input' => $private, 'source_url' => $private],
        ]);

        $this->assertSame('hoaxlin.id — Periksa informasi', $dom->evaluate('string(//title)'));
        $description = $dom->evaluate('string(//meta[@name="description"]/@content)');
        $this->assertStringContainsString('bukan verifikasi kebenaran yang definitif', $description);
        $this->assertStringNotContainsString($private, $dom->document->saveHTML());
        $this->assertSame($description, $dom->evaluate('string(//meta[@property="og:description"]/@content)'));
    }

    #[DataProvider('sectionTemplates')]
    public function test_sections_are_preserved_and_escaped_in_text_and_attributes(string $sections): void
    {
        $title = 'Judul </title><script id="attack">x</script> & teks';
        $description = '" /><script id="attack">x</script><meta name="injected" content="';
        $dom = $this->render($sections.' @include("partials.head")', compact('title', 'description'));

        $this->assertSame($title, $dom->evaluate('string(//title)'));
        $this->assertSame($title, $dom->evaluate('string(//meta[@property="og:title"]/@content)'));
        $this->assertSame($description, $dom->evaluate('string(//meta[@name="description"]/@content)'));
        $this->assertSame($description, $dom->evaluate('string(//meta[@property="og:description"]/@content)'));
        $this->assertSame(0, $dom->query('//script[@id="attack"] | //meta[@name="injected"]')->length);
    }

    public static function sectionTemplates(): array
    {
        return [
            'inline sections' => ['@section("title", $title) @section("description", $description)'],
            'escaped block sections' => ['@section("title"){{ $title }}@endsection @section("description"){{ $description }}@endsection'],
            'raw block sections' => ['@section("title"){!! $title !!}@endsection @section("description"){!! $description !!}@endsection'],
        ];
    }

    private function render(string $template, array $data): DOMXPath
    {
        $html = Blade::render('<html><head>'.$template.'</head><body></body></html>', $data, deleteCachedView: true);
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
