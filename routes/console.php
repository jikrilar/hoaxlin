<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('media:prune')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();
Schedule::command('submissions:recover-stale')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping();
