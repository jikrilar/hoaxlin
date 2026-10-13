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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Component;
use Illuminate\View\ViewException;
use Illuminate\View\ViewServiceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UiComponentsTest extends TestCase
{
    private string $compiledPath;

    private Container $previousContainer;

    private ?Container $previousFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->compiledPath = sys_get_temp_dir().'/hoaxlin-ui-tests-'.bin2hex(random_bytes(6));
        mkdir($this->compiledPath);

        // Render the real components without booting HTTP, .env, DB or providers.
        $root = dirname(__DIR__, 2);
        $app = new Application($root);
        $app->instance('config', new Repository([
            'view' => ['paths' => [$root.'/resources/views'], 'compiled' => $this->compiledPath],
        ]));
        Facade::clearResolvedInstances();
        Component::flushCache();
        Component::forgetFactory();
        Facade::setFacadeApplication($app);
        foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, ViewServiceProvider::class] as $provider) {
            $app->register($provider);
        }
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

    public function test_buttons_default_to_non_submit_and_loading_is_native_disabled(): void
    {
        $dom = $this->render('<form><x-ui.button id="normal">Batal</x-ui.button><x-ui.button id="busy" type="submit" :loading="true">Memproses…</x-ui.button></form>');

        $this->assertSame('button', $dom->evaluate('string(//*[@id="normal"]/@type)'));
        $this->assertSame(0, $dom->query('//*[@id="normal"]/@disabled')->length);
        $this->assertSame('submit', $dom->evaluate('string(//*[@id="busy"]/@type)'));
        $this->assertSame(1, $dom->query('//*[@id="busy"]/@disabled')->length);
        $this->assertSame('true', $dom->evaluate('string(//*[@id="busy"]/@aria-busy)'));
        $this->assertSame(0, $dom->query('//button/@variant | //button/@loading')->length);
        $this->assertSame(1, $dom->query('//form')->length);
    }

    public function test_fields_connect_labels_hints_errors_and_external_descriptions(): void
    {
        $dom = $this->render('<p id="external">Petunjuk</p><x-ui.field id="one" label="Email" hint="Gunakan email akun." error="Email tidak valid."><x-forms.input type="email" name="email" aria-describedby="external one-hint" aria-invalid="false" required /></x-ui.field><x-ui.field id="two" label="Kata sandi"><x-forms.input type="password" name="password" /></x-ui.field>');

        $this->assertSame('one', $dom->evaluate('string(//label[1]/@for)'));
        $this->assertSame('true', $dom->evaluate('string(//*[@id="one"]/@aria-invalid)'));
        $this->assertSame('external one-hint one-error', $dom->evaluate('string(//*[@id="one"]/@aria-describedby)'));
        $this->assertSame('false', $dom->evaluate('string(//*[@id="two"]/@aria-invalid)'));
        $this->assertSame(0, $dom->query('//*[@id="two"]/@aria-describedby')->length);
        $this->assertSame(1, $dom->query('//*[@id="one"]/@required')->length);
        $this->assertSame(0, $dom->query('//input/@hint | //input/@error | //input/@label | //div/@hint | //div/@error')->length);
        $ids = [];
        foreach ($dom->query('//*[@id]') as $node) {
            $ids[] = $node->getAttribute('id');
        }
        $this->assertSame($ids, array_unique($ids));
        foreach (explode(' ', $dom->evaluate('string(//*[@id="one"]/@aria-describedby)')) as $id) {
            $this->assertSame(1, $dom->query('//*[@id="'.$id.'"]')->length);
        }
    }

    public function test_untrusted_props_slots_and_input_values_remain_text(): void
    {
        $payload = '<script>alert("x")</script>';
        $dom = $this->render('<x-ui.alert :title="$payload">{{ $payload }}</x-ui.alert><x-ui.empty-state :title="$payload" :message="$payload" /><x-ui.disclosure :title="$payload">{{ $payload }}</x-ui.disclosure><x-ui.field id="safe" :label="$payload" :hint="$payload" :error="$payload"><x-forms.input name="safe" :value="$payload" /></x-ui.field>', ['payload' => $payload]);

        $this->assertSame(0, $dom->query('//script')->length);
        $this->assertSame($payload, $dom->evaluate('string(//summary)'));
        $this->assertSame($payload, $dom->evaluate('string(//*[@id="safe"]/@value)'));
        $this->assertSame($payload, $dom->evaluate('string(//label)'));
    }

    public function test_links_preserve_navigation_and_secure_new_tab_rel(): void
    {
        $dom = $this->render('<x-ui.link :href="$href" target="_blank" rel="nofollow">Hasil</x-ui.link>', ['href' => '/hasil/12?one=1&two=2']);

        $this->assertSame('/hasil/12?one=1&two=2', $dom->evaluate('string(//a/@href)'));
        $this->assertSame('nofollow noopener noreferrer', $dom->evaluate('string(//a/@rel)'));
        $this->assertSame(0, $dom->query('//a/@variant | //a/@disabled')->length);
    }

    public function test_disclosure_and_alert_roles_are_native_or_chosen_by_the_caller(): void
    {
        $dom = $this->render('<x-ui.disclosure title="Detail" /><x-ui.disclosure title="Terbuka" :open="true">Isi</x-ui.disclosure><x-ui.alert tone="info">Bantuan</x-ui.alert><x-ui.alert tone="danger" role="alert">Error</x-ui.alert>');

        $this->assertSame(2, $dom->query('//details/summary')->length);
        $this->assertSame(1, $dom->query('//details/@open')->length);
        $this->assertSame(0, $dom->query('//*[@data-tone="info"]/@role')->length);
        $this->assertSame('alert', $dom->evaluate('string(//*[@data-tone="danger"]/@role)'));
        $this->assertSame(0, $dom->query('//details/@title | //details/@aria-expanded | //div/@tone')->length);
    }

    #[DataProvider('invalidContracts')]
    public function test_invalid_component_contracts_fail_closed(string $template): void
    {
        try {
            $this->render($template);
            $this->fail('Invalid component contract was rendered.');
        } catch (ViewException $exception) {
            $cause = $exception;
            while ($cause->getPrevious()) {
                $cause = $cause->getPrevious();
            }
            $this->assertInstanceOf(InvalidArgumentException::class, $cause);
        }
    }

    public static function invalidContracts(): array
    {
        return [
            'variant' => ['<x-ui.button variant="untrusted" />'],
            'button type' => ['<x-ui.button type="link" />'],
            'loading boolean' => ['<x-ui.button loading="false" />'],
            'script URL' => ['<x-ui.link href="javascript:alert(1)" />'],
            'data URL' => ['<x-ui.link href="data:text/html,test" />'],
            'malformed URL' => ['<x-ui.link href="https://" />'],
            'protocol relative URL' => ['<x-ui.link href="//example.test" />'],
            'disabled anchor' => ['<x-ui.link href="/" disabled />'],
            'field id' => ['<x-ui.field id="two words" label="Email" />'],
            'input id mismatch' => ['<x-ui.field id="one" label="Email"><x-forms.input id="two" /></x-ui.field>'],
            'input context override' => ['<x-ui.field id="one" label="Email" error="Error"><x-forms.input error="" /></x-ui.field>'],
            'input without field' => ['<x-ui.disclosure id="one" title="Detail"><x-forms.input name="email" /></x-ui.disclosure>'],
            'input type' => ['<x-ui.field id="one" label="Email"><x-forms.input type="hidden" /></x-ui.field>'],
            'alert tone' => ['<x-ui.alert tone="prediction" />'],
            'open boolean' => ['<x-ui.disclosure title="Detail" open="false" />'],
        ];
    }

    private function render(string $template, array $data = []): DOMXPath
    {
        $html = Blade::render($template, $data, deleteCachedView: true);
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
