<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// New message types for the copy-and-send queue (8 Oct change list, 17.1).
return new class extends Migration
{
    private const OLD = ['sale_confirmation', 'order_ready', 'loyalty_award', 'installment_reminder', 'exchange_valuation_ready', 'other'];

    private const NEW = ['scheme_welcome', 'scheme_default', 'scheme_completed', 'order_accepted', 'order_from_karigar', 'order_to_hallmarking'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // other drivers store the enum as plain text
        }
        $all = "'" . implode("','", array_merge(self::OLD, self::NEW)) . "'";
        DB::statement("ALTER TABLE pending_notifications MODIFY type ENUM({$all}) NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::table('pending_notifications')->whereIn('type', self::NEW)->update(['type' => 'other']);
        $old = "'" . implode("','", self::OLD) . "'";
        DB::statement("ALTER TABLE pending_notifications MODIFY type ENUM({$old}) NOT NULL DEFAULT 'other'");
    }
};
