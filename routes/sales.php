<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Sales\NewSale;
use App\Livewire\Sales\SaleVerificationQueue;
use App\Livewire\Sales\InvoiceView;
use App\Livewire\Sales\SalesHistory;

Route::middleware(['auth'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/new', NewSale::class)->name('new');
    Route::get('/verification', SaleVerificationQueue::class)->middleware('permission:sale.approve')->name('verification');
    Route::get('/history', SalesHistory::class)->name('history');
    Route::get('/{sale}/bill', fn (\App\Models\Sales\Sale $sale) => view('sales.bill', ['sale' => $sale->load('customer', 'items')]))->name('bill');
    Route::get('/{sale}', InvoiceView::class)->name('invoice');
});
