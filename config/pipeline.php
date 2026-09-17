<?php

return [
    /*
    | Processing must remain untouched long enough for the longest queue
    | reservation (media: 30 minutes) plus operational headroom.
    */
    'stale_after_minutes' => (int) env('PIPELINE_STALE_AFTER_MINUTES', 45),
];
