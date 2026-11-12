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

    public function test_password_fields_keep_native_attributes_and_hide_inert_toggles(): void
    {
        $dom = $this->render('<x-ui.field id="current" label="Kata Sandi" hint="Gunakan kata sandi akun." error="Kata sandi salah."><x-forms.password name="password" autocomplete="current-password" required /></x-ui.field><x-ui.field id="new" label="Kata Sandi Baru"><x-forms.password name="password_confirmation" /></x-ui.field>');

        $this->assertSame('password', $dom->evaluate('string(//*[@id="current"]/@type)'));
        $this->assertSame('password', $dom->evaluate('string(//*[@id="current"]/@name)'));
        $this->assertSame('current-password', $dom->evaluate('string(//*[@id="current"]/@autocomplete)'));
        $this->assertSame('new-password', $dom->evaluate('string(//*[@id="new"]/@autocomplete)'));
        $this->assertSame('current-hint current-error', $dom->evaluate('string(//*[@id="current"]/@aria-describedby)'));
        $this->assertSame('true', $dom->evaluate('string(//*[@id="current"]/@aria-invalid)'));
        $this->assertSame(1, $dom->query('//*[@id="current"]/@required')->length);
        $this->assertSame(0, $dom->query('//input/@value')->length);
        $this->assertSame(2, $dom->query('//button[@type="button" and @hidden and @aria-pressed="false" and @aria-label="Tampilkan kata sandi"]')->length);
        $this->assertSame('current', $dom->evaluate('string(//button[@data-password-toggle="current"]/@aria-controls)'));
    }

    public function test_term_renders_accessible_popover_with_glossary_description_and_no_js_title_fallback(): void
    {
        $glossary = require dirname(__DIR__, 2).'/config/glossary.php';
        $this->assertNotEmpty($glossary);
        foreach ($glossary as $key => $entry) {
            $this->assertIsString($key);
            $this->assertNotSame('', trim($entry['label'] ?? ''));
            $this->assertNotSame('', trim($entry['description'] ?? ''));
            $this->assertLessThanOrEqual(160, mb_strlen($entry['description']));
        }

        $dom = $this->render('<p><x-ui.term key="confidence">skor keyakinan</x-ui.term> dan <x-ui.term key="rag" /></p>');

        $this->assertSame(2, $dom->query('//*[@data-term]')->length);
        $this->assertSame(2, $dom->query('//button[@type="button" and @data-term-trigger and @aria-expanded="false"]')->length);
        $this->assertStringContainsString('skor keyakinan', $dom->evaluate('string((//button[@data-term-trigger])[1])'));
        $this->assertStringContainsString('pencarian rujukan', $dom->evaluate('string((//button[@data-term-trigger])[2])'));
        $this->assertSame(
            'Seberapa yakin model terhadap tebakannya. Bukan persentase kebenaran berita.',
            $dom->evaluate('string((//button[@data-term-trigger])[1]/@title)'),
        );
        $firstId = $dom->evaluate('string((//button[@data-term-trigger])[1]/@aria-describedby)');
        $secondId = $dom->evaluate('string((//button[@data-term-trigger])[2]/@aria-describedby)');
        $this->assertNotSame('', $firstId);
        $this->assertNotSame('', $secondId);
        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(
            'Seberapa yakin model terhadap tebakannya. Bukan persentase kebenaran berita.',
            $dom->evaluate('string(//*[@id="'.$firstId.'" and @role="tooltip" and @data-term-popover and @hidden])'),
        );
    }

    public function test_term_output_has_no_whitespace_before_punctuation_or_between_siblings(): void
    {
        $dom = $this->render('<p>Status <x-ui.term key="abstention">belum terverifikasi</x-ui.term>, bukan kelas training ketiga. Skor <x-ui.term key="confidence">keyakinan</x-ui.term>.</p>');

        $buttons = $dom->query('//button[@data-term-trigger]');
        $this->assertSame(2, $buttons->length);

        $first = $buttons->item(0);
        $second = $buttons->item(1);

        // Trailing spaces/newlines inside the trigger would surface before the following punctuation.
        $this->assertSame('belum terverifikasi?', trim($first->textContent));
        $this->assertSame('belum terverifikasi?', $first->textContent, 'slot text must not carry surrounding whitespace');
        $this->assertSame('keyakinan?', $second->textContent);

        // The following text node starts with the punctuation, not with whitespace.
        $this->assertSame(', bukan kelas training ketiga. Skor ', $first->parentNode->nextSibling->textContent);
        $this->assertSame('.', $second->parentNode->nextSibling->textContent);

        $this->assertSame(2, $dom->query('//*[contains(@class,"hl-term__mark") and @aria-hidden="true"]')->length);
        $this->assertSame(0, $dom->query('//*[contains(@class,"hl-term__icon")]')->length);
    }

    public function test_term_trigger_has_no_interactive_box_wrapper_around_the_sentence(): void
    {
        $dom = $this->render('<p>Prediksi <x-ui.term key="classifier">model analisis</x-ui.term> tetap ditampilkan.</p>');

        $this->assertSame(1, $dom->query('//p/span[contains(concat(" ", normalize-space(@class), " "), " hl-term ")][not(@style)]')->length);
        $this->assertSame(0, $dom->query('//button[@data-term-trigger]/div | //button[@data-term-trigger]/p')->length);
        $this->assertSame(1, $dom->query('//button[@data-term-trigger][not(@class) or contains(@class,"hl-term__trigger")]')->length);
    }

    public function test_unknown_term_key_renders_safe_text_fallback_without_throwing(): void
    {
        $payload = '<script id="attack">alert(1)</script>';
        $dom = $this->render('<p><x-ui.term key="unknown_key_fixture">istilah khusus</x-ui.term> <x-ui.term key="unknown_empty" /> <x-ui.term key="unknown_xss">{{ $payload }}</x-ui.term></p>', [
            'payload' => $payload,
        ]);

        $this->assertSame(0, $dom->query('//button[@data-term-trigger] | //*[@data-term-popover] | //script[@id="attack"]')->length);
        $this->assertStringContainsString('istilah khusus', $dom->evaluate('string(//p)'));
        $this->assertStringContainsString('unknown_empty', $dom->evaluate('string(//p)'));
        $this->assertStringContainsString($payload, htmlspecialchars_decode($dom->evaluate('string(//p)'), ENT_QUOTES));
    }

    public function test_disclaimer_variants_are_explicit_and_fail_closed(): void
    {
        $dom = $this->render('<x-ui.disclaimer /><x-ui.disclaimer variant="full" />');

        $this->assertSame(2, $dom->query('//*[@data-disclaimer]')->length);
        $this->assertSame('compact', $dom->evaluate('string((//*[@data-disclaimer])[1]/@data-disclaimer)'));
        $this->assertSame('full', $dom->evaluate('string((//*[@data-disclaimer])[2]/@data-disclaimer)'));
        $this->assertStringContainsString(
            'Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.',
            $dom->evaluate('string((//*[@data-disclaimer])[1])'),
        );
        $this->assertSame(
            '/cara-kerja#meaning-title',
            $dom->evaluate('string((//*[@data-disclaimer])[2]//a/@href)'),
        );
        $this->assertStringContainsString('Cara membaca hasil', $dom->evaluate('string((//*[@data-disclaimer])[2]//a)'));
        $this->assertStringContainsString('bukan bukti bahwa informasi benar atau palsu', $dom->evaluate('string((//*[@data-disclaimer])[2])'));
        $this->assertStringContainsString('bukan persentase kebenaran berita', $dom->evaluate('string((//*[@data-disclaimer])[2])'));
    }

    public function test_disclaimer_accepts_page_specific_context_inside_the_same_block(): void
    {
        $dom = $this->render('<x-ui.disclaimer><p>Skor keyakinan bukan persentase kebenaran berita.</p></x-ui.disclaimer>');

        $this->assertSame(1, $dom->query('//*[@data-disclaimer]')->length);
        $this->assertStringContainsString('Skor keyakinan bukan persentase kebenaran berita.', $dom->evaluate('string(//*[@data-disclaimer])'));
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
            'password restore' => ['<x-ui.field id="one" label="Password"><x-forms.password name="password" value="secret" /></x-ui.field>'],
            'password type' => ['<x-ui.field id="one" label="Password"><x-forms.password name="password" type="text" /></x-ui.field>'],
            'password autocomplete' => ['<x-ui.field id="one" label="Password"><x-forms.password name="password" autocomplete="email" /></x-ui.field>'],
            'alert tone' => ['<x-ui.alert tone="prediction" />'],
            'open boolean' => ['<x-ui.disclosure title="Detail" open="false" />'],
            'disclaimer variant' => ['<x-ui.disclaimer variant="marketing" />'],
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
