<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceActionApproval;
use App\Models\IntelligenceActionProposal;
use App\Models\IntelligenceAuditEvent;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActionService
{
    public function prepareProductAvailability(User $actor, string $productQuery, bool $available): IntelligenceActionProposal
    {
        $this->authorize($actor);
        $matches = Product::forBusiness($actor->business_id)->where('name', 'like', '%'.addcslashes($productQuery, '%_').'%')->limit(2)->get();
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['product' => $matches->isEmpty() ? 'Produk tidak ditemukan.' : 'Nama produk ambigu. Sebutkan nama yang lebih spesifik.']);
        }
        $product = $matches->first();
        $payload = ['product_id' => $product->id, 'is_active' => $available];
        $preview = ['title' => $available ? 'Aktifkan produk' : 'Tandai produk tidak tersedia', 'target' => $product->name, 'changes' => [['field' => 'Ketersediaan', 'before' => $product->is_active ? 'Tersedia' : 'Tidak tersedia', 'after' => $available ? 'Tersedia' : 'Tidak tersedia']]];

        return IntelligenceActionProposal::create([
            'business_id' => $actor->business_id, 'outlet_id' => $product->outlet_id, 'actor_id' => $actor->id,
            'action_type' => 'product.availability', 'payload' => $payload, 'preview' => $preview,
            'payload_hash' => $this->payloadHash($payload), 'expected_version' => $product->updated_at->toJSON(),
            'idempotency_key' => (string) Str::uuid(), 'status' => 'pending', 'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function approve(User $actor, IntelligenceActionProposal $proposal, string $confirmation): IntelligenceActionProposal
    {
        $this->authorizeProposal($actor, $proposal);
        if ($confirmation !== 'SETUJUI') {
            throw ValidationException::withMessages(['confirmation' => 'Konfirmasi eksplisit SETUJUI diperlukan.']);
        }
        if ($proposal->status !== 'pending' || $proposal->expires_at->isPast()) {
            throw ValidationException::withMessages(['proposal' => 'Proposal sudah tidak dapat disetujui.']);
        }
        $this->validateHash($proposal);
        IntelligenceActionApproval::create(['proposal_id' => $proposal->id, 'approver_id' => $actor->id, 'decision' => 'approved', 'decided_at' => now()]);
        $proposal->update(['status' => 'approved']);

        return $proposal->fresh();
    }

    public function execute(User $actor, IntelligenceActionProposal $proposal): IntelligenceActionProposal
    {
        $this->authorizeProposal($actor, $proposal);
        if ($proposal->status === 'executed') {
            return $proposal;
        }
        if ($proposal->status !== 'approved' || $proposal->expires_at->isPast()) {
            throw ValidationException::withMessages(['proposal' => 'Proposal belum disetujui atau sudah kedaluwarsa.']);
        }
        $this->validateHash($proposal);

        return DB::transaction(function () use ($actor, $proposal) {
            $locked = IntelligenceActionProposal::lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status === 'executed') {
                return $locked;
            }
            $product = Product::forBusiness($actor->business_id)->lockForUpdate()->findOrFail($locked->payload['product_id']);
            if ($product->updated_at->toJSON() !== $locked->expected_version) {
                throw ValidationException::withMessages(['proposal' => 'Data produk berubah. Buat preview baru sebelum menjalankan aksi.']);
            }
            $before = ['is_active' => $product->is_active];
            $product->update(['is_active' => (bool) $locked->payload['is_active']]);
            $after = ['is_active' => $product->fresh()->is_active];
            IntelligenceAuditEvent::create(['business_id' => $actor->business_id, 'actor_id' => $actor->id, 'object_type' => Product::class, 'object_id' => $product->id, 'event' => 'intelligence.product_availability.executed', 'before' => $before, 'after' => $after, 'happened_at' => now()]);
            $locked->update(['status' => 'executed', 'executed_at' => now(), 'result' => ['before' => $before, 'after' => $after]]);

            return $locked->fresh();
        });
    }

    private function authorize(User $actor): void
    {
        abort_unless(config('pos.features.intelligence_actions') && in_array($actor->role, ['owner', 'admin'], true), 403);
    }

    private function authorizeProposal(User $actor, IntelligenceActionProposal $proposal): void
    {
        $this->authorize($actor);
        abort_unless($proposal->business_id === $actor->business_id, 403);
    }

    private function validateHash(IntelligenceActionProposal $proposal): void
    {
        if (! hash_equals($proposal->payload_hash, $this->payloadHash($proposal->payload))) {
            throw ValidationException::withMessages(['proposal' => 'Integritas payload proposal gagal.']);
        }
    }

    private function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload));
    }
}
