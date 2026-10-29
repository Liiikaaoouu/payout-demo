<?php

use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/payouts', [PayoutController::class, 'store']);
Route::post('/webhooks/bank', [WebhookController::class, 'store']);
