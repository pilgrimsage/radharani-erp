<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Monthly scheme (8 Oct change list, section 16): a scheme has a length, so months paid, months
// pending and the completion date can be shown; an existing member joins with what they have
// already paid; there is no completion bonus; a matured scheme ends in one of three outcomes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installment_schemes', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_months')->default(12)->after('monthly_amount');
            $table->decimal('opening_pending_amount', 12, 2)->nullable()->after('months_paid');
            $table->enum('maturity_outcome', ['order', 'sale', 'reserve'])->nullable()->after('status');
            $table->unsignedBigInteger('outcome_ref')->nullable()->after('maturity_outcome');
            $table->timestamp('outcome_at')->nullable()->after('outcome_ref');
        });
    }

    public function down(): void
    {
        Schema::table('installment_schemes', fn (Blueprint $t) => $t->dropColumn(['total_months', 'opening_pending_amount', 'maturity_outcome', 'outcome_ref', 'outcome_at']));
    }
};
