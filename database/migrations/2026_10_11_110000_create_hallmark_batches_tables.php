<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hallmarking rebuild (8 Oct change list, section 7). A batch goes to one centre, identified
// by its date and time. It can hold untagged pieces by count (usually straight from a karigar)
// and already-tagged stock pieces (item-based). Pieces return in as many receipts as it takes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hallmark_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors'); // the hallmarking centre
            $table->enum('source', ['stock', 'order'])->default('stock');
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('description', 150)->nullable();
            $table->unsignedInteger('pieces_counted')->default(0);     // untagged pieces sent by count
            $table->decimal('weight_counted', 10, 3)->default(0);
            $table->unsignedInteger('huid_expected')->default(0);      // how many are to receive a HUID
            $table->date('expected_return')->nullable();
            $table->enum('status', ['dispatched', 'partially_returned', 'returned', 'closed'])->default('dispatched')->index();
            $table->string('photo_path')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('done_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->string('close_note', 255)->nullable();
            $table->timestamps();
        });

        // Already-tagged stock pieces sent in a batch (each also has its own hallmark_out movement).
        Schema::create('hallmark_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hallmark_batch_id')->constrained('hallmark_batches')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('weight_out', 10, 3);
            $table->boolean('returned')->default(false);
            $table->unique(['hallmark_batch_id', 'item_id']);
        });

        Schema::create('hallmark_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hallmark_batch_id')->constrained('hallmark_batches')->cascadeOnDelete();
            $table->unsignedInteger('pieces')->default(0);
            $table->unsignedInteger('with_huid')->default(0);
            $table->unsignedInteger('without_huid')->default(0);
            $table->decimal('weight_received', 10, 3)->default(0);
            $table->decimal('weight_loss', 10, 3)->default(0);
            $table->string('tagged_by', 100);
            $table->string('photo_path')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('done_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('source_hallmark_batch_id')->nullable()->after('source_karigar_batch_id')->constrained('hallmark_batches')->nullOnDelete();
        });

        // Pieces a karigar sent on for hallmarking untagged, waiting to be put in a batch.
        Schema::table('karigar_receipts', function (Blueprint $table) {
            $table->foreignId('hallmark_batch_id')->nullable()->after('disposition')->constrained('hallmark_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('karigar_receipts', fn (Blueprint $t) => $t->dropConstrainedForeignId('hallmark_batch_id'));
        Schema::table('items', fn (Blueprint $t) => $t->dropConstrainedForeignId('source_hallmark_batch_id'));
        Schema::dropIfExists('hallmark_receipts');
        Schema::dropIfExists('hallmark_batch_items');
        Schema::dropIfExists('hallmark_batches');
    }
};
