<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceException;
use App\Services\Bert\BertClassifier;
use App\Services\Bert\CachedBertClassifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BertDecisionPolicyTest extends TestCase
{
    private function payload(string $fingerprint): array
    {
        return [
            'label' => 'meragukan', 'confidence_score' => 0.98, 'model_version' => 'v1.0.0',
            'raw_scores' => ['valid' => 0.02, 'hoax' => 0.98],
            'token_count' => 15, 'truncated' => false, 'preprocessing_version' => '1.0.0',
            'decision' => [
                'schema_version' => 'bert-decision-v1', 'fingerprint' => $fingerprint,
                'threshold' => 0.99, 'raw_argmax' => 'hoax', 'served_label' => 'meragukan',
                'abstention_reason' => 'below_threshold',
                'uncalibrated_scores' => ['valid' => 0.03, 'hoax' => 0.97],
                'calibrated_scores' => ['valid' => 0.02, 'hoax' => 0.98],
            ],
        ];
    }

    public function test_release_decision_is_not_thresholded_again_and_survives_warm_cache(): void
    {
        Cache::flush();
        $fingerprint = str_repeat('a', 64);
        config(['services.bert.policy_fingerprint' => $fingerprint, 'services.bert.confidence_threshold' => 0.999]);
        $payload = $this->payload($fingerprint);
        $payload['label'] = $payload['decision']['served_label'] = 'hoax';
        $payload['decision']['threshold'] = 0.95;
        $payload['decision']['abstention_reason'] = null;
        Http::fake(['*' => Http::response($payload)]);
        $classifier = new CachedBertClassifier(new BertClassifier);
        $cold = $classifier->classify('Klaim TIDAK benar');
        $warm = $classifier->classify('Klaim TIDAK benar');
        $this->assertSame('hoax', $cold->label->value);
        $this->assertSame($cold->toArray(), array_replace($warm->toArray(), ['cached' => false]));
        $this->assertTrue($warm->cached);
        $this->assertSame(15, $warm->tokenCount);
        Http::assertSentCount(1);
    }

    public function test_exact_input_and_policy_change_do_not_share_cache(): void
    {
        Cache::flush();
        $a = str_repeat('a', 64);
        $b = str_repeat('b', 64);
        config(['services.bert.policy_fingerprint' => $a]);
        Http::fakeSequence()->push($this->payload($a))->push($this->payload($a))->push($this->payload($b));
        $classifier = new CachedBertClassifier(new BertClassifier);
        $classifier->classify('TIDAK  benar');
        $classifier->classify('tidak benar');
        config(['services.bert.policy_fingerprint' => $b]);
        $classifier->classify('TIDAK  benar');
        Http::assertSentCount(3);
    }

    public function test_policy_mismatch_fails_closed(): void
    {
        config(['services.bert.policy_fingerprint' => str_repeat('b', 64)]);
        Http::fake(['*' => Http::response($this->payload(str_repeat('a', 64)))]);
        $this->expectException(AiServiceException::class);
        (new BertClassifier)->classify('test');
    }

    public function test_legacy_requires_explicit_opt_in(): void
    {
        config(['services.bert.allow_legacy_response' => false]);
        $payload = $this->payload(str_repeat('a', 64));
        unset($payload['decision']);
        Http::fake(['*' => Http::response($payload)]);
        $this->expectException(AiServiceException::class);
        (new BertClassifier)->classify('test');
    }

    public function test_opted_in_legacy_is_uncached_and_does_not_recover_abstention(): void
    {
        Cache::flush();
        config(['services.bert.allow_legacy_response' => true, 'services.bert.policy_fingerprint' => null,
            'services.bert.confidence_threshold' => 0.1]);
        $payload = $this->payload(str_repeat('a', 64));
        unset($payload['decision']);
        Http::fake(['*' => Http::response($payload)]);
        $classifier = new CachedBertClassifier(new BertClassifier);
        $this->assertSame('meragukan', $classifier->classify('test')->label->value);
        $this->assertFalse($classifier->classify('test')->cached);
        Http::assertSentCount(2);
    }

    public function test_inconsistent_served_label_is_rejected(): void
    {
        $payload = $this->payload(str_repeat('a', 64));
        $payload['label'] = 'hoax';
        Http::fake(['*' => Http::response($payload)]);
        $this->expectException(AiServiceException::class);
        (new BertClassifier)->classify('test');
    }

    public function test_release_threshold_boundary_is_validated_without_local_override(): void
    {
        config(['services.bert.confidence_threshold' => 1.0]);
        $sequence = Http::fakeSequence();
        foreach ([0.989999, 0.99, 0.990001] as $confidence) {
            $payload = $this->payload(str_repeat('a', 64));
            $scores = ['valid' => 1 - $confidence, 'hoax' => $confidence];
            $payload['confidence_score'] = $confidence;
            $payload['raw_scores'] = $payload['decision']['calibrated_scores'] = $scores;
            $payload['label'] = $payload['decision']['served_label'] = $confidence < 0.99 ? 'meragukan' : 'hoax';
            $payload['decision']['abstention_reason'] = $confidence < 0.99 ? 'below_threshold' : null;
            $sequence->push($payload);
        }
        foreach ([0.989999, 0.99, 0.990001] as $confidence) {
            $result = (new BertClassifier)->classify('test');
            $this->assertSame($confidence < 0.99 ? 'meragukan' : 'hoax', $result->label->value);
            $this->assertSame($result->label, $result->withConfidenceThreshold(1.0)->label);
        }
    }

    public function test_legacy_opt_in_cannot_bypass_a_pinned_identity(): void
    {
        config(['services.bert.allow_legacy_response' => true, 'services.bert.policy_fingerprint' => str_repeat('a', 64)]);
        $payload = $this->payload(str_repeat('a', 64));
        unset($payload['decision']);
        Http::fake(['*' => Http::response($payload)]);
        $this->expectException(AiServiceException::class);
        (new BertClassifier)->classify('test');
    }
}
