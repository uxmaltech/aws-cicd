<?php

use Illuminate\Support\Facades\Route;
use Uxmal\Devtools\Http\Controllers\GithubWebhookController;




// Route::controller(GithubWebhookController::class)->group(function ($router) {
//     $router->post('/github/webhook', 'handle')->name('github.webhook');
//     $router->get('/github/webhook', 'test')->name('github.webhook.test');
//     Route::get('/test', 'test')->name('github.test');
// })->middleware(['web']);

Route::get('/test', function () {
    dd('test');
})
    ->middleware(['web', 'auth', 'api'])
    ->name('github.test');
