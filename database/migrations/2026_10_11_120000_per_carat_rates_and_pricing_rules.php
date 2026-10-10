<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Rates per metal and carat, and one set of pricing rules (8 Oct change list, 11.1 and 11.2).
return new class extends Migration
{
    private const CARATS = [
        'gold' => ['24K' => 24, '22K' => 22, '18K' => 18, '14K' => 14],
        'silver' => ['99.9' => 99.9, '92.5' => 92.5],
        'platinum' => ['950' => 950, '900' => 900],
        'titanium' => ['Grade 5' => 1, 'Grade 2' => 1],
    ];

    public function up(): void
    {
        Schema::table('rate_logs', function (Blueprint $table) {
            $table->string('purity', 10)->nullable()->after('metal');
            $table->index(['metal', 'purity', 'created_at']);
        });

        // Carry today's single rate per metal across to every carat, scaled by fineness, so nothing
        // goes to zero on day one. The owner then sets the real per-carat rates.
        $anchor = ['gold' => '22K', 'silver' => '92.5', 'platinum' => '950', 'titanium' => 'Grade 5'];
        foreach (self::CARATS as $metal => $carats) {
            $legacy = DB::table('rate_logs')->where('metal', $metal)->whereNull('purity')->orderByDesc('created_at')->orderByDesc('id')->first();
            if (! $legacy || (float) $legacy->rate <= 0) {
                continue;
            }
            $base = $carats[$anchor[$metal]];
            foreach ($carats as $carat => $fineness) {
                DB::table('rate_logs')->insert([
                    'metal' => $metal, 'purity' => $carat, 'rate' => round((float) $legacy->rate * $fineness / $base, 2),
                    'source' => 'manual', 'updated_by' => $legacy->updated_by, 'created_at' => now(),
                ]);
            }
        }

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['making', 'additional', 'discount', 'hallmark']);
            // Most specific wins: product, then category, then price range, then metal, then everything.
            $table->enum('scope', ['product', 'category', 'price_range', 'metal', 'all']);
            $table->foreignId('item_id')->nullable()->constrained('items')->cascadeOnDelete();
            $table->string('category', 50)->nullable();
            $table->enum('metal', ['gold', 'silver', 'titanium', 'platinum'])->nullable();
            $table->string('purity', 10)->nullable();                  // carat; null = any
            $table->decimal('min_value', 12, 2)->nullable();           // metal-value band
            $table->decimal('max_value', 12, 2)->nullable();
            $table->string('name', 60)->nullable();                    // additional charges: what it is
            $table->enum('calc', ['percentage', 'per_gram', 'per_piece']);
            $table->decimal('value', 12, 2);
            $table->boolean('active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['kind', 'active']);
        });

        // The hallmarking charge every piece carries unless a more specific rule says otherwise.
        DB::table('pricing_rules')->insert([
            'kind' => 'hallmark', 'scope' => 'all', 'calc' => 'per_piece', 'value' => 45, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
        DB::table('rate_logs')->whereNotNull('purity')->delete();
        Schema::table('rate_logs', function (Blueprint $table) {
            $table->dropIndex(['metal', 'purity', 'created_at']);
            $table->dropColumn('purity');
        });
    }
};
