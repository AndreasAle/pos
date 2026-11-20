<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buku besar KEUANGAN YOKA SENDIRI (platform), terpisah total dari transaksi toko klien (orders, expenses, dll.).
 * Double-entry: setiap jurnal minimal dua baris dan total debit = total kredit. Jurnal yang sudah
 * diposting tidak diubah atau dihapus; koreksi lewat jurnal pembalik (lihat App\Services\PlatformFinance\Ledger).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fin_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name', 100);
            $t->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            // cash, bank, clearing, receivable, prepaid, payable, owner_loan, reimbursement, tax, deferred,
            // capital, drawing, retained, contra_revenue, ...
            $t->string('subtype', 30)->nullable();
            $t->boolean('is_cash')->default(false);
            // Perilaku biaya (Managerial Accounting ch.2) dan kelompok laporan.
            $t->enum('behavior', ['fixed', 'variable', 'mixed'])->nullable();
            $t->enum('report_group', ['cost_of_revenue', 'marketing', 'operating', 'other'])->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('platform_fin_entries', function (Blueprint $t) {
            $t->id();
            $t->date('entry_date')->index();
            $t->string('description', 255);
            $t->string('source', 20)->default('manual'); // manual, midtrans, reversal, opening
            $t->string('idempotency_key', 120)->nullable()->unique();
            $t->string('reference', 120)->nullable();
            $t->string('evidence', 255)->nullable();
            $t->foreignId('reversal_of_id')->nullable()->constrained('platform_fin_entries');
            $t->foreignId('reversed_by_id')->nullable()->constrained('platform_fin_entries');
            $t->foreignId('created_by')->nullable()->constrained('platform_admins');
            $t->timestamps();
        });

        Schema::create('platform_fin_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('platform_fin_entry_id')->constrained('platform_fin_entries');
            $t->foreignId('platform_fin_account_id')->constrained('platform_fin_accounts');
            $t->decimal('debit', 16, 2)->default(0);
            $t->decimal('credit', 16, 2)->default(0);
            $t->string('memo', 255)->nullable();
            $t->string('cost_center', 30)->nullable()->index(); // platform, ai, cs, marketing, umum
            $t->index(['platform_fin_account_id', 'platform_fin_entry_id'], 'pfl_account_entry_idx');
        });

        Schema::create('platform_fin_periods', function (Blueprint $t) {
            $t->id();
            $t->char('period', 7)->unique(); // YYYY-MM
            $t->timestamp('closed_at');
            $t->foreignId('closed_by')->nullable()->constrained('platform_admins');
            $t->json('checklist')->nullable(); // snapshot checklist saat ditutup
            $t->boolean('warnings_acknowledged')->default(false);
            $t->timestamp('reopened_at')->nullable(); // terisi = periode sedang dibuka ulang
            $t->foreignId('reopened_by')->nullable()->constrained('platform_admins');
            $t->string('reopen_reason', 255)->nullable();
            $t->unsignedSmallInteger('reopen_count')->default(0);
            $t->timestamps();
        });

        Schema::create('platform_fin_budgets', function (Blueprint $t) {
            $t->id();
            $t->char('period', 7);
            $t->foreignId('platform_fin_account_id')->constrained('platform_fin_accounts');
            $t->decimal('amount', 16, 2);
            $t->timestamps();
            $t->unique(['period', 'platform_fin_account_id']);
        });

        $now = now();
        DB::table('platform_fin_accounts')->insert(array_map(fn ($a) => [
            'code' => $a[0], 'name' => $a[1], 'type' => $a[2], 'subtype' => $a[3], 'is_cash' => $a[4],
            'behavior' => $a[5] ?? null, 'report_group' => $a[6] ?? null, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ], self::CHART));
    }

    /** Bagan akun awal. Bisa ditambah dari halaman Keuangan Yoka. */
    private const CHART = [
        ['1000', 'Kas Tunai', 'asset', 'cash', true],
        ['1100', 'Bank Yoka', 'asset', 'bank', true],
        ['1200', 'Saldo Midtrans (dana tertahan)', 'asset', 'clearing', false],
        ['1300', 'Piutang Usaha', 'asset', 'receivable', false],
        ['1400', 'Biaya Dibayar di Muka', 'asset', 'prepaid', false],
        ['1500', 'Aset Tetap (laptop, perangkat)', 'asset', 'fixed', false],
        ['1510', 'Akumulasi Penyusutan', 'asset', 'contra_asset', false],
        ['2000', 'Hutang Usaha', 'liability', 'payable', false],
        ['2100', 'Pinjaman dari Pemilik', 'liability', 'owner_loan', false],
        ['2200', 'Talangan Pemilik (harus diganti)', 'liability', 'reimbursement', false],
        ['2300', 'Hutang Pajak', 'liability', 'tax', false],
        ['2400', 'Pendapatan Diterima di Muka', 'liability', 'deferred', false],
        ['3000', 'Modal Pemilik', 'equity', 'capital', false],
        ['3100', 'Prive (Penarikan Pemilik)', 'equity', 'drawing', false],
        ['3200', 'Laba Ditahan', 'equity', 'retained', false],
        ['4000', 'Pendapatan Langganan', 'revenue', 'subscription', false],
        ['4100', 'Pendapatan Jasa Setup', 'revenue', 'service', false],
        ['4800', 'Refund Pendapatan', 'revenue', 'contra_revenue', false],
        ['4900', 'Pendapatan Lain', 'revenue', 'other', false],
        ['5000', 'Server & Hosting', 'expense', null, false, 'fixed', 'cost_of_revenue'],
        ['5100', 'Biaya AI (OpenAI dll.)', 'expense', null, false, 'variable', 'cost_of_revenue'],
        ['5200', 'Fee Payment Gateway', 'expense', null, false, 'variable', 'cost_of_revenue'],
        ['5300', 'Domain & SSL', 'expense', null, false, 'fixed', 'operating'],
        ['5400', 'WhatsApp Gateway & Notifikasi', 'expense', null, false, 'variable', 'cost_of_revenue'],
        ['6000', 'Iklan Meta', 'expense', null, false, 'variable', 'marketing'],
        ['6100', 'Iklan TikTok', 'expense', null, false, 'variable', 'marketing'],
        ['6200', 'Marketing Lain', 'expense', null, false, 'variable', 'marketing'],
        ['7000', 'Gaji & Freelance', 'expense', null, false, 'fixed', 'operating'],
        ['7300', 'Beban Penyusutan', 'expense', null, false, 'fixed', 'operating'],
        ['7100', 'Software & Tools', 'expense', null, false, 'fixed', 'operating'],
        ['7200', 'Pajak & Retribusi', 'expense', null, false, 'mixed', 'operating'],
        ['7900', 'Operasional Lain', 'expense', null, false, 'mixed', 'operating'],
    ];

    public function down(): void
    {
        Schema::dropIfExists('platform_fin_budgets');
        Schema::dropIfExists('platform_fin_periods');
        Schema::dropIfExists('platform_fin_lines');
        Schema::dropIfExists('platform_fin_entries');
        Schema::dropIfExists('platform_fin_accounts');
    }
};
