<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Exchange and refinery (8 Oct change list, section 9): deduction presets by metal and carat,
// kept with the daily rates; the exchange knows its metal; the refinery return works its
// figure out the way the exchange does (weight, purity, deduction).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_deduction_presets', function (Blueprint $table) {
            $table->id();
            $table->enum('metal', ['gold', 'silver', 'titanium', 'platinum']);
            $table->string('purity', 10);                 // carat
            $table->decimal('percent', 5, 2)->default(2); // deducted from the net weight
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['metal', 'purity']);
        });

        $now = now();
        foreach (['gold' => ['24K', '22K', '18K', '14K'], 'silver' => ['99.9', '92.5'], 'platinum' => ['950', '900'], 'titanium' => ['Grade 5', 'Grade 2']] as $metal => $carats) {
            foreach ($carats as $carat) {
                DB::table('exchange_deduction_presets')->insert(['metal' => $metal, 'purity' => $carat, 'percent' => 2.00, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        Schema::table('exchange_transactions', function (Blueprint $table) {
            $table->enum('metal', ['gold', 'silver', 'titanium', 'platinum'])->default('gold')->after('customer_id');
        });

        Schema::table('refinery_batches', function (Blueprint $table) {
            $table->enum('metal', ['gold', 'silver', 'titanium', 'platinum'])->default('gold')->after('id');
            $table->decimal('deduction_percent', 5, 2)->nullable()->after('refined_purity');
            $table->decimal('result_weight', 10, 3)->nullable()->after('deduction_percent');
        });
    }

    public function down(): void
    {
        Schema::table('refinery_batches', fn (Blueprint $t) => $t->dropColumn(['metal', 'deduction_percent', 'result_weight']));
        Schema::table('exchange_transactions', fn (Blueprint $t) => $t->dropColumn('metal'));
        Schema::dropIfExists('exchange_deduction_presets');
    }
};
