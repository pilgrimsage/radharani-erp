<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Purchases hold no money any more (8 Oct change list, section 13), so a line has no rate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('rate', 12, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
    }
};
