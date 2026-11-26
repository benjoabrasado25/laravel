<?php

use App\Http\Controllers\PaymentWebhookController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

// API routes are prefixed with /api and have no sessions or CSRF check,
// which suits a server-to-server webhook. The signature check replaces CSRF.
Route::post('/webhooks/payments', PaymentWebhookController::class)
    ->middleware(VerifyWebhookSignature::class)
    ->name('webhooks.payments');
