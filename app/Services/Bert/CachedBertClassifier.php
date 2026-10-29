<?php

namespace App\Services\Bert;

use App\Contracts\Classifier;
use App\DataObjects\Classification;
use Illuminate\Support\Facades\Cache;

class CachedBertClassifier implements Classifier
{
    public function __construct(private readonly Classifier $classifier) {}

    public function classify(string $text): Classification
    {
        $fingerprint = config('services.bert.policy_fingerprint');
        // No immutable deployment identity: do not risk reusing stale predictions.
        if (! is_string($fingerprint) || ! preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            return $this->classifier->classify($text);
        }
        $key = 'bert:v2:'.$fingerprint.':'.hash('sha256', $text);

        if ($cached = Cache::get($key)) {
            DecisionPolicy::validate($cached);

            return Classification::fromResponse($cached, cached: true);
        }

        $result = $this->classifier->classify($text);
        DecisionPolicy::validate($result->toArray());
        Cache::put($key, $result->toArray(), config('services.bert.cache_ttl'));

        return $result;
    }
}
