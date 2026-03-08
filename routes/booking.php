<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/bookings', 'pages::bookings.index')->name('bookings.index');
    Route::livewire('/bookings/{booking}', 'pages::bookings.show')->name('bookings.show');
});
