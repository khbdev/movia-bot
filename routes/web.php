<?php

use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/setwebhook', function () {
    $url = env('TELEGRAM_WEBHOOK_URL');
    return Telegram::setWebhook(['url' => $url]);
});




Route::get('/deletewebhook', function () {
    return Telegram::removeWebhook();
});