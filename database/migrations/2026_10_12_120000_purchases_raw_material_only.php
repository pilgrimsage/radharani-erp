<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Purchases are raw material only (8 Oct change list, section 13): a bill reference and notes,
// no vendor, no money and no payment status. They feed the raw-metal balance. An order can
// have a purchase linked to it. Existing columns stay so old rows remain readable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable()->change();
            $table->decimal('total_amount', 12, 2)->nullable()->default(null)->change();
            $table->text('notes')->nullable()->after('invoice_number');
            $table->foreignId('order_id')->nullable()->after('notes')->constrained('orders')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchases MODIFY payment_status ENUM('paid','partial','pending') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('notes');
        });
    }
};
