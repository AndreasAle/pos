<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance V2 tahap 1: vendor, dokumen biaya dengan alur persetujuan, dan jejak audit keuangan.
 * Dokumen biaya BELUM mempengaruhi buku besar sampai disetujui lalu diposting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fin_vendors', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120)->unique();
            $t->string('default_account', 10)->nullable();
            $t->string('notes', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('platform_fin_expenses', function (Blueprint $t) {
            $t->id();
            // draft, submitted, approved, posted, rejected, cancelled
            $t->string('status', 12)->default('draft')->index();
            $t->date('expense_date');
            $t->foreignId('vendor_id')->nullable()->constrained('platform_fin_vendors');
            $t->string('account_code', 10);
            $t->string('paid_from', 20); // kode akun kas/bank/midtrans, atau capital|loan|reimbursement
            $t->decimal('amount', 16, 2);
            $t->string('description', 200);
            $t->string('invoice_no', 80)->nullable();
            $t->string('evidence_path', 255)->nullable();
            $t->string('evidence_name', 150)->nullable();
            $t->string('created_by_type', 10); // admin | agent
            $t->unsignedBigInteger('created_by_id');
            $t->foreignId('approved_by')->nullable()->constrained('platform_admins');
            $t->timestamp('approved_at')->nullable();
            $t->boolean('self_approved')->default(false);
            $t->string('rejection_reason', 200)->nullable();
            $t->foreignId('platform_fin_entry_id')->nullable()->constrained('platform_fin_entries');
            // Hutang usaha: tagihan diakui saat disetujui (Dr beban / Cr 2000), dibayar terpisah (Dr 2000 / Cr kas).
            $t->date('due_date')->nullable();
            $t->string('cost_center', 30)->nullable();
            $t->string('payment_status', 8)->nullable()->index(); // null = langsung dibayar, unpaid, paid
            $t->date('paid_at')->nullable();
            $t->string('paid_with', 20)->nullable();
            $t->foreignId('payment_entry_id')->nullable()->constrained('platform_fin_entries');
            $t->timestamps();
            // Invoice yang sama dari vendor yang sama tidak boleh dicatat dua kali.
            $t->index(['vendor_id', 'invoice_no'], 'pfe_vendor_invoice_idx'); // dobel dicek di ExpenseWorkflow (invoice yang ditolak boleh diajukan ulang)
        });

        Schema::create('platform_fin_audit_events', function (Blueprint $t) {
            $t->id();
            $t->string('actor_type', 10); // admin | agent | system
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('action', 40);
            $t->string('subject_type', 40);
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('reason', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fin_audit_events');
        Schema::dropIfExists('platform_fin_expenses');
        Schema::dropIfExists('platform_fin_vendors');
    }
};
