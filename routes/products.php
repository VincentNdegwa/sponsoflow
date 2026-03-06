<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('products')->group(function () {
        Route::livewire('/', 'pages::products.index')->name('products.index');
        Route::livewire('/create', 'pages::products.create')->name('products.create');
        Route::livewire('/{product}', 'pages::products.show')->name('products.show');
        Route::livewire('/{product}/calendar', 'pages::products.calendar')->name('products.calendar');
        Route::livewire('/{product}/edit', 'pages::products.edit')->name('products.edit');
    });
});