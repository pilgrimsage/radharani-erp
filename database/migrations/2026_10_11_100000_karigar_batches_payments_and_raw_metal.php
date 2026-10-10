<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Karigar rebuild (8 Oct change list, section 6). A "batch" is one issue to a karigar, identified
// by its date and time. It carries an advance (cash, metal or both) and a piece count; pieces
// come back in any number of receipts until the owner or manager closes the rest.
// karigar_raw_batches stays as the batch table so items.source_karigar_batch_id keeps working.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karigar_raw_batches', function (Blueprint $table) {
            $table->string('description', 150)->nullable()->after('purpose_label');
            $table->json('categories')->nullable()->after('description');
            $table->unsignedInteger('pieces_expected')->default(0)->after('categories');
            $table->decimal('advance_cash', 12, 2)->default(0)->after('weight_out');
            $table->decimal('advance_metal_weight', 10, 3)->default(0)->after('advance_cash');
            $table->string('advance_metal_purity', 10)->nullable()->after('advance_metal_weight');
            $table->unsignedBigInteger('order_id')->nullable()->after('advance_metal_purity')->index();
            $table->foreignId('closed_by')->nullable()->after('returned_by')->constrained('users');
            $table->timestamp('closed_at')->nullable()->after('closed_by');
            $table->string('close_note', 255)->nullable()->after('closed_at');
        });

        // weight_out was the raw metal handed over; an advance of metal now says that, and the
        // batch weight is the estimated weight of the finished pieces.
        Schema::table('karigar_raw_batches', function (Blueprint $table) {
            $table->decimal('weight_out', 10, 3)->default(0)->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE karigar_raw_batches MODIFY status ENUM('dispatched','partially_returned','returned','closed') NOT NULL DEFAULT 'dispatched'");
        }

        Schema::create('karigar_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('karigar_raw_batches')->cascadeOnDelete();
            $table->unsignedInteger('pieces')->default(0);
            $table->decimal('weight_received', 10, 3)->default(0);
            $table->decimal('weight_loss', 10, 3)->default(0);
            // stock: pieces become items waiting in Pending Review. hallmark: they go on to hallmarking untagged.
            $table->enum('disposition', ['stock', 'hallmark'])->default('stock');
            $table->string('photo_path')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('done_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('karigar_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('batch_id')->nullable()->constrained('karigar_raw_batches')->nullOnDelete();
            $table->enum('kind', ['cash', 'metal']);
            $table->decimal('amount', 12, 2)->nullable();          // cash
            $table->enum('metal', ['gold', 'silver', 'titanium', 'platinum'])->nullable();
            $table->string('purity', 10)->nullable();               // carat, for metal
            $table->decimal('weight', 10, 3)->nullable();           // metal
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->index(['vendor_id', 'created_at']);
        });

        // Raw metal balance by metal and carat (6.5): purchases add, issues and payments to karigars deduct.
        Schema::create('raw_metal_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('metal', ['gold', 'silver', 'titanium', 'platinum']);
            $table->string('purity', 10);
            $table->decimal('weight', 10, 3);                       // + in, - out
            $table->string('source_type', 30);                      // purchase | karigar_advance | karigar_payment | adjustment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->index(['metal', 'purity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_metal_entries');
        Schema::dropIfExists('karigar_payments');
        Schema::dropIfExists('karigar_receipts');
        Schema::table('karigar_raw_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['description', 'categories', 'pieces_expected', 'advance_cash', 'advance_metal_weight', 'advance_metal_purity', 'order_id', 'closed_at', 'close_note']);
        });
    }
};
