<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Exchange\ExchangeList;
use App\Livewire\Exchange\NewEntry;
use App\Livewire\Exchange\StatusTracker;
use App\Livewire\Exchange\TransactionDetail;
use App\Livewire\Exchange\AccountsValuation;
use App\Livewire\Exchange\RefineryBatchSend;
use App\Livewire\Exchange\RefineryBatchReturn;

// Old Gold/Silver Exchange & Refinery.
Route::middleware(['auth', 'permission:exchange.manage'])->prefix('exchange')->name('exchange.')->group(function () {
    Route::get('/', ExchangeList::class)->name('list');
    Route::get('/new', NewEntry::class)->name('new');
    Route::get('/tracker', StatusTracker::class)->name('tracker');
    Route::get('/transactions/{transaction}', TransactionDetail::class)->name('transactions.show');
    Route::get('/valuation', AccountsValuation::class)->name('valuation');
    Route::get('/refinery/send', RefineryBatchSend::class)->name('refinery.send');
    Route::get('/refinery/return', RefineryBatchReturn::class)->name('refinery.return');
});
