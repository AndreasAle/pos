<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add balance columns to businesses
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('settings');
            $table->decimal('total_earned', 15, 2)->default(0)->after('balance');
            $table->decimal('total_withdrawn', 15, 2)->default(0)->after('total_earned');
        });

        // Balance transaction log (credit/debit history)
        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference')->nullable();
            $table->string('description');
            $table->string('source')->default('qris'); // qris, withdrawal, manual, refund
            $table->nullableMorphs('sourceable'); // polymorphic: order, withdrawal_request
            $table->timestamps();
        });

        // Withdrawal requests from merchants
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('bank_name');
            $table->string('account_number', 50);
            $table->string('account_name');
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('balance_transactions');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['balance', 'total_earned', 'total_withdrawn']);
        });
    }
};
