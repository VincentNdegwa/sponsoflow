<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/bookings', 'pages::bookings.index')->name('bookings.index');
    Route::livewire('/bookings/create', 'pages::bookings.create')->name('bookings.create');
    Route::livewire('/bookings/{booking:uuid}', 'pages::bookings.show')->name('bookings.show');
});

Route::livewire('/review/{token}', 'pages::bookings.guest-review')->name('bookings.guest-review');
Route::livewire('/inquiry/{token}', 'pages::bookings.inquiry-respond')->name('bookings.inquiry-respond');
Route::livewire('/booking/{token}', 'pages::bookings.invite')->name('bookings.invite');
