<?php

use Illuminate\Support\Facades\Route;

Route::prefix('creator')->group(function () {
    Route::livewire('/{user:public_slug}', 'pages::public.creator.show')->name('creator.show');
    Route::post('/{user:public_slug}/reserve', 'PublicBookingController@reserve')->name('creator.reserve');
    Route::post('/{user:public_slug}/checkout', 'PublicBookingController@checkout')->name('creator.checkout');
});

Route::get('/booking/success', 'PublicBookingController@success')->name('booking.success');
Route::get('/booking/cancel', 'PublicBookingController@cancel')->name('booking.cancel');
Route::post('/webhooks/stripe', 'StripeWebhookController@handle')->name('webhooks.stripe');
