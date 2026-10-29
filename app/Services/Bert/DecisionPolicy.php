<?php

namespace App\Services\Bert;

use InvalidArgumentException;

final class DecisionPolicy
{
    public static function validate(array $payload): void
    {
        $decision = $payload['decision'] ?? null;
        if (! is_array($decision) || ($decision['schema_version'] ?? null) !== 'bert-decision-v1') {
            throw new InvalidArgumentException('Unsupported BERT decision contract.');
        }
        $fingerprint = $decision['fingerprint'] ?? null;
        if (! is_string($fingerprint) || ! preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            throw new InvalidArgumentException('Invalid BERT policy fingerprint.');
        }
        $expected = config('services.bert.policy_fingerprint');
        if (filled($expected) && ! hash_equals((string) $expected, $fingerprint)) {
            throw new InvalidArgumentException('BERT policy identity mismatch.');
        }
        $threshold = $decision['threshold'] ?? null;
        if (! is_numeric($threshold) || ! is_finite((float) $threshold) || $threshold < 0 || $threshold > 1) {
            throw new InvalidArgumentException('Invalid BERT release threshold.');
        }
        foreach (['calibrated_scores', 'uncalibrated_scores'] as $field) {
            $scores = $decision[$field] ?? [];
            if (! is_array($scores) || count($scores) !== 2) {
                throw new InvalidArgumentException('Invalid binary score contract.');
            }
            foreach (['valid', 'hoax'] as $label) {
                if (! isset($scores[$label]) || ! is_numeric($scores[$label]) || ! is_finite((float) $scores[$label]) || $scores[$label] < 0 || $scores[$label] > 1) {
                    throw new InvalidArgumentException('Invalid binary score.');
                }
            }
            if (abs(array_sum($scores) - 1) > 0.00001) {
                throw new InvalidArgumentException('Binary scores must sum to one.');
            }
        }
        $scores = $decision['calibrated_scores'];
        $argmax = $scores['valid'] >= $scores['hoax'] ? 'valid' : 'hoax';
        $confidence = (float) $scores[$argmax];
        $abstains = $confidence < $threshold;
        $label = $abstains ? 'meragukan' : $argmax;
        if (($decision['raw_argmax'] ?? null) !== $argmax
            || ($decision['served_label'] ?? null) !== $label
            || ($payload['label'] ?? null) !== $label
            || ! array_key_exists('abstention_reason', $decision)
            || $decision['abstention_reason'] !== ($abstains ? 'below_threshold' : null)
            || abs((float) ($payload['confidence_score'] ?? -1) - $confidence) > 0.000001
            || ($payload['raw_scores'] ?? null) != $scores) {
            throw new InvalidArgumentException('Inconsistent BERT decision response.');
        }
    }
}
