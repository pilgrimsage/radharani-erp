<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Movement\VaultCounterMove;
use App\Livewire\Movement\KarigarDispatch;
use App\Livewire\Movement\KarigarReturn;
use App\Livewire\Movement\HallmarkDispatch;
use App\Livewire\Movement\HallmarkReturn;
use App\Livewire\Movement\CustomPurposeMove;
use App\Livewire\Movement\PendingReviewQueue;
use App\Livewire\Movement\MovementLog;

// Movements module — every stock-location change, append-only downstream
// via the movements table. See CLAUDE.md non-negotiable rule 1.
Route::middleware(['auth'])->prefix('movements')->name('movements.')->group(function () {
    Route::get('/vault-counter', VaultCounterMove::class)->name('vault-counter');
    Route::get('/karigar', \App\Livewire\Movement\KarigarDesk::class)->name('karigar');
    Route::get('/karigar/batch/{batch}/report', fn (\App\Models\Movement\KarigarRawBatch $batch) => view('movements.karigar-report', ['batch' => $batch->load('vendor', 'user')]))->name('karigar.print');
    // The old separate screens now live inside the one Karigar screen.
    Route::redirect('/karigar-dispatch', '/movements/karigar?tab=issue')->name('karigar-dispatch');
    Route::redirect('/karigar-return', '/movements/karigar?tab=receive')->name('karigar-return');
    Route::get('/hallmark', \App\Livewire\Movement\HallmarkDesk::class)->name('hallmark');
    Route::redirect('/hallmark-dispatch', '/movements/hallmark?tab=dispatch')->name('hallmark-dispatch');
    Route::redirect('/hallmark-return', '/movements/hallmark?tab=receive')->name('hallmark-return');
    Route::get('/custom-purpose', CustomPurposeMove::class)->name('custom-purpose');
    // #9: only admins close pending items into stock.
    Route::get('/pending-review', PendingReviewQueue::class)->middleware('permission:movement.approve')->name('pending-review');
    Route::get('/log', MovementLog::class)->name('log');
});
