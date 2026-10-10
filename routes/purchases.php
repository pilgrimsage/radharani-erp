<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Purchase\NewPurchaseEntry;
use App\Livewire\Purchase\PurchaseList;

Route::middleware(['auth'])->prefix('purchases')->group(function () {
    Route::get('/new', NewPurchaseEntry::class)
        ->middleware('permission:purchase.manage')->name('purchases.new');

    Route::get('/', PurchaseList::class)
        ->middleware('permission:purchase.manage')->name('purchases.list');
});
