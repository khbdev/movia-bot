<?php

use App\Http\Controllers\TelegramBotController;
use Illuminate\Support\Facades\Route;




Route::post('/telegram/webhook', [TelegramBotController::class, 'handle']);