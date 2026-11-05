<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which categories the kitchen cooks. Items in these categories go on the
 * kitchen ticket; drinks and anything made at the counter stay off it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('send_to_kitchen')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('send_to_kitchen');
        });
    }
};
