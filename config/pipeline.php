<?php

return [
    /*
    | Processing must remain untouched long enough for the longest queue
    | reservation (media: 30 minutes) plus operational headroom.
    */
    'stale_after_minutes' => (int) env('PIPELINE_STALE_AFTER_MINUTES', 45),

    /*
    | Dashboard latency percentiles hide themselves below this sample count so
    | a handful of runs never reads as a p95 trend.
    */
    'latency_minimum_sample' => (int) env('PIPELINE_LATENCY_MINIMUM_SAMPLE', 20),

    'retry' => [
        'backoff' => [5, 15, 60],
        'max_delay_seconds' => (int) env('PIPELINE_MAX_RETRY_DELAY', 900),
    ],
];
