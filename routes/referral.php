<?php

use App\Livewire\Referral\ReferralDesk;
use Illuminate\Support\Facades\Route;

// Referral replaces Loyalty (8 Oct change list, section 15).
Route::middleware(['auth', 'permission:referral.manage'])->get('/referral', ReferralDesk::class)->name('referral');
