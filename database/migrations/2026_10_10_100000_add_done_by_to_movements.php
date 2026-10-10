<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Done by" (8 Oct change list, 1.5): the employee who physically did the movement when
// that isn't the logged-in user (a phone on charge, a colleague at the vault). Additional to
// user_id, which always stays the logged-in actor.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->foreignId('done_by_employee_id')->nullable()->after('user_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('done_by_employee_id');
        });
    }
};
