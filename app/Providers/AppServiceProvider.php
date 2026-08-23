<?php

namespace App\Providers;

use App\Contracts\Classifier;
use App\Contracts\Explainer;
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
use App\Services\Resilience\CircuitBreaker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CircuitBreaker::class, fn ($app, array $parameters) => new CircuitBreaker($parameters['service'] ?? 'bert'));
        $this->app->singleton(\App\Services\OpenAI\OpenAiQuota::class, fn () => new \App\Services\OpenAI\OpenAiQuota);

        $this->app->singleton(Classifier::class, function ($app): Classifier {
            $http = new BertClassifier;
            $protected = new CircuitBreakingClassifier($http, new CircuitBreaker('bert'));

            return new CachedBertClassifier($protected);
        });

        $this->app->singleton(Explainer::class, function ($app): Explainer {
            $quota = $app->make(\App\Services\OpenAI\OpenAiQuota::class);
            return new OpenAiExplainer(new CircuitBreaker('openai'), $quota);
        });
        // D6: Tagged bindings for extractors — each extractor is bound and tagged, then
        // the resolver receives the whole collection via $app->tagged('extractors').
        // This makes the extractor pipeline extensible without editing the resolver wiring.
        $this->app->singleton(StoredTextExtractor::class, fn () => new StoredTextExtractor);
        $this->app->singleton(TextInputExtractor::class, fn () => new TextInputExtractor);
        $this->app->singleton(ArticleExtractor::class, fn () => new ArticleExtractor);
        $this->app->singleton(OpenAiImageExtractor::class, function ($app) {
            $quota = $app->make(\App\Services\OpenAI\OpenAiQuota::class);
            return new OpenAiImageExtractor(new CircuitBreaker('openai'), $quota);
        });
        $this->app->singleton(OpenAiVideoExtractor::class, function ($app) {
            $quota = $app->make(\App\Services\OpenAI\OpenAiQuota::class);
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

        $this->app->singleton(\App\Services\Pipeline\SubmissionStateMachine::class, function ($app) {
            return new \App\Services\Pipeline\SubmissionStateMachine($app->make(\App\Services\Pipeline\ProcessingEventRecorder::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\User::observe(\App\Observers\FilamentAuditObserver::class);
        \App\Models\Dataset::observe(\App\Observers\FilamentAuditObserver::class);
        \App\Models\Submission::observe(\App\Observers\FilamentAuditObserver::class);
    }
}
