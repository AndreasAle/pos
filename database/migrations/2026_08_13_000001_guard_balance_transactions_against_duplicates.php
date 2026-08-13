<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Last line of defence against a double credit/debit.
 *
 * BalanceService already checks for an existing row before applying a movement,
 * but a retried Midtrans webhook arriving concurrently could slip between the
 * check and the insert. This unique index makes that physically impossible:
 * one source can produce at most one credit and one debit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_transactions', function (Blueprint $table) {
            $table->unique(
                ['sourceable_type', 'sourceable_id', 'type'],
                'balance_tx_source_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('balance_transactions', function (Blueprint $table) {
            $table->dropUnique('balance_tx_source_type_unique');
        });
    }
};
