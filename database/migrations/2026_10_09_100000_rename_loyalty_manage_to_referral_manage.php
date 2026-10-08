<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Loyalty is replaced by Referral (8 Oct change list, 15.1). Renamed in place so
// every role that held loyalty.manage keeps the access under the new name.
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'loyalty.manage')->where('guard_name', 'web')
            ->update(['name' => 'referral.manage']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'referral.manage')->where('guard_name', 'web')
            ->update(['name' => 'loyalty.manage']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
