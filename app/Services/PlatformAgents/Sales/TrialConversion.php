<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\PlatformAiProspect;
use App\Models\PlatformAiTask;

/**
 * Status trial/langganan dibaca dari data billing (business_subscriptions), bukan dari spreadsheet atau obrolan.
 * Rekomendasi follow-up hanya draf; tidak ada yang terkirim tanpa "Setujui & kirim".
 */
final class TrialConversion
{
    public function business(PlatformAiProspect $p): ?Business
    {
        if ($p->matched_business_id && ($b = Business::find($p->matched_business_id))) {
            return $b;
        }
        if (! $p->phone_normalized) {
            return null;
        }
        $phone = $p->phone_normalized;
        $variants = [$phone, '0'.substr($phone, 2), '+'.$phone, substr($phone, 2)];

        return Business::whereIn('phone', $variants)->first()
            ?? Business::whereHas('owner', fn ($q) => $q->whereIn('phone', $variants))->first();
    }

    /** @return array{stage:string, business_id:?int, ends_at:?string, sandbox_payment:bool} */
    public function lifecycle(PlatformAiProspect $p): array
    {
        $b = $this->business($p);
        if (! $b) {
            return ['stage' => 'NOT_REGISTERED', 'business_id' => null, 'ends_at' => null, 'sandbox_payment' => false];
        }
        $sub = BusinessSubscription::where('business_id', $b->id)->latest('id')->first();
        $endsAt = $sub?->ends_at;
        $stage = match (true) {
            ! $sub => 'REGISTERED',
            in_array($sub->status, ['cancelled', 'canceled'], true) => 'CANCELED',
            $sub->paid_at !== null && (float) $sub->amount > 0 && $sub->isActive() => 'SUBSCRIBED',
            $sub->status === 'pending' && $sub->paid_at === null => 'PAYMENT_PENDING',
            $sub->status === 'trial' && $sub->isActive() && $endsAt && $endsAt->lte(now()->addDays((int) config('yoka_sales.trial_ending_days', 2))) => 'TRIAL_ENDING',
            $sub->status === 'trial' && $sub->isActive() => 'TRIAL_ACTIVE',
            $sub->status === 'trial' => 'TRIAL_EXPIRED',
            default => 'UNKNOWN',
        };

        return ['stage' => $stage, 'business_id' => $b->id, 'ends_at' => $endsAt?->toDateString(),
            // Midtrans sandbox: "lunas" bukan uang sungguhan. Ditandai agar tidak dihitung sebagai pendapatan nyata.
            'sandbox_payment' => $stage === 'SUBSCRIBED' && ! config('midtrans.is_production')];
    }

    /** Rekomendasi (bukan pesan terkirim). eligible=false bila tidak boleh dihubungi sama sekali. */
    public function recommend(PlatformAiProspect $p): array
    {
        $life = $this->lifecycle($p);
        $blocked = match (true) {
            $p->opted_out_at !== null || $p->sales_stage === 'opted_out' => 'Minta berhenti dihubungi.',
            $life['stage'] === 'SUBSCRIBED' => 'Sudah berlangganan: bukan sasaran pesan penjualan.',
            PlatformAiTask::where('type', 'whatsapp_draft')->where('status', 'proposed')->where('payload->prospect_id', $p->id)->exists() => 'Sudah ada draf yang menunggu persetujuan.',
            default => null,
        };
        $strategy = match ($life['stage']) {
            'NOT_REGISTERED' => 'Belum daftar trial. Ajak coba gratis bila kebutuhannya sudah jelas.',
            'REGISTERED' => 'Sudah daftar tapi belum ada langganan tercatat. Tanyakan apakah ada kendala saat mulai.',
            'TRIAL_ACTIVE' => 'Trial berjalan. Tanyakan satu hal yang ingin dia coba, tawarkan bantuan setup. Jangan menekan untuk bayar.',
            'TRIAL_ENDING' => 'Trial hampir habis (tanggal asli dari sistem). Tanyakan apakah sudah cocok; jelaskan cara pilih paket bila dia mau lanjut. Tanpa tenggat palsu.',
            'TRIAL_EXPIRED' => 'Trial selesai tanpa bayar. Tanyakan apa yang kurang cocok; hormati bila belum mau.',
            'PAYMENT_PENDING' => 'Pembayaran belum selesai. Teruskan ke tim bila ada kendala; jangan menyatakan status bayar sendiri.',
            'CANCELED' => 'Sudah berhenti. Jangan dihubungi untuk penjualan kecuali dia menghubungi duluan.',
            'SUBSCRIBED' => 'Pelanggan aktif. Layanan CS biasa, bukan penjualan.',
            default => 'Status tidak jelas: cek manual sebelum menghubungi.',
        };

        return $life + ['eligible' => $blocked === null && ! in_array($life['stage'], ['CANCELED', 'UNKNOWN'], true), 'blocked_reason' => $blocked, 'strategy' => $strategy];
    }
}
