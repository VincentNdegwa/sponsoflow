<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/bookings', 'pages::bookings.index')->name('bookings.index');
    Route::livewire('/bookings/{booking}', 'pages::bookings.show')->name('bookings.show');
});

Route::livewire('/review/{token}', 'pages::bookings.guest-review')->name('bookings.guest-review');
Route::livewire('/inquiry/{token}', 'pages::bookings.inquiry-respond')->name('bookings.inquiry-respond');
