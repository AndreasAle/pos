<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\SubscriptionPlan;

/**
 * Otoritas harga ada di sini, bukan di prompt. Harga resmi dibaca dari subscription_plans.
 * Adit hanya penasihat: permintaan di bawah harga resmi tidak pernah disetujui otomatis.
 */
final class CommercialPolicy
{
    public const STANDARD_OFFER = 'STANDARD_OFFER';

    public const APPROVED_PROMOTION = 'APPROVED_PROMOTION';

    public const NEEDS_HUMAN_APPROVAL = 'NEEDS_HUMAN_APPROVAL';

    public const INSUFFICIENT_DATA = 'INSUFFICIENT_DATA';

    public const NOT_COMMERCIALLY_VIABLE = 'NOT_COMMERCIALLY_VIABLE';

    public const REJECTED_BY_POLICY = 'REJECTED_BY_POLICY';

    private const COMMERCIAL = '/\b(diskon|discount|potongan|promo|voucher|nego|kurangin|murahin|harga khusus|harga spesial|paket (custom|khusus)|custom paket|budget|bujet|cuma (ada )?(rp|\d)|segini|mahal|gratis(in)? (aja|selamanya|terus)|free (plan|selamanya)|aktifkan gratis)\b|\b(kalau|kalo|klo)\s+(rp\.?\s?)?\d{2,3}(\.\d{3})?\s*(rb|ribu|k)?\b.{0,15}\b(bisa|boleh|gimana)\b|\brp\.?\s?\d{2,3}\s*(rb|ribu|k)\b.{0,10}\b(bisa|boleh)\b/iu';

    private const FREE_DEMAND = '/\b(gratis(in)? (aja|selamanya|terus)|free (plan|selamanya|forever)|aktifkan gratis|tanpa bayar selamanya)\b/iu';

    private const CUSTOM = '/\b(paket (custom|khusus)|custom paket|fitur (khusus|tambahan) (buat|untuk) (saya|aku|kami)|bikinin fitur)\b/iu';

    /** @return \Illuminate\Support\Collection<int,SubscriptionPlan> */
    public function officialPlans()
    {
        return SubscriptionPlan::where('is_active', true)->where('price', '>', 0)->orderBy('price')->get(['name', 'slug', 'price', 'max_outlets', 'max_users']);
    }

    public function isCommercialRequest(string $text): bool
    {
        return (bool) preg_match(self::COMMERCIAL, $text) || (bool) preg_match(ConversationStrategy::BUNDLE, $text);
    }

    /** Harga yang diminta prospek, mis. "Rp50 ribu", "50rb", "50.000", "50k". null bila tidak ada angka harga yang jelas. */
    public function requestedPrice(string $text): ?float
    {
        if (! preg_match('/(?:rp\.?\s*)?(\d{1,3}(?:[.,]\d{3})+|\d{1,7})\s*(rb|ribu|k|jt|juta)?\b/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $raw = $m[0][0];
        $hasCurrency = (bool) preg_match('/rp|rb|ribu|k\b|jt|juta/iu', $raw);
        $n = (float) str_replace(['.', ','], '', $m[1][0]);
        $mult = match (mb_strtolower($m[2][0] ?? '')) { 'rb', 'ribu', 'k' => 1000, 'jt', 'juta' => 1000000, default => 1 };
        $value = $n * $mult;
        // Angka tanpa satuan uang di bawah 1.000 (mis. "2 outlet") bukan harga.
        if (! $hasCurrency && $value < 1000) {
            return null;
        }

        return $value > 0 ? $value : null;
    }

    /**
     * Evaluasi permintaan komersial. Hasilnya keputusan kebijakan, bukan teks untuk prospek.
     *
     * @return array{state:string, requested_price:?float, official_plan:?string, official_price:?float, discount_pct:?float, analysis:array, customer_guidance:string, needs_approval:bool}
     */
    public function evaluate(string $text): array
    {
        $plans = $this->officialPlans();
        $requested = $this->requestedPrice($text);
        $base = ['requested_price' => $requested, 'official_plan' => null, 'official_price' => null, 'discount_pct' => null];

        if (preg_match(self::FREE_DEMAND, $text)) {
            return $base + ['state' => self::REJECTED_BY_POLICY, 'needs_approval' => false,
                'analysis' => ['reason' => 'Permintaan paket gratis permanen tidak ada di kebijakan.'],
                'customer_guidance' => 'Paket gratis permanen tidak ada. Satu-satunya yang gratis adalah trial 7 hari. Tawarkan trial tanpa menjanjikan apa pun lagi.'];
        }
        // Paket aplikasi + perangkat: belum ada katalog/bundel resmi, jadi selalu diputuskan manusia.
        if (preg_match(ConversationStrategy::BUNDLE, $text)) {
            $first = $plans->first();
            if ($bundle = app(HardwareCatalog::class)->approvedBundle()) {
                return array_merge($base, ['official_plan' => $bundle['name'], 'official_price' => (float) $bundle['initial_price']])
                    + ['state' => self::STANDARD_OFFER, 'needs_approval' => false, 'analysis' => ['reason' => 'Bundel resmi tersedia.'],
                        'customer_guidance' => 'Sebut bundel resmi '.$bundle['name'].' dengan harganya persis; pembayaran tetap lewat checkout resmi.'];
            }
            $candidate = collect((array) config('yoka_hardware.items'))->first();

            return array_merge($base, ['official_plan' => $first?->name, 'official_price' => $first ? (float) $first->price : null, 'items' => ['printer/perangkat']])
                + ['state' => self::NEEDS_HUMAN_APPROVAL, 'needs_approval' => true,
                    'analysis' => ['reason' => 'Permintaan penawaran aplikasi + perangkat; belum ada bundel resmi yang disetujui.', 'cost_status' => self::INSUFFICIENT_DATA,
                        'kind' => 'hardware_quotation', 'candidate_item' => $candidate ? ($candidate['model'].' (kompatibilitas: '.$candidate['compatibility'].', harga beli referensi: '.($candidate['purchase_cost'] ?? '-').')') : null,
                        'draft_bundle' => config('yoka_hardware.bundles.starter-kit.initial_price'), 'missing_terms' => array_keys(array_filter((array) config('yoka_hardware.bundles.starter-kit.terms'), fn ($v) => $v === null))],
                    'customer_guidance' => 'Langganan tidak termasuk perangkat dan belum ada paket bundel resmi. Bilang kamu tanyakan ke tim; jangan sebut harga perangkat, jangan janji printer gratis.'];
        }
        if ($plans->isEmpty()) {
            return $base + ['state' => self::INSUFFICIENT_DATA, 'needs_approval' => true,
                'analysis' => ['reason' => 'Harga resmi tidak tersedia di sistem (tidak ada paket aktif).'],
                'customer_guidance' => 'Harga resmi sedang tidak bisa dicek. Jangan sebut angka apa pun; bilang kamu cek dulu ke tim.'];
        }
        $cheapest = $plans->first();
        if (preg_match(self::CUSTOM, $text)) {
            return array_merge($base, ['official_plan' => $cheapest->name, 'official_price' => (float) $cheapest->price]) + ['state' => self::NEEDS_HUMAN_APPROVAL, 'needs_approval' => true,
                'analysis' => ['reason' => 'Permintaan paket/fitur custom.', 'cost_status' => self::INSUFFICIENT_DATA],
                'customer_guidance' => 'Paket custom perlu dicek tim. Jangan menjanjikan fitur atau harga. Tanyakan satu kebutuhan utamanya.'];
        }
        if ($requested === null) {
            return array_merge($base, ['official_plan' => $cheapest->name, 'official_price' => (float) $cheapest->price]) + ['state' => self::STANDARD_OFFER, 'needs_approval' => false,
                'analysis' => ['reason' => 'Tanya diskon tanpa angka; tidak ada promo resmi yang aktif.'],
                'customer_guidance' => 'Saat ini tidak ada promo resmi. Jelaskan paket resmi dan trial gratis 7 hari, lalu tanyakan kebutuhan utamanya supaya paketnya pas.'];
        }

        // Paket resmi termahal yang masih masuk budget: itu penawaran standar, bukan diskon.
        $fit = $plans->filter(fn ($p) => (float) $p->price <= $requested)->last();
        if ($fit) {
            return array_merge($base, ['official_plan' => $fit->name, 'official_price' => (float) $fit->price, 'discount_pct' => 0.0]) + ['state' => self::STANDARD_OFFER, 'needs_approval' => false,
                'analysis' => ['reason' => "Budget cukup untuk paket resmi {$fit->name}."],
                'customer_guidance' => "Budgetnya cukup untuk paket resmi {$fit->name}. Jelaskan isi paket itu dengan harga resminya."];
        }

        $official = (float) $cheapest->price;
        $discount = round((1 - $requested / $official) * 100, 1);
        $analysis = $this->economics($requested, $official) + ['discount_pct' => $discount, 'cheapest_plan' => $cheapest->name];
        $base = array_merge($base, ['official_plan' => $cheapest->name, 'official_price' => $official, 'discount_pct' => $discount]);

        if ($discount <= (float) config('yoka_sales.approved_discount_pct', 0)) {
            return $base + ['state' => self::APPROVED_PROMOTION, 'needs_approval' => false, 'analysis' => $analysis,
                'customer_guidance' => 'Masuk promo resmi yang berlaku. Sebut hanya harga resmi dan promo yang tercatat.'];
        }
        $floor = config('yoka_sales.floor_price');
        if ($floor !== null && $requested < (float) $floor) {
            return $base + ['state' => self::NOT_COMMERCIALLY_VIABLE, 'needs_approval' => false, 'analysis' => $analysis + ['reason' => 'Di bawah batas harga minimum internal.'],
                'customer_guidance' => 'Harga segitu belum bisa. Tolak dengan sopan tanpa menyebut angka internal; tawarkan trial gratis dan paket resmi termurah.'];
        }

        return $base + ['state' => $analysis['cost_status'] === self::INSUFFICIENT_DATA ? self::INSUFFICIENT_DATA : self::NEEDS_HUMAN_APPROVAL, 'needs_approval' => true, 'analysis' => $analysis,
            'customer_guidance' => 'Harga khusus perlu dicek tim dulu. Jangan janji disetujui dan jangan sebut angka selain harga resmi. Tanyakan satu kebutuhan utamanya (kasir saja, atau juga stok dan laporan).'];
    }

    /** Ekonomi sederhana. Biaya yang tidak diketahui TIDAK dianggap nol. */
    private function economics(float $requested, float $official): array
    {
        $cost = config('yoka_sales.cost_per_customer_month');
        $fee = config('yoka_sales.payment_fee_pct');
        if ($cost === null || $fee === null) {
            return ['cost_status' => self::INSUFFICIENT_DATA, 'missing' => array_values(array_filter([$cost === null ? 'cost_per_customer_month' : null, $fee === null ? 'payment_fee_pct' : null])),
                'revenue_requested' => $requested, 'revenue_official' => $official, 'note' => 'Pendapatan bukan laba; margin tidak dihitung karena data biaya belum ada.'];
        }
        $contribution = $requested * (1 - $fee / 100) - $cost;

        return ['cost_status' => 'KNOWN', 'revenue_requested' => $requested, 'revenue_official' => $official, 'cost_per_customer_month' => (float) $cost, 'payment_fee_pct' => (float) $fee,
            'contribution_per_month' => round($contribution, 0), 'contribution_margin_pct' => $requested > 0 ? round($contribution / $requested * 100, 1) : null];
    }

    /**
     * Buang kalimat balasan yang menyebut harga selain harga resmi, atau menjanjikan paket gratis/aktivasi gratis.
     *
     * @return array{0:string,1:array<int,string>}
     */
    public function scrubReply(string $reply): array
    {
        $allowed = array_merge($this->officialPlans()->map(fn ($p) => (int) round((float) $p->price))->all(), app(HardwareCatalog::class)->officialPrices());
        $removed = [];
        $sentences = preg_split('/(?<=[.!?])\s+|\n/u', $reply) ?: [];
        $kept = [];
        foreach ($sentences as $s) {
            $bad = (bool) preg_match(self::FREE_DEMAND, $s) || (bool) preg_match('/\b(sudah|udah) (aku |saya )?(aktifkan|setujui|approve)\b|\bharga khusus (disetujui|bisa)\b/iu', $s);
            if (! $bad && preg_match_all('/rp\.?\s*(\d{1,3}(?:[.,]\d{3})+|\d+)\s*(rb|ribu|k)?/iu', $s, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    $v = (int) str_replace(['.', ','], '', $m[1]) * (in_array(mb_strtolower($m[2] ?? ''), ['rb', 'ribu', 'k'], true) ? 1000 : 1);
                    if (! in_array($v, $allowed, true)) {
                        $bad = true;
                    }
                }
            }
            $bad ? $removed[] = $s : $kept[] = $s;
        }

        return [trim(implode(' ', $kept)), $removed];
    }
}
