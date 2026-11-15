<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 40);
            $table->string('public_token', 64)->unique();
            $table->string('status', 24)->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
            $table->index(['business_id', 'outlet_id', 'status']);
        });

        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->constrained()->cascadeOnDelete();
            $table->string('guest_token', 64)->unique();
            $table->string('status', 24)->default('open');
            $table->string('guest_name', 80)->nullable();
            $table->string('guest_phone', 24)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['dining_table_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('dining_table_id')->nullable()->after('table_number')->constrained()->nullOnDelete();
            $table->foreignId('table_session_id')->nullable()->after('dining_table_id')->constrained()->nullOnDelete();
            $table->string('order_source', 24)->default('pos')->after('order_type');
            $table->string('acceptance_status', 24)->default('accepted')->after('status');
            $table->foreignId('accepted_by')->nullable()->after('acceptance_status')->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            $table->timestamp('expires_at')->nullable()->after('accepted_at');
            $table->string('payment_gateway', 32)->nullable()->after('payment_token');
            $table->string('gateway_reference', 120)->nullable()->after('payment_gateway');
            $table->timestamp('payment_expires_at')->nullable()->after('gateway_reference');
            $table->index(['business_id', 'order_source', 'acceptance_status'], 'orders_source_acceptance_idx');
            $table->index(['table_session_id', 'created_at']);
        });

        // Public self-orders have no authenticated cashier. Attribution is kept
        // in order_source and accepted_by; user_id is filled only after a member
        // of staff handles the order.
        DB::statement('ALTER TABLE orders MODIFY user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE orders SET user_id = accepted_by WHERE user_id IS NULL AND accepted_by IS NOT NULL');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_source_acceptance_idx');
            $table->dropIndex(['table_session_id', 'created_at']);
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropConstrainedForeignId('table_session_id');
            $table->dropConstrainedForeignId('dining_table_id');
            $table->dropColumn(['order_source', 'acceptance_status', 'accepted_at', 'expires_at', 'payment_gateway', 'gateway_reference', 'payment_expires_at']);
        });
        DB::statement('ALTER TABLE orders MODIFY user_id BIGINT UNSIGNED NOT NULL');
        Schema::dropIfExists('table_sessions');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('dining_areas');
    }
};
