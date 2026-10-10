<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\EmployeeManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\Admin\RoleManager;
use App\Livewire\Admin\LoyaltySettingsManager;
use App\Livewire\Admin\ReferralOverview;
use App\Livewire\Admin\CustomerManager;
use App\Livewire\Admin\CustomerDetail;
use App\Livewire\Admin\CustomerBulkImport;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\LocationManager;
use App\Livewire\Admin\PartyManager;

// Owner/manager only — gated by Spatie permissions, seeded via RolePermissionSeeder.
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/employees', EmployeeManager::class)
        ->middleware('permission:employee.manage')->name('admin.employees');

    Route::get('/users', UserManager::class)
        ->middleware('permission:user.manage')->name('admin.users');

    Route::get('/parties', PartyManager::class)->middleware('permission:purchase.manage')->name('admin.parties');
    Route::get('/locations', LocationManager::class)
        ->middleware('permission:location.manage')->name('admin.locations');

    Route::get('/roles', RoleManager::class)
        ->middleware('permission:role.manage')->name('admin.roles');

    // Hidden: Loyalty settings (8 Oct change list, 15.1)
    // Route::get('/loyalty-settings', LoyaltySettingsManager::class)
    //     ->middleware('permission:referral.manage')->name('admin.loyalty-settings');

    Route::get('/referrals', ReferralOverview::class)
        ->middleware('permission:referral.manage')->name('admin.referrals');

    Route::get('/customers', CustomerManager::class)
        ->middleware('permission:customer.manage')->name('admin.customers');

    Route::get('/customers/import', CustomerBulkImport::class)
        ->middleware('permission:customer.manage')->name('admin.customers.import');

    Route::get('/customers/{customer}', CustomerDetail::class)
        ->middleware('permission:customer.manage')->name('admin.customers.detail');

    Route::get('/audit-log', AuditLogViewer::class)
        ->middleware('permission:audit.view')->name('admin.audit-log');
});
