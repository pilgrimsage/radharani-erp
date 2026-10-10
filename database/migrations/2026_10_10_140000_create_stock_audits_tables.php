<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Stock audit (8 Oct change list, 4.6): the owner or manager takes a box, scans every piece and
// marks it present, missing or extra. The result is kept as a record: who, when, discrepancies.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('box_id')->constrained('boxes');
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('expected_count')->default(0);
            $table->unsignedInteger('present_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('extra_count')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['box_id', 'created_at']);
        });

        Schema::create('stock_audit_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('code', 40);
            $table->enum('result', ['present', 'missing', 'extra']);
        });

        $permission = Permission::firstOrCreate(['name' => 'stock.audit', 'guard_name' => 'web']);
        foreach (['owner', 'manager'] as $name) {
            Role::where('name', $name)->first()?->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_lines');
        Schema::dropIfExists('stock_audits');
        Permission::where('name', 'stock.audit')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
