<?php

namespace App\Services;

use App\Models\AiCreditEntry;
use App\Models\AiCreditTopup;
use App\Models\AiCreditWallet;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saldo kredit Tanya Yoka per bisnis.
 *
 * Dua kantong: gratis (jatah bulanan dari paket, hangus akhir bulan) dan berbayar (top-up,
 * berlaku 12 bulan sejak top-up terakhir). Pemakaian memotong kantong gratis dulu.
 * Saldo tidak pernah minus; semua perubahan tercatat di ai_credit_entries.
 */
class AiCredits
{
    public function enabled(): bool
    {
        return (bool) config('yoka_ai_credits.enabled');
    }

    /** @return array{free:int,paid:int,total:int,allowance:int,paid_expires_at:?string} */
    public function balance(Business $business): array
    {
        $w = $this->wallet($business);

        return ['free' => $w->free_balance, 'paid' => $w->paid_balance, 'total' => $w->total(),
            'allowance' => $this->monthlyAllowance($business), 'paid_expires_at' => $w->paid_expires_at?->toDateString()];
    }

    public function canAsk(Business $business): bool
    {
        return ! $this->enabled() || $this->wallet($business)->total() >= 1;
    }

    /** Kredit untuk satu jawaban dari pemakaian token sebenarnya. 0 = model tidak dipanggil. */
    public function creditsFor(?array $usage): int
    {
        $weighted = (int) ($usage['prompt_tokens'] ?? 0) + (int) config('yoka_ai_credits.output_weight', 4) * (int) ($usage['completion_tokens'] ?? 0);
        if ($weighted <= 0) {
            return 0;
        }

        return (int) min((int) config('yoka_ai_credits.max_credits_per_answer', 10),
            max(1, (int) ceil($weighted / max(1, (int) config('yoka_ai_credits.tokens_per_credit', 12000)))));
    }

    /**
     * Potong kredit untuk satu jawaban. Idempoten per reference (mis. "msg:123").
     * Dipotong dari kantong gratis dulu, dibatasi sisa saldo (tidak pernah minus).
     */
    public function charge(Business $business, ?array $usage, string $reference): int
    {
        $credits = $this->creditsFor($usage);
        if (! $this->enabled() || $credits === 0) {
            return 0;
        }

        return DB::transaction(function () use ($business, $usage, $reference, $credits) {
            $w = $this->lockedWallet($business);
            if (AiCreditEntry::where('business_id', $business->id)->where('kind', 'usage')->where('reference', $reference)->exists()) {
                return 0;
            }
            $fromFree = min($credits, $w->free_balance);
            $fromPaid = min($credits - $fromFree, $w->paid_balance);
            if ($fromFree + $fromPaid === 0) {
                return 0;
            }
            $w->update(['free_balance' => $w->free_balance - $fromFree, 'paid_balance' => $w->paid_balance - $fromPaid]);
            $this->log($w, 'usage', -$fromFree, -$fromPaid, $reference, ['credits_due' => $credits, 'tokens' => array_intersect_key((array) $usage, array_flip(['prompt_tokens', 'completion_tokens']))]);

            return $fromFree + $fromPaid;
        });
    }

    /** Tandai top-up lunas dan tambahkan kredit. Aman dipanggil berulang (webhook dikirim ulang). */
    public function completeTopup(AiCreditTopup $topup): bool
    {
        $added = DB::transaction(function () use ($topup) {
            $locked = AiCreditTopup::query()->lockForUpdate()->findOrFail($topup->id);
            if ($locked->status === 'paid') {
                return false;
            }
            $w = $this->lockedWallet($locked->business);
            $w->update([
                'paid_balance' => $w->paid_balance + $locked->credits,
                'paid_expires_at' => now()->addMonthsNoOverflow((int) config('yoka_ai_credits.paid_validity_months', 12))->toDateString(),
            ]);
            $this->log($w, 'topup', 0, $locked->credits, $locked->payment_token, ['pack' => $locked->pack, 'amount' => (float) $locked->amount]);
            $locked->update(['status' => 'paid', 'paid_at' => now()]);

            return true;
        });

        if ($added) {
            try {
                app(\App\Services\PlatformFinance\Transactions::class)->aiCreditsPaid($topup->fresh());
            } catch (\Throwable $e) {
                Log::warning('Platform ledger: AI credit top-up not posted', ['topup_id' => $topup->id, 'error' => class_basename($e)]);
            }
        }

        return $added;
    }

    public function monthlyAllowance(Business $business): int
    {
        $slug = $business->activeSubscription()->with('plan')->first()?->plan?->slug;

        return $slug ? (int) (config('yoka_ai_credits.free_monthly')[$slug] ?? 0) : 0;
    }

    /** Dompet dengan jatah bulan ini dan kedaluwarsa sudah diterapkan. */
    public function wallet(Business $business): AiCreditWallet
    {
        $w = AiCreditWallet::firstOrCreate(['business_id' => $business->id]);
        if ($w->free_period === now()->format('Y-m') && ! $this->paidExpired($w)) {
            return $w;
        }

        return DB::transaction(fn () => $this->lockedWallet($business));
    }

    private function lockedWallet(Business $business): AiCreditWallet
    {
        AiCreditWallet::firstOrCreate(['business_id' => $business->id]);
        $w = AiCreditWallet::where('business_id', $business->id)->lockForUpdate()->first();

        $period = now()->format('Y-m');
        if ($w->free_period !== $period) {
            if (($left = $w->free_balance) > 0) {
                $w->update(['free_balance' => 0]);
                $this->log($w, 'expire_free', -$left, 0, 'free:'.$w->free_period);
            }
            $allowance = $this->monthlyAllowance($business);
            $w->update(['free_balance' => $allowance, 'free_period' => $period]);
            if ($allowance > 0) {
                $this->log($w, 'grant_monthly', $allowance, 0, $period);
            }
        }
        if ($this->paidExpired($w)) {
            $expired = $w->paid_balance;
            $w->update(['paid_balance' => 0]);
            $this->log($w, 'expire_paid', 0, -$expired, 'paid:'.$w->paid_expires_at->toDateString());
        }

        return $w;
    }

    private function paidExpired(AiCreditWallet $w): bool
    {
        return $w->paid_balance > 0 && $w->paid_expires_at && $w->paid_expires_at->isPast();
    }

    private function log(AiCreditWallet $w, string $kind, int $free, int $paid, ?string $reference, ?array $meta = null): void
    {
        AiCreditEntry::create(['business_id' => $w->business_id, 'kind' => $kind, 'free_change' => $free, 'paid_change' => $paid,
            'free_after' => $w->free_balance, 'paid_after' => $w->paid_balance, 'reference' => $reference, 'meta' => $meta]);
    }
}
