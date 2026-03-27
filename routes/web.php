<?php

use App\Http\Controllers\Admin\AdminAuthenticatedSessionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\StripeConnectController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Payment routes
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');

// Payment webhooks (should be in api.php in production)
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripeWebhook'])->name('webhooks.stripe');
Route::post('/webhooks/paystack', [PaymentWebhookController::class, 'paystackWebhook'])->name('webhooks.paystack');

// Paystack callback route
Route::get('/payment/paystack/callback', [PaymentWebhookController::class, 'paystackCallback'])->name('payment.paystack.callback');

// Payment management r
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/payments/{booking}/release', [PaymentWebhookController::class, 'releaseFunds'])->name('payments.release');
    Route::post('/payments/{booking}/refund', [PaymentWebhookController::class, 'refundPayment'])->name('payments.refund');
    Route::get('/paystack/banks', [PaymentWebhookController::class, 'getSupportedBanks'])->name('paystack.banks');
    Route::post('/paystack/verify-account', [PaymentWebhookController::class, 'verifyBankAccount'])->name('paystack.verify');
});

// Stripe Connect routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/stripe/connect/{workspace}', [StripeConnectController::class, 'create'])->name('stripe.connect.create');
    Route::get('/stripe/connect/return', [StripeConnectController::class, 'return'])->name('stripe.connect.return');
    Route::get('/stripe/connect/refresh', [StripeConnectController::class, 'refresh'])->name('stripe.connect.refresh');
    Route::get('/stripe/connect/{workspace}/status', [StripeConnectController::class, 'checkStatus'])->name('stripe.connect.status');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});

// Admin routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['admin.access'])->group(function () {
        Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
        Route::livewire('/users', 'pages::admin.users')->name('users');
        Route::livewire('/users/{user}', 'pages::admin.users.show')->name('users.show');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/products.php';
require __DIR__.'/booking.php';
require __DIR__.'/campaigns.php';
require __DIR__.'/marketplace.php';
require __DIR__.'/public.php';
