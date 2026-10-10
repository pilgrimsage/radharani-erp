<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Referral replaces Loyalty (8 Oct change list, section 15). Codes are opt-in: no customer has
// one by default. The owner sets the points rules; points are recorded in their own ledger.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('referral_opted_at')->nullable()->after('referral_code');
        });

        // Codes issued to everyone by default are withdrawn; people who want one opt in again.
        DB::table('customers')->update(['referral_code' => null]);

        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('points_per_gram', 8, 2)->default(0);       // per gram of metal the referred customer buys
            $table->unsignedInteger('first_sale_bonus')->default(0);    // flat points on the referred customer's first sale
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        DB::table('referral_settings')->insert(['points_per_gram' => 0, 'first_sale_bonus' => 0, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('referral_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');          // the referrer who earns them
            $table->integer('points');
            $table->foreignId('sale_id')->nullable()->constrained('sales');       // the referred sale they are for
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_points');
        Schema::dropIfExists('referral_settings');
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('referral_opted_at'));
    }
};
