<?php

use Illuminate\Support\Facades\Route;
use Uxmal\Devtools\Http\Controllers\GithubWebhookController;


Route::prefix("devtools")->controller(GithubWebhookController::class)->group(function () {

    Route::post('github/webhook', 'handle')->name('devtools.github.webhook');
});
