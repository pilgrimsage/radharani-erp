<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Two-level category tree (8 Oct change list, 5.1): Metal, then Subcategory. The same
// subcategory name can exist under different metals as separate rows. items.category
// stays as the subcategory's name so existing screens, pricing and the website keep working.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->enum('metal', ['gold', 'silver', 'titanium', 'platinum']);
            $table->string('name', 50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['metal', 'name']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('item_categories')->nullOnDelete();
        });

        // Seed the tree from what is already typed on items (all current data is test data).
        $now = now();
        $pairs = DB::table('items')->selectRaw("COALESCE(metal, 'gold') as m, category")->whereNotNull('category')
            ->where('category', '!=', '')->distinct()->get();
        foreach ($pairs as $i => $p) {
            DB::table('item_categories')->insertOrIgnore([
                'metal' => $p->m, 'name' => $p->category, 'sort_order' => $i, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::statement("UPDATE items SET category_id = (SELECT c.id FROM item_categories c WHERE c.metal = COALESCE(items.metal, 'gold') AND c.name = items.category LIMIT 1)");
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $t) => $t->dropConstrainedForeignId('category_id'));
        Schema::dropIfExists('item_categories');
    }
};
