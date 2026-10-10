<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Pricing\DailyRateEntry;
use App\Livewire\Pricing\RateHistoryLog;
use App\Livewire\Pricing\PriceSimulator;
use App\Livewire\Pricing\PricingRules;

Route::middleware(['auth'])->prefix('pricing')->name('pricing.')->group(function () {
    Route::get('/rates', DailyRateEntry::class)->middleware('permission:rate.update')->name('rates');
    Route::get('/rates/history', RateHistoryLog::class)->middleware('permission:rate.update')->name('rates.history');
    Route::get('/simulator', PriceSimulator::class)->middleware('permission:rate.update')->name('simulator');
    Route::get('/rules', PricingRules::class)->middleware('permission:rate.update')->name('rules');
});
