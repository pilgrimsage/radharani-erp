<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Owner-managed in-store locations (8 Oct change list, 3.1): Vault, Counter 1, Counter 2,
// Display and so on. movements.location_id records where a vault_out went; a later
// vault_out to a different location is a place change. vault_in always means the vault.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->enum('type', ['vault', 'counter', 'display', 'other'])->default('counter');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('movements', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('trackable_id')->constrained('locations')->nullOnDelete();
        });

        $now = now();
        DB::table('locations')->insert([
            ['name' => 'Vault', 'type' => 'vault', 'sort_order' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Counter 1', 'type' => 'counter', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Display', 'type' => 'display', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Existing vault_out rows are deliberately not touched (movements are insert-only):
        // a null location_id on a vault_out reads as the first counter.
    }

    public function down(): void
    {
        Schema::table('movements', fn (Blueprint $t) => $t->dropConstrainedForeignId('location_id'));
        Schema::dropIfExists('locations');
    }
};
