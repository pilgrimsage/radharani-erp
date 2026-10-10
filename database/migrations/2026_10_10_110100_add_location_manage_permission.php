<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Owner-only: who may add, rename and switch off in-store locations (8 Oct change list, 3.1).
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'location.manage', 'guard_name' => 'web']);
        Role::where('name', 'owner')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'location.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
