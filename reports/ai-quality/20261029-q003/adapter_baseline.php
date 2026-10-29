<?php

use App\Contracts\Classifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'services.bert.url' => 'http://127.0.0.1:8001',
    'services.bert.internal_token' => 'q003-isolated-fixture-token',
    'services.bert.model_version' => 'v1.0.0',
    'services.bert.confidence_threshold' => 0.99,
    'services.bert.timeout' => 30,
    'cache.default' => 'array',
]);
$handle = fopen('/workspace/datasets/challenge/external-challenge-v1/challenge.csv', 'r');
$fields = fgetcsv($handle, escape: '');
$fields[0] = ltrim($fields[0], "\xEF\xBB\xBF");
$rows = [];
while (($values = fgetcsv($handle, escape: '')) !== false) {
    $rows[] = array_combine($fields, $values);
}
fclose($handle);
$records = [];
Http::record();
$classify = $app->make(Classifier::class);
$version = Http::get('http://127.0.0.1:8001/version')->throw()->json();
foreach ([0, 20, 40, 60, 80, 100] as $index) {
    $row = $rows[$index];
    $direct = Http::withToken('q003-isolated-fixture-token')
        ->post('http://127.0.0.1:8001/predict', ['text' => $row['text']])->throw()->json();
    $before = Http::recorded()->count();
    $start = microtime(true);
    $cold = $classify->classify($row['text']);
    $coldSeconds = microtime(true) - $start;
    $afterCold = Http::recorded()->count();
    $start = microtime(true);
    $warm = $classify->classify($row['text']);
    $warmSeconds = microtime(true) - $start;
    $afterWarm = Http::recorded()->count();
    if ($cold->cached || ! $warm->cached || $afterCold - $before !== 1 || $afterWarm !== $afterCold) {
        throw new RuntimeException('Unexpected cache cold/warm HTTP boundary.');
    }
    foreach ([$cold, $warm] as $result) {
        if ($result->label->value !== $direct['label'] || $result->modelVersion !== $direct['model_version']) {
            throw new RuntimeException('Direct-service/adapter label or version mismatch.');
        }
        foreach (['valid', 'hoax'] as $label) {
            if (abs($result->rawScores[$label] - $direct['raw_scores'][$label]) > 1e-6) {
                throw new RuntimeException('Direct-service/adapter score mismatch.');
            }
        }
    }
    $records[] = [
        'id' => $row['id'], 'text_sha256' => hash('sha256', $row['text']),
        'direct_service' => $direct, 'cold' => $cold->toArray(), 'warm' => $warm->toArray(),
        'cold_http_calls' => $afterCold - $before, 'warm_http_calls' => $afterWarm - $afterCold,
        'cold_seconds' => $coldSeconds, 'warm_seconds' => $warmSeconds,
    ];
}
$sources = [];
foreach (['app/Services/Bert/BertClassifier.php', 'app/Services/Bert/CachedBertClassifier.php',
    'app/Services/Bert/CircuitBreakingClassifier.php', 'app/DataObjects/Classification.php',
    'app/Enums/DetectionLabel.php', 'app/Support/TextNormalizer.php', 'app/Providers/AppServiceProvider.php',
    'config/services.php', 'composer.lock'] as $path) {
    $sources[$path] = hash_file('sha256', '/var/www/html/'.$path);
}
echo json_encode([
    'task' => 'Q-003', 'status' => 'DONE', 'php' => PHP_VERSION,
    'laravel' => $app->version(), 'service_version' => $version,
    'selection' => 'Fixed row indices 0,20,40,60,80,100 before adapter inference; one per historical source.',
    'cache_store' => 'array isolated process', 'numeric_tolerance' => 1e-6,
    'source_sha256' => $sources, 'records' => $records,
    'scope' => 'Real HTTP + bound Classifier decorators; no database, submission pipeline, Redis, OpenAI or production cache.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
