<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Beli X gratis Y" promotions, optionally limited to one category — the
 * classic "buy 1 get 1 on all drinks".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('percent','nominal','buy_get') NOT NULL DEFAULT 'percent'");

        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedSmallInteger('buy_qty')->default(1)->after('value');
            $table->unsignedSmallInteger('get_qty')->default(1)->after('buy_qty');
            $table->foreignId('product_category_id')->nullable()->after('get_qty')
                ->constrained('product_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_category_id');
            $table->dropColumn(['buy_qty', 'get_qty']);
        });

        DB::table('promotions')->where('type', 'buy_get')->delete();
        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('percent','nominal') NOT NULL DEFAULT 'percent'");
    }
};
