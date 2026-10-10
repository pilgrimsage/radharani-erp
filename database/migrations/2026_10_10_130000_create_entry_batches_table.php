<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A batch is identified by the date and time of the entry (8 Oct change list, 1.3). Imports
// create one; the unassigned-items list groups pieces by it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_batches', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['import', 'manual'])->default('import');
            $table->string('note', 120)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('entry_batch_id')->nullable()->after('category_id')->constrained('entry_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $t) => $t->dropConstrainedForeignId('entry_batch_id'));
        Schema::dropIfExists('entry_batches');
    }
};
