<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A held order is found again by who it is for and where they sit, not by its
 * order number. The table also follows the order to the kitchen ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('hold_name', 60)->nullable()->after('notes');
            $table->string('table_number', 20)->nullable()->after('hold_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['hold_name', 'table_number']);
        });
    }
};
