<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Pricing\DailyRateEntry;
use App\Livewire\Pricing\RateHistoryLog;
use App\Livewire\Pricing\MakingChargeConfig;
use App\Livewire\Pricing\DiscountRulesManager;
use App\Livewire\Pricing\AdditionalChargesConfig;
use App\Livewire\Pricing\PriceSimulator;
use App\Livewire\Pricing\PricingRules;

Route::middleware(['auth'])->prefix('pricing')->name('pricing.')->group(function () {
    Route::get('/rates', DailyRateEntry::class)->middleware('permission:rate.update')->name('rates');
    Route::get('/rates/history', RateHistoryLog::class)->middleware('permission:rate.update')->name('rates.history');
    Route::get('/simulator', PriceSimulator::class)->middleware('permission:rate.update')->name('simulator');
    Route::get('/rules', PricingRules::class)->middleware('permission:rate.update')->name('rules');
    Route::get('/making-charges', MakingChargeConfig::class)->middleware('permission:rate.update')->name('making-charges');
    Route::get('/discounts', DiscountRulesManager::class)->middleware('permission:discount.manage')->name('discounts');
    Route::get('/additional-charges', AdditionalChargesConfig::class)->middleware('permission:rate.update')->name('additional-charges');
});
