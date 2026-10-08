<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Purchase\VendorManager;
use App\Livewire\Purchase\NewPurchaseEntry;
use App\Livewire\Purchase\PurchaseList;

Route::middleware(['auth'])->prefix('purchases')->group(function () {
    // Hidden: vendors/suppliers (8 Oct change list, 18.1). Karigars and hallmark centres stay as named parties.
    // Route::get('/vendors', VendorManager::class)
    //     ->middleware('permission:purchase.manage')->name('purchases.vendors');

    Route::get('/new', NewPurchaseEntry::class)
        ->middleware('permission:purchase.manage')->name('purchases.new');

    Route::get('/', PurchaseList::class)
        ->middleware('permission:purchase.manage')->name('purchases.list');
});
