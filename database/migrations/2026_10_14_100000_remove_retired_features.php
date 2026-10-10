<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// The features the client dropped (8 Oct change list, section 18) are removed for good: the
// accounting module, Loyalty, invoices and GST, vendors and suppliers, ready-made product
// purchases, and the old per-item pricing presets (replaced by pricing_rules).
// down() rebuilds the empty structures only; the data itself is not recoverable from here.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['transactions', 'accounts', 'loyalty_transactions', 'loyalty_settings', 'invoice_counters', 'gst_rates', 'discount_rules', 'making_charge_presets', 'additional_charge_presets'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'loyalty_points')) {
                $table->dropColumn('loyalty_points');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['cgst', 'sgst', 'igst', 'payment_modes']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropColumn(['total_amount', 'gst', 'payment_status']);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('rate');
        });

        // Suppliers are gone; karigars and hallmarking centres stay as named parties for their ledgers.
        DB::table('vendors')->where('type', 'supplier')->delete();
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE vendors MODIFY type ENUM('karigar','hallmark_center') NOT NULL");
        }
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('balance');
        });

        Permission::whereIn('name', ['ledger.view', 'ledger.manage', 'discount.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('vendors', fn (Blueprint $t) => $t->decimal('balance', 12, 2)->default(0));
        Schema::table('purchase_items', fn (Blueprint $t) => $t->decimal('rate', 12, 2)->nullable());
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('gst', 12, 2)->nullable();
            $table->string('payment_status', 10)->nullable();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->json('payment_modes')->nullable();
        });
        Schema::table('customers', fn (Blueprint $t) => $t->integer('loyalty_points')->default(0));
    }
};
