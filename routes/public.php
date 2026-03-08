<?php

use Illuminate\Support\Facades\Route;

Route::prefix('creator')->group(function () {
    Route::livewire('/{user:public_slug}', 'pages::public.creator.show')->name('creator.show');
    Route::post('/{user:public_slug}/reserve', 'PublicBookingController@reserve')->name('creator.reserve');
    Route::post('/{user:public_slug}/checkout', 'PublicBookingController@checkout')->name('creator.checkout');
});

// Use our new payment routes instead of the old booking ones
Route::get('/booking/success', [App\Http\Controllers\PaymentController::class, 'success'])->name('booking.success');
Route::get('/booking/cancel', [App\Http\Controllers\PaymentController::class, 'cancel'])->name('booking.cancel');
Route::post('/webhooks/stripe', 'StripeWebhookController@handle')->name('webhooks.stripe');
