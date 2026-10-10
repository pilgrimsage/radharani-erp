<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karigar_raw_batches', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('close_note');
        });
    }

    public function down(): void
    {
        Schema::table('karigar_raw_batches', fn (Blueprint $t) => $t->dropColumn('photo_path'));
    }
};
