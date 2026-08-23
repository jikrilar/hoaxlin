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
        $this->app->singleton(TextExtractorResolver::class, function ($app): TextExtractorResolver {
            $quota = $app->make(\App\Services\OpenAI\OpenAiQuota::class);
            $openaiBreaker = new CircuitBreaker('openai');
            return new TextExtractorResolver([
                new StoredTextExtractor,
                new TextInputExtractor,
                new ArticleExtractor,
                new OpenAiImageExtractor($openaiBreaker, $quota),
                new OpenAiVideoExtractor($openaiBreaker, $quota),
            ]);
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
