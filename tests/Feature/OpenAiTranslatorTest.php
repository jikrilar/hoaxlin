<?php

namespace Tests\Feature;

use App\Contracts\Classifier;
use App\Contracts\Explainer;
use App\Contracts\Translator;
use App\DataObjects\Classification;
use App\DataObjects\Explanation;
use App\Enums\DetectionLabel;
use App\Exceptions\AiServiceException;
use App\Jobs\ProcessSubmission;
use App\Models\Submission;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Extraction\TextInputExtractor;
use App\Services\Fakes\FakeClassifier;
use App\Services\Fakes\FakeExplainer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiTranslatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.openai.key' => 'test-openai-key',
            'services.openai.translation_model' => 'gpt-4o-mini',
            'services.openai.translation_prompt_version' => 'test-v1',
        ]);
    }

    public function test_indonesian_text_bypasses_openai_and_remains_unchanged(): void
    {
        Http::fake();
        $text = 'Pemerintah telah mengumumkan kebijakan baru untuk masyarakat dan berita ini diterbitkan oleh sumber resmi.';

        $translation = app(Translator::class)->translate($text);

        $this->assertFalse($translation->translated);
        $this->assertSame('id', $translation->sourceLanguage);
        $this->assertSame($text, $translation->text);
        Http::assertNothingSent();
    }

    public function test_english_text_is_translated_with_responses_api_and_cached(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'source_language' => 'en',
                    'translated' => true,
                    'indonesian_text' => 'Pemerintah mengatakan kebijakan baru akan berlaku bagi semua warga setelah pengumuman resmi.',
                ], JSON_THROW_ON_ERROR),
                'usage' => ['input_tokens' => 24, 'output_tokens' => 20],
            ]),
        ]);
        $text = 'The government said that the new policy will apply to all people after the official announcement.';
        $translator = app(Translator::class);

        $first = $translator->translate($text);
        $second = $translator->translate($text);

        $this->assertTrue($first->translated);
        $this->assertSame('en', $first->sourceLanguage);
        $this->assertSame('Pemerintah mengatakan kebijakan baru akan berlaku bagi semua warga setelah pengumuman resmi.', $first->text);
        $this->assertFalse($first->cached);
        $this->assertTrue($second->cached);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer test-openai-key')
            && $request['model'] === 'gpt-4o-mini'
            && $request['input'] === $text
            && $request['store'] === false);
    }

    public function test_english_pipeline_sends_indonesian_translation_to_indobert(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'source_language' => 'en',
                    'translated' => true,
                    'indonesian_text' => 'Pemerintah mengumumkan bantuan baru untuk semua warga setelah pertemuan resmi.',
                ], JSON_THROW_ON_ERROR),
                'usage' => ['input_tokens' => 22, 'output_tokens' => 17],
            ]),
        ]);
        $classifier = (new FakeClassifier)->willReturn(new Classification(
            DetectionLabel::Valid,
            0.91,
            'indobert-test-v1',
            ['valid' => 0.91, 'hoax' => 0.09],
            8,
        ));
        $this->app->instance(Classifier::class, $classifier);
        $this->app->instance(Explainer::class, (new FakeExplainer)->willReturn(Explanation::ready('Penjelasan.', 'test')));
        $this->app->instance(TextExtractorResolver::class, new TextExtractorResolver([new TextInputExtractor]));

        $submission = Submission::create([
            'input_type' => 'text',
            'raw_input' => 'The government announced new financial support for all citizens after the official meeting, according to the news report.',
            'status' => 'pending',
        ]);

        ProcessSubmission::dispatchSync($submission);

        $submission->refresh();
        $this->assertSame('completed', $submission->status);
        $this->assertSame('en', $submission->source_language);
        $this->assertSame('openai', $submission->translation_provider);
        $this->assertSame('gpt-4o-mini', $submission->translation_model);
        $this->assertSame('Pemerintah mengumumkan bantuan baru untuk semua warga setelah pertemuan resmi.', $submission->translated_text);
        $this->assertSame([$submission->translated_text], $classifier->classifiedTexts());
    }

    public function test_english_text_fails_clearly_when_api_key_is_missing(): void
    {
        config(['services.openai.key' => null]);

        try {
            app(Translator::class)->translate('The government said that this news was published by officials for all people in the country.');
            $this->fail('Expected AiServiceException.');
        } catch (AiServiceException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertStringContainsString('OPENAI_API_KEY', $exception->getMessage());
        }
    }
}
