<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Soft delete for boxes, packets and items (8 Oct change list, 4.1 and 4.3): hidden from
// every list, but the row and its movement/audit history stay.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['boxes', 'packets', 'items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (['boxes', 'packets', 'items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
