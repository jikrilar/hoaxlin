<?php

namespace App\Services\Bert;

use App\Contracts\Classifier;
use App\DataObjects\Classification;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\Cache;

class CachedBertClassifier implements Classifier
{
    public function __construct(private readonly Classifier $classifier) {}

    public function classify(string $text): Classification
    {
        $key = 'bert:'.sha1(implode('|', [
            config('services.bert.model_version', 'unknown'),
            config('app.ai_normalizer_version', '1.0'),
            TextNormalizer::normalize($text),
        ]));

        if ($cached = Cache::get($key)) {
            return Classification::fromResponse($cached, cached: true);
        }

        $result = $this->classifier->classify($text);
        Cache::put($key, $result->toArray(), config('services.bert.cache_ttl'));

        return $result;
    }
}
