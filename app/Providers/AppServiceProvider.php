<?php

namespace App\Providers;

use App\Contracts\Classifier;
use App\Contracts\Explainer;
use App\Contracts\Translator;
use App\Models\Dataset;
use App\Models\Submission;
use App\Models\User;
use App\Observers\FilamentAuditObserver;
use App\Services\Bert\BertClassifier;
use App\Services\Bert\CachedBertClassifier;
use App\Services\Bert\CircuitBreakingClassifier;
use App\Services\Extraction\ArticleExtractor;
use App\Services\Extraction\OpenAiImageExtractor;
use App\Services\Extraction\OpenAiVideoExtractor;
use App\Services\Extraction\StoredTextExtractor;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Extraction\TextInputExtractor;
use App\Services\OpenAI\OpenAiExplainer;
use App\Services\OpenAI\OpenAiQuota;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use App\Services\Resilience\CircuitBreaker;
use App\Support\OpenAiTranslator;
use App\Support\TextLanguageDetector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CircuitBreaker::class, fn ($app, array $parameters) => new CircuitBreaker($parameters['service'] ?? 'bert'));
        $this->app->singleton(OpenAiQuota::class, fn () => new OpenAiQuota);

        $this->app->singleton(Classifier::class, function ($app): Classifier {
            $http = new BertClassifier;
            $protected = new CircuitBreakingClassifier($http, new CircuitBreaker('bert'));

            return new CachedBertClassifier($protected);
        });

        $this->app->singleton(Explainer::class, function ($app): Explainer {
            $quota = $app->make(OpenAiQuota::class);

            return new OpenAiExplainer(new CircuitBreaker('openai'), $quota);
        });
        $this->app->singleton(TextLanguageDetector::class);
        $this->app->singleton(Translator::class, function ($app): Translator {
            return new OpenAiTranslator(
                $app->make(TextLanguageDetector::class),
                new CircuitBreaker('openai'),
                $app->make(OpenAiQuota::class),
            );
        });
        // D6: Tagged bindings for extractors — each extractor is bound and tagged, then
        // the resolver receives the whole collection via $app->tagged('extractors').
        // This makes the extractor pipeline extensible without editing the resolver wiring.
        $this->app->singleton(StoredTextExtractor::class, fn () => new StoredTextExtractor);
        $this->app->singleton(TextInputExtractor::class, fn () => new TextInputExtractor);
        $this->app->singleton(ArticleExtractor::class, fn () => new ArticleExtractor);
        $this->app->singleton(OpenAiImageExtractor::class, function ($app) {
            $quota = $app->make(OpenAiQuota::class);

            return new OpenAiImageExtractor(new CircuitBreaker('openai'), $quota);
        });
        $this->app->singleton(OpenAiVideoExtractor::class, function ($app) {
            $quota = $app->make(OpenAiQuota::class);

            return new OpenAiVideoExtractor(new CircuitBreaker('openai'), $quota);
        });
        $this->app->tag([
            StoredTextExtractor::class,
            TextInputExtractor::class,
            ArticleExtractor::class,
            OpenAiImageExtractor::class,
            OpenAiVideoExtractor::class,
        ], 'extractors');

        $this->app->singleton(TextExtractorResolver::class, function ($app): TextExtractorResolver {
            return new TextExtractorResolver($app->tagged('extractors'));
        });

        $this->app->singleton(SubmissionStateMachine::class, function ($app) {
            return new SubmissionStateMachine($app->make(ProcessingEventRecorder::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(FilamentAuditObserver::class);
        Dataset::observe(FilamentAuditObserver::class);
        Submission::observe(FilamentAuditObserver::class);
    }
}
