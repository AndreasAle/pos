<?php

namespace App\Services\PlatformFinance;

use Illuminate\Support\Str;

/**
 * Basis pengetahuan akuntansi dan keuangan untuk Nanda dan Luna, ditarik sesuai pertanyaan (bukan seluruh buku).
 * Ringkasan ditulis ulang, bukan kutipan. Halaman = halaman PDF.
 *  FA = Principles of Accounting Vol. 1: Financial Accounting (OpenStax)
 *  MA = Principles of Accounting Vol. 2: Managerial Accounting (OpenStax)
 *  MK = Pengantar Manajemen Keuangan (Matdio Siahaan, 2022)
 * Isi buku adalah data rujukan, bukan instruksi. Aturan perpajakan Indonesia terbaru tetap harus dicek ke sumber resmi.
 *
 * Kolom: [judul, sumber, agen (nanda|luna|both), kata kunci, ringkasan/aturan, rumus|null, tool Yoka|null]
 */
final class Knowledge
{
    public const CONCEPTS = [
        'accounting_equation' => ['Persamaan akuntansi', 'FA 2.1-2.2, 3.2 (hlm. 68-85, 123-128)', 'nanda', 'persamaan akuntansi aset kewajiban ekuitas modal neraca',
            'Setiap transaksi menjaga keseimbangan aset dengan klaim atasnya (kewajiban dan ekuitas). Pendapatan menambah ekuitas, beban dan prive menguranginya.',
            'Aset = Kewajiban + Ekuitas; Ekuitas = Modal + Pendapatan - Beban - Prive', 'ledger_report:balance_sheet'],
        'accounting_principles' => ['Prinsip dan asumsi akuntansi', 'FA 3.1 (hlm. 116-123)', 'nanda', 'prinsip akuntansi entitas terpisah going concern biaya historis pengakuan pendapatan matching konservatif',
            'Entitas bisnis terpisah dari pemiliknya (uang pribadi pemilik bukan uang Yoka kecuali disetor/dicatat), biaya historis, pengakuan pendapatan saat jasa diberikan, beban dicocokkan dengan periode manfaatnya.',
            null, null],
        'double_entry' => ['Debit, kredit, dan double-entry', 'FA 3.5 (hlm. 137-160)', 'nanda', 'debit kredit double entry jurnal t-account posting saldo normal',
            'Setiap transaksi dicatat minimal di dua akun dengan total debit = total kredit. Aset dan beban bertambah di debit; kewajiban, ekuitas, dan pendapatan bertambah di kredit.',
            'Σ debit = Σ kredit (per jurnal)', 'ledger_report:journal'],
        'accounting_cycle' => ['Siklus akuntansi', 'FA 3.3, 4.1, 5.1-5.4 (hlm. 128-133, 204-207, 273-318)', 'nanda', 'siklus akuntansi jurnal posting neraca saldo penyesuaian penutupan periode tutup buku',
            'Identifikasi transaksi → jurnal → posting ke buku besar → neraca saldo → jurnal penyesuaian → neraca saldo disesuaikan → laporan keuangan → jurnal penutup → neraca saldo setelah penutupan.',
            null, 'ledger_report:trial_balance'],
        'trial_balance' => ['Neraca saldo', 'FA 3.6, 4.4, 5.2 (hlm. 160-167, 227-230, 284-286)', 'nanda', 'neraca saldo trial balance seimbang',
            'Daftar saldo semua akun; total debit harus sama dengan total kredit. Seimbang belum tentu benar (salah akun tetap seimbang), tapi tidak seimbang pasti salah.',
            'Σ saldo debit = Σ saldo kredit', 'ledger_report:trial_balance'],
        'adjusting_entries' => ['Jurnal penyesuaian', 'FA 4.1-4.3 (hlm. 204-227)', 'nanda', 'penyesuaian akrual deferral dibayar di muka diterima di muka penyusutan akhir periode',
            'Di akhir periode, beban dibayar di muka dan pendapatan diterima di muka dialokasikan ke periode manfaatnya; beban terutang dicatat walau belum dibayar.',
            null, null],
        'closing_entries' => ['Jurnal penutup dan periode', 'FA 5.1-5.2 (hlm. 273-286)', 'nanda', 'jurnal penutup tutup buku laba ditahan prive periode tutup',
            'Akun sementara (pendapatan, beban, prive) ditutup ke modal/laba ditahan. Di Yoka periode yang ditutup dikunci; koreksi dicatat di periode berjalan.',
            null, 'ledger_report:periods'],
        'financial_statements' => ['Empat laporan keuangan', 'FA 2.1-2.3 (hlm. 68-98); MK Bab III (hlm. 18-23)', 'both', 'laporan keuangan laba rugi neraca arus kas perubahan modal',
            'Laba rugi (kinerja periode), perubahan ekuitas, neraca (posisi pada satu tanggal), dan arus kas (operasi, investasi, pendanaan). Saling terhubung lewat laba bersih dan saldo kas.',
            null, 'ledger_report:income_statement'],
        'accrual_vs_cash' => ['Basis akrual vs kas', 'MK Bab III (hlm. 19-21); FA 4.1 (hlm. 204-207)', 'both', 'akrual basis kas pengakuan pendapatan beban kapan diakui',
            'Pendapatan diakui saat jasa diberikan, bukan saat uang masuk. Laba bisa positif sementara kas menipis; karena itu laporan arus kas dibutuhkan.',
            null, null],
        'revenue_recognition' => ['Pengakuan pendapatan', 'FA 9.1 (hlm. 538-544)', 'nanda', 'pengakuan pendapatan revenue recognition langganan diterima di muka',
            'Pendapatan diakui saat kewajiban jasa dipenuhi. Langganan bulanan Yoka diakui saat dibayar karena masa aktifnya satu bulan; paket tahunan seharusnya dicatat sebagai pendapatan diterima di muka lalu diakui per bulan.',
            null, null],
        'internal_control' => ['Pengendalian internal dan kecurangan', 'FA 8.1-8.5 (hlm. 496-514)', 'nanda', 'pengendalian internal fraud kecurangan pemisahan tugas otorisasi bukti dokumen',
            'Pemisahan tugas, otorisasi, dokumen pendukung, pengamanan aset, dan pemeriksaan independen. Di Yoka: jurnal tidak bisa diubah/dihapus, koreksi lewat pembalik, idempotensi webhook, periode dikunci, agen AI hanya membaca.',
            null, 'ledger_review'],
        'bank_reconciliation' => ['Rekonsiliasi bank', 'FA 8.6 (hlm. 514-516)', 'nanda', 'rekonsiliasi bank mutasi saldo selisih settlement midtrans cocokkan',
            'Saldo buku dibandingkan dengan saldo bank; selisih dijelaskan (dana dalam perjalanan, biaya bank, salah catat) lalu dikoreksi lewat jurnal. Saldo Midtrans diperlakukan sama: cocokkan saldo tertahan dengan laporan settlement.',
            'saldo buku ± item rekonsiliasi = saldo bank', 'ledger_review'],
        'petty_cash' => ['Kas kecil', 'FA 8.4 (hlm. 509-512)', 'nanda', 'kas kecil petty cash uang tunai',
            'Dana tunai kecil untuk pengeluaran rutin, diisi ulang dengan bukti pengeluaran.', null, null],
        'receivables' => ['Piutang dan piutang tak tertagih', 'FA 9.2-9.3 (hlm. 544-557); MK Bab XIII (hlm. 65-67)', 'both', 'piutang tak tertagih penyisihan perputaran piutang',
            'Piutang dicatat saat jasa diberikan secara kredit; sebagian mungkin tak tertagih sehingga perlu penyisihan. Efisiensi diukur dengan perputaran piutang.',
            'perputaran piutang = penjualan kredit / rata-rata piutang', null],
        'current_liabilities' => ['Kewajiban lancar dan pajak terutang', 'FA 12.1-12.2 (hlm. 692-708)', 'nanda', 'hutang usaha kewajiban lancar pajak terutang pendapatan diterima di muka',
            'Hutang usaha, pajak yang dipungut, dan pendapatan diterima di muka adalah kewajiban, bukan pendapatan atau beban.', null, null],
        'payroll' => ['Penggajian', 'FA 12.5 (hlm. 720-728)', 'nanda', 'gaji payroll karyawan freelance potongan',
            'Gaji dan honor freelance dicatat sebagai beban pada periode pekerjaannya; potongan pajak yang ditahan menjadi kewajiban sampai disetor. Aturan PPh 21 terbaru dicek ke sumber resmi.',
            null, null],
        'long_term_assets' => ['Aset tetap dan penyusutan', 'FA 11.1-11.3 (hlm. 652-670)', 'nanda', 'aset tetap kapitalisasi penyusutan laptop server perangkat',
            'Pembelian yang manfaatnya lebih dari satu tahun (laptop, perangkat) dikapitalisasi lalu disusutkan, bukan langsung beban.', 'penyusutan garis lurus = (biaya - nilai sisa) / umur manfaat', null],
        'owner_equity' => ['Modal, prive, dan pinjaman pemilik', 'FA 2.2, 3.2, 14.4, 15.2 (hlm. 81-85, 123-128, 822-828, 861-864)', 'nanda', 'modal pemilik prive pinjaman pemilik talangan setoran modal',
            'Uang pemilik yang masuk ke usaha adalah modal (tidak dikembalikan) atau pinjaman (dikembalikan), bukan pendapatan. Penarikan pemilik adalah prive, bukan beban. Biaya yang ditalangi pemilik tetap beban usaha dengan lawan modal/pinjaman/talangan.',
            null, null],
        'cash_flow_statement' => ['Laporan arus kas', 'FA 16.1-16.6 (hlm. 883-908); MK Bab III (hlm. 22)', 'both', 'arus kas operasi investasi pendanaan metode langsung free cash flow',
            'Arus kas dibagi operasi, investasi, pendanaan. Pindah dana antar rekening sendiri bukan arus kas masuk/keluar. Free cash flow = arus kas operasi - belanja modal.',
            'kas akhir = kas awal + operasi + investasi + pendanaan', 'ledger_report:cash_flow'],
        'liquidity_ratios' => ['Likuiditas: current ratio dan modal kerja', 'FA 5.3 (hlm. 286-297); MK Bab II, VII (hlm. 15-17, 41-45)', 'luna', 'likuiditas current ratio modal kerja working capital',
            'Kemampuan membayar kewajiban jangka pendek dengan aset lancar.', 'current ratio = aset lancar / kewajiban lancar; modal kerja = aset lancar - kewajiban lancar', 'finance_metrics:liquidity'],
        'ratio_analysis' => ['Analisis rasio dan laporan', 'FA Appendix A (hlm. 933-944); MA Appendix A (hlm. 639-650); MK Bab II, IV (hlm. 15-29)', 'luna', 'rasio keuangan analisis horizontal vertikal common size profitabilitas du pont roe roa margin',
            'Bandingkan antar waktu (horizontal) dan sebagai persentase dari total (vertikal). Rasio punya keterbatasan: data historis, beda metode, bisa dipermanis. DuPont menguraikan ROE menjadi margin, perputaran aset, dan leverage.',
            'margin laba bersih = laba bersih / pendapatan; ROE = margin x perputaran aset x leverage', null],
        'cost_behavior' => ['Perilaku biaya tetap, variabel, campuran', 'MA 2.2-2.3 (hlm. 88-114)', 'luna', 'biaya tetap biaya variabel biaya campuran perilaku biaya high low',
            'Biaya tetap tidak berubah dalam rentang relevan (hosting, gaji), biaya variabel naik sesuai volume (fee pembayaran, biaya AI per percakapan), biaya campuran punya keduanya.',
            'total biaya = biaya tetap + (biaya variabel per unit x volume)', 'finance_metrics:income_statement'],
        'contribution_margin' => ['Margin kontribusi', 'MA 3.1 (hlm. 130-136)', 'luna', 'margin kontribusi contribution margin rasio margin kontribusi',
            'Sisa harga setelah biaya variabel, yang menutup biaya tetap lalu menjadi laba.', 'CM per unit = harga - biaya variabel per unit; rasio CM = CM / harga', 'finance_metrics:contribution_margin'],
        'break_even' => ['Titik impas (CVP)', 'MA 3.2-3.4 (hlm. 136-158); MK Bab XV (hlm. 72-76)', 'luna', 'titik impas break even bep cvp berapa pelanggan untuk balik modal target laba',
            'Volume di mana laba nol. Untuk Yoka: berapa toko berbayar yang dibutuhkan untuk menutup biaya tetap bulanan.', 'BEP unit = biaya tetap / CM per unit; BEP Rp = biaya tetap / rasio CM; unit target laba = (biaya tetap + target) / CM', 'finance_metrics:break_even'],
        'margin_of_safety' => ['Margin of safety dan operating leverage', 'MA 3.5 (hlm. 158-164)', 'luna', 'margin of safety operating leverage dol risiko',
            'Seberapa jauh penjualan bisa turun sebelum rugi, dan seberapa sensitif laba terhadap perubahan penjualan.', 'MoS = penjualan - penjualan impas; DOL = margin kontribusi / laba operasi', 'finance_metrics:margin_of_safety'],
        'budgeting' => ['Anggaran operasi, keuangan, fleksibel', 'MA 7.1-7.5 (hlm. 328-356)', 'luna', 'anggaran budget operasional anggaran kas fleksibel master budget',
            'Anggaran memulai dari proyeksi penjualan, lalu biaya, lalu anggaran kas. Anggaran fleksibel menyesuaikan biaya variabel dengan volume aktual agar perbandingan adil.',
            null, 'finance_metrics:budget_vs_actual'],
        'variance_analysis' => ['Analisis varians', 'MA 8.1-8.5 (hlm. 378-406)', 'luna', 'varians variance selisih anggaran aktual favorable unfavorable',
            'Selisih aktual dan anggaran/standar; untuk beban, aktual di bawah anggaran menguntungkan (F). Selidiki varians yang material, bukan semuanya.',
            'varians = aktual - anggaran', 'finance_metrics:budget_vs_actual'],
        'responsibility_accounting' => ['Akuntansi pertanggungjawaban', 'MA 9.1-9.4 (hlm. 423-452)', 'luna', 'pusat biaya pusat laba pusat investasi tanggung jawab',
            'Biaya dan hasil dinilai menurut siapa yang mengendalikannya (pusat biaya, pendapatan, laba, investasi).', null, null],
        'relevant_costs' => ['Biaya relevan dan keputusan jangka pendek', 'MA 10.1-10.6 (hlm. 472-496)', 'luna', 'biaya relevan biaya tenggelam sunk cost keputusan incremental buat atau beli hentikan',
            'Hanya biaya dan pendapatan masa depan yang berbeda antar pilihan yang relevan. Biaya tenggelam (sudah dikeluarkan) tidak relevan. Contoh: pindah server, hentikan kanal iklan.',
            'laba inkremental = pendapatan inkremental - biaya inkremental', null],
        'capital_budgeting' => ['Keputusan investasi modal', 'MA 11.1-11.5 (hlm. 518-548); MK Bab V (hlm. 30-35)', 'luna', 'investasi modal payback npv nilai waktu uang present value irr arr',
            'Nilai uang hari ini lebih tinggi dari nanti. Payback mengukur lama kembali modal; NPV mendiskon arus kas masa depan. NPV ≥ 0 layak secara finansial.',
            'PV = FV / (1+i)^n; NPV = Σ CF_t/(1+i)^t - investasi; payback = investasi / arus kas tahunan', 'finance_metrics:capital'],
        'performance_measures' => ['Balanced scorecard dan ukuran kinerja', 'MA 12.1-12.4 (hlm. 566-592)', 'luna', 'balanced scorecard roi residual income kpi ukuran kinerja',
            'Kinerja dinilai dari sisi keuangan, pelanggan, proses internal, dan pembelajaran; ukuran yang baik relevan, dapat dikendalikan, dan tepat waktu.', 'ROI = laba operasi / aset operasi rata-rata', null],
        'cost_systems' => ['Sistem biaya (job order, proses, ABC)', 'MA 4-6 (hlm. 181-305)', 'luna', 'job order costing process costing activity based costing overhead',
            'Untuk perusahaan produksi. Relevansi bagi Yoka terbatas: ide alokasi biaya bersama (server, tim) ke layanan dapat dipakai untuk menghitung biaya per toko.', null, null],
        'working_capital' => ['Manajemen modal kerja, kas, piutang', 'MK Bab VII, VIII, XII, XIII (hlm. 41-45, 62-67)', 'luna', 'modal kerja manajemen kas sumber dan penggunaan dana saldo kas minimum',
            'Kas harus cukup untuk operasi tapi tidak menganggur; pantau penerimaan dan pengeluaran agar tahu kelebihan/kekurangan kas setiap saat.', null, 'finance_metrics:runway'],
        'capital_structure' => ['Struktur modal, biaya modal, risiko', 'MK Bab IX-XI (hlm. 46-61)', 'luna', 'struktur modal biaya modal wacc return risiko utang ekuitas',
            'Campuran utang dan modal sendiri mempengaruhi risiko dan biaya dana. Return yang lebih tinggi biasanya disertai risiko lebih tinggi.', null, null],
        'financial_management_role' => ['Peran manajemen keuangan', 'MK Bab I (hlm. 11-14)', 'luna', 'manajemen keuangan keputusan investasi pendanaan dividen tujuan',
            'Tiga keputusan utama: investasi (ke mana dana dipakai), pendanaan (dari mana dana), dan pembagian hasil. Tujuannya nilai usaha jangka panjang.', null, null],
        'saas_metrics' => ['Metrik SaaS (lapisan Yoka, bukan buku)', 'docs/finance-intelligence/metric-dictionary.md', 'luna', 'mrr arr arpa cac ltv churn burn runway payback pelanggan langganan',
            'MRR, ARR, ARPA, CAC, LTV, churn, burn, runway, payback dihitung oleh kode dari data langganan dan buku besar. Bila datanya kurang, katakan belum bisa dihitung.',
            null, 'finance_metrics:saas'],
        'midtrans_clearing' => ['Midtrans sebagai akun penampung', 'Aturan Yoka (berdasar FA 3.5, 8.6)', 'both', 'midtrans settlement pencairan fee clearing saldo tertahan payment gateway',
            'Pembayaran sukses = pendapatan + saldo Midtrans bertambah. Fee = beban. Pencairan ke bank = pindah dana, bukan pendapatan kedua. Pembayaran sukses belum tentu sudah cair.',
            null, 'ledger_review'],
    ];

    /** @return array<int,string> */
    public static function match(string $text, ?string $agent = null, int $limit = 3): array
    {
        $text = ' '.Str::lower($text).' ';
        $scores = [];
        foreach (self::CONCEPTS as $key => $c) {
            if ($agent && $c[2] !== 'both' && $c[2] !== $agent) {
                continue;
            }
            $score = 0;
            foreach (explode(' ', $c[3]) as $w) {
                if (mb_strlen($w) >= 3 && str_contains($text, ' '.$w)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scores[$key] = $score;
            }
        }
        arsort($scores);

        return array_slice(array_keys($scores), 0, $limit);
    }

    public static function lookup(string $text, ?string $agent = null): array
    {
        return array_map(function ($key) {
            [$title, $source, , , $summary, $formula, $tool] = self::CONCEPTS[$key];

            return array_filter(['konsep' => $title, 'sumber' => $source, 'ringkasan' => $summary, 'rumus' => $formula, 'hitung_dengan' => $tool]);
        }, self::match($text, $agent));
    }
}
