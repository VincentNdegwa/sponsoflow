<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/marketplace', 'pages::marketplace.index')->name('marketplace.index');
});
