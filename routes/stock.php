<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Stock\BoxManager;
use App\Livewire\Stock\ContainerList;
use App\Livewire\Stock\CategoryTree;
use App\Livewire\Stock\UnassignedItems;
use App\Livewire\Stock\ProductSummary;
use App\Livewire\Stock\HuidExchange;
use App\Livewire\Stock\StockAuditPage;
use App\Livewire\Stock\BoxDetail;
use App\Livewire\Stock\PacketManager;
use App\Livewire\Stock\PacketDetail;
use App\Livewire\Stock\ItemManager;
use App\Livewire\Stock\ItemDetail;
use App\Livewire\Stock\AssignToContainer;
use App\Livewire\Stock\QrGenerator;
use App\Livewire\Stock\BulkImport;
use App\Livewire\Stock\HierarchyConfigurator;
use App\Http\Controllers\Stock\QrController;

// Stock module — behind auth + the stock.manage permission gate.
Route::middleware(['auth', 'permission:stock.manage'])->prefix('stock')->group(function () {
    Route::get('/audit', StockAuditPage::class)->middleware('permission:stock.audit')->name('stock.audit');
    Route::get('/huid', HuidExchange::class)->name('stock.huid');
    Route::get('/summary', ProductSummary::class)->name('stock.summary');
    Route::get('/unassigned', UnassignedItems::class)->name('stock.unassigned');
    Route::get('/categories', CategoryTree::class)->middleware('permission:category.manage')->name('stock.categories');
    Route::get('/boxes', ContainerList::class)->name('stock.boxes');
    Route::get('/boxes/{box}', BoxDetail::class)->name('stock.boxes.show');

    Route::get('/packets', ContainerList::class)->name('stock.packets');
    Route::get('/packets/{packet}', PacketDetail::class)->name('stock.packets.show');

    Route::get('/items', ItemManager::class)->name('stock.items');
    Route::get('/items/{item}', ItemDetail::class)->name('stock.items.show');

    Route::get('/assign', AssignToContainer::class)->name('stock.assign');
    Route::get('/qr-codes', QrGenerator::class)->name('stock.qr-codes');
    Route::get('/qr-codes/export', [QrController::class, 'export'])->name('stock.qr.export');
    Route::get('/qr-codes/print', [QrController::class, 'print'])->name('stock.qr.print');
    // What printed stickers encode: logs the scan, then opens the detail page.
    Route::get('/q/{code}', [QrController::class, 'resolve'])->name('stock.qr.resolve');
    // The top-bar camera scanner: any HUID, internal code, packet/box code or sticker opens its detail page.
    Route::get('/scan', [QrController::class, 'lookup'])->name('stock.scan');
    Route::get('/import', BulkImport::class)->name('stock.import');
    Route::get('/configurator', HierarchyConfigurator::class)->name('stock.configurator');
});
