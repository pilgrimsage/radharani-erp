<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sales rebuild (8 Oct change list, section 12). A bill can be paid in parts across modes and
// cleared later; each part is its own insert-only row and the balance is worked out, never
// stored. GST is gone; the adjustment is a flat amount or a percentage and needs no approval.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales');
            $table->enum('mode', ['cash', 'upi', 'card', 'bank']);
            $table->decimal('amount', 12, 2);
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sale_id', 'created_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->enum('adjustment_type', ['flat', 'percent'])->nullable()->after('discount');
            $table->decimal('adjustment_value', 12, 2)->nullable()->after('adjustment_type');
            $table->foreignId('referral_customer_id')->nullable()->after('customer_id')->constrained('customers')->nullOnDelete();
            $table->string('order_override_note', 255)->nullable()->after('accountant_note');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_customer_id');
            $table->dropColumn(['adjustment_type', 'adjustment_value', 'order_override_note']);
        });
        Schema::dropIfExists('sale_payments');
    }
};
