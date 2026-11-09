<?php

use App\Http\Controllers\Api\BertCallbackController;
use App\Http\Middleware\VerifyBertInternalToken;
use Illuminate\Support\Facades\Route;

Route::post('/internal/bert/callback', BertCallbackController::class)
    ->middleware(VerifyBertInternalToken::class)
    ->name('internal.bert.callback');
