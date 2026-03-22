<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/campaigns', 'pages::campaigns.index')->name('campaigns.index');
    Route::livewire('/campaigns/create', 'pages::campaigns.create')->name('campaigns.create');
});
