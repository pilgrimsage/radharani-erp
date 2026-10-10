<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', Dashboard::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/stock.php';
require __DIR__.'/movements.php';
require __DIR__.'/exchange.php';
require __DIR__.'/orders.php';
require __DIR__.'/pricing.php';
require __DIR__.'/sales.php';
require __DIR__.'/purchases.php';
require __DIR__.'/notifications.php';
require __DIR__.'/installments.php';
require __DIR__.'/reports.php';
require __DIR__.'/ledgers.php';
require __DIR__.'/referral.php';
require __DIR__.'/storefront.php';
require __DIR__.'/website.php';
require __DIR__.'/admin.php';
require __DIR__.'/wireframes.php';
require __DIR__.'/portal.php';
