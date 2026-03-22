<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/campaigns', 'pages::campaigns.index')->name('campaigns.index');
    Route::livewire('/campaigns/create', 'pages::campaigns.create')->name('campaigns.create');
    Route::livewire('/campaigns/{campaign}/edit', 'pages::campaigns.create')->name('campaigns.edit');
    Route::livewire('/campaigns/categories', 'pages::campaigns.categories')->name('campaigns.categories');
    Route::livewire('/campaigns/deliverable-options', 'pages::campaigns.deliverable-options')->name('campaigns.deliverable-options');
    Route::livewire('/campaigns/templates', 'pages::campaigns.templates')->name('campaigns.templates');
});
