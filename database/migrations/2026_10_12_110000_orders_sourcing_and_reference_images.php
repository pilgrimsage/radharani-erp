<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Custom orders (8 Oct change list, section 10): how an order is sourced decides its path
// (karigar, hallmarking, sales), and the customer can leave reference images.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('sourcing', ['stock', 'karigar', 'bought_finished', 'bought_unhallmarked', 'bought_unfinished'])->default('karigar')->after('out_of_stock');
            $table->decimal('estimated_value', 10, 2)->default(0)->change();
        });

        Schema::create('order_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_images');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('sourcing'));
    }
};
