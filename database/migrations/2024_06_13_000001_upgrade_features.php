<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Orders: refund + delivery + midtrans + split flag ─────────────────
        Schema::table('orders', function (Blueprint $table) {
            $table->text('refund_reason')->nullable()->after('cancel_reason');
            $table->timestamp('refunded_at')->nullable()->after('refund_reason');

            $table->enum('order_type', ['dine_in', 'takeaway', 'delivery'])->default('dine_in')->after('order_number');
            $table->string('delivery_platform', 50)->nullable()->after('order_type'); // gofood,grabfood,shopeefood,manual
            $table->decimal('delivery_fee', 15, 2)->default(0)->after('delivery_platform');
            $table->text('customer_address')->nullable()->after('delivery_fee');
            $table->string('delivery_notes')->nullable()->after('customer_address');
            $table->string('platform_order_number', 100)->nullable()->after('delivery_notes');

            $table->string('payment_token', 255)->nullable()->after('payment_status'); // Midtrans token
            $table->string('qris_url', 500)->nullable()->after('payment_token');       // Midtrans QRIS URL
            $table->boolean('is_split_payment')->default(false)->after('qris_url');
        });

        // ── Order Payments: split billing ────────────────────────────────────
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'qris', 'transfer', 'ewallet', 'debit', 'other'])->default('cash');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index('order_id');
        });

        // ── Business Subscriptions: midtrans payment ─────────────────────────
        Schema::table('business_subscriptions', function (Blueprint $table) {
            $table->string('payment_token', 255)->nullable()->after('notes');
            $table->string('payment_url', 500)->nullable()->after('payment_token');
            $table->timestamp('paid_at')->nullable()->after('payment_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'refund_reason', 'refunded_at',
                'order_type', 'delivery_platform', 'delivery_fee',
                'customer_address', 'delivery_notes', 'platform_order_number',
                'payment_token', 'qris_url', 'is_split_payment',
            ]);
        });

        Schema::dropIfExists('order_payments');

        Schema::table('business_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_token', 'payment_url', 'paid_at']);
        });
    }
};
