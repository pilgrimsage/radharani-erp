<?php

use App\Http\Controllers\LedgerDownloadController;
use App\Livewire\Ledgers\LedgerIndex;
use App\Livewire\Ledgers\LedgerParty;
use Illuminate\Support\Facades\Route;

// Ledgers hold weights and counts only (8 Oct change list, section 14).
Route::middleware(['auth', 'permission:movement.create'])->prefix('ledgers')->name('ledgers.')->group(function () {
    Route::get('/', LedgerIndex::class)->name('index');
    Route::get('/party/{party}', LedgerParty::class)->name('party');
    Route::get('/customer/{customer}/download/{format}', [LedgerDownloadController::class, 'customer'])->withoutMiddleware('permission:movement.create')->middleware('permission:customer.manage')->name('customer.download');
    Route::get('/party/{party}/download/{format}', LedgerDownloadController::class)->name('party.download');
});
