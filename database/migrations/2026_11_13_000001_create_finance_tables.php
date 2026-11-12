<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive finance tables: operating expenses (chart of expense accounts), suppliers,
 * purchases with payments (accounts payable) and manual receivables. Nothing existing
 * is altered. Money is decimal(15,2); rows are voided, never deleted, so the books keep
 * an audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        $methods = ['cash', 'transfer', 'qris', 'ewallet', 'debit', 'other'];

        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('code', 10);
            $t->string('name');
            $t->enum('group', ['operating', 'other_expense', 'tax'])->default('operating');
            $t->boolean('is_system')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['business_id', 'code']);
        });

        Schema::create('expenses', function (Blueprint $t) use ($methods) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('expense_number', 30);
            $t->date('expense_date');
            $t->decimal('amount', 15, 2);
            $t->string('payee')->nullable();
            $t->enum('payment_method', $methods)->default('cash');
            $t->string('reference_no', 100)->nullable();
            $t->text('notes')->nullable();
            $t->enum('status', ['posted', 'void'])->default('posted');
            $t->timestamp('voided_at')->nullable();
            $t->string('void_reason')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'expense_number']);
            $t->index(['business_id', 'status', 'expense_date']);
            $t->index(['business_id', 'outlet_id', 'expense_date']);
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('contact_person')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('tax_id', 40)->nullable();
            $t->unsignedSmallInteger('payment_terms_days')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['business_id', 'is_active']);
        });

        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('purchase_number', 30);
            $t->string('supplier_invoice_no', 100)->nullable();
            $t->date('purchase_date');
            $t->date('due_date')->nullable();
            $t->decimal('subtotal', 15, 2)->default(0);
            $t->decimal('discount_amount', 15, 2)->default(0);
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->decimal('shipping_amount', 15, 2)->default(0);
            $t->decimal('total', 15, 2)->default(0);
            $t->decimal('paid_amount', 15, 2)->default(0);
            $t->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $t->enum('status', ['received', 'cancelled'])->default('received');
            $t->boolean('stock_received')->default(false);
            $t->text('notes')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'purchase_number']);
            $t->index(['business_id', 'status', 'payment_status', 'due_date']);
            $t->index(['business_id', 'supplier_id']);
        });

        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description');
            $t->decimal('qty', 15, 3);
            $t->string('unit', 20)->nullable();
            $t->decimal('unit_cost', 15, 2);
            $t->decimal('subtotal', 15, 2);
            $t->timestamps();
            $t->index('purchase_id');
        });

        Schema::create('purchase_payments', function (Blueprint $t) use ($methods) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->date('paid_at');
            $t->decimal('amount', 15, 2);
            $t->enum('payment_method', $methods)->default('cash');
            $t->string('reference_no', 100)->nullable();
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->index(['purchase_id', 'paid_at']);
        });

        Schema::create('receivables', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('receivable_number', 30);
            $t->string('party_name');
            $t->string('description')->nullable();
            $t->date('issue_date');
            $t->date('due_date')->nullable();
            $t->decimal('amount', 15, 2);
            $t->decimal('paid_amount', 15, 2)->default(0);
            $t->enum('status', ['open', 'void'])->default('open');
            $t->timestamp('voided_at')->nullable();
            $t->string('void_reason')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'receivable_number']);
            $t->index(['business_id', 'status', 'due_date']);
        });

        Schema::create('receivable_payments', function (Blueprint $t) use ($methods) {
            $t->id();
            $t->foreignId('receivable_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->date('paid_at');
            $t->decimal('amount', 15, 2);
            $t->enum('payment_method', $methods)->default('cash');
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->index(['receivable_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        foreach (['receivable_payments', 'receivables', 'purchase_payments', 'purchase_items', 'purchases', 'suppliers', 'expenses', 'expense_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
