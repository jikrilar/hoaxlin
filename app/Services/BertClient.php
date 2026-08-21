<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BertClient
{
    /**
     * @return array{label: string, confidence_score: float, model_version: string}
     *
     * @throws ConnectionException
     */
    public function classify(string $text): array
    {
        return Http::baseUrl(config('services.bert.url'))
            ->timeout(config('services.bert.timeout'))
            ->acceptJson()
            ->post('/predict', ['text' => $text])
            ->throw()
            ->json();
    }
}
