<?php

namespace App\Services\PlatformAgents;

use App\Models\BusinessSubscription;
use App\Models\Order;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiTask;
use App\Services\PlatformWhatsApp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Agen follow-up trial. LLM memutuskan sendiri langkahnya lewat tool, tetapi
 * batas keras ada di kode ini, bukan di prompt:
 *  - hanya membaca data platform dan membuat DRAF (tidak pernah mengirim),
 *  - maksimal 5 draf per jalan, 1 draf per bisnis per 7 hari,
 *  - hanya bisnis yang trialnya berakhir <= 7 hari.
 * Pengiriman terjadi hanya setelah manusia menyetujui di papan tugas.
 */
class TrialAgent
{
    public const MAX_DRAFTS = 5;
    private const MAX_STEPS = 6;

    /** @var array<int,int> */
    private array $drafted = [];

    /** @return array{0:string,1:string,2:string,3:bool,4:array} format sama dengan agen lain */
    public function work(PlatformAiAgent $agent): array
    {
        $this->drafted = [];
        $candidates = $this->expiringTrials();
        $usedLlm = false;

        if ($candidates !== [] && filled(config('services.yoka_ai.key')) && $this->budgetAllows()) {
            $usedLlm = $this->runLoop($agent);
        }

        if (! $usedLlm) {
            foreach ($candidates as $c) {
                if (count($this->drafted) >= self::MAX_DRAFTS) {
                    break;
                }
                $this->createDraft($agent, $c['business_id'], $this->template($c));
            }
        }

        $n = count($this->drafted);
        $summary = $candidates === []
            ? 'Tidak ada trial yang berakhir dalam 7 hari.'
            : count($candidates)." trial segera berakhir; {$n} draf WhatsApp menunggu persetujuan.";

        return [$summary, $n ? 'Tinjau draf di papan tugas, edit bila perlu, lalu setujui untuk mengirim.' : 'Tidak ada tindakan baru.', 'medium', false, ['candidates' => count($candidates), 'drafts' => $n, 'ai_loop' => $usedLlm]];
    }

    /** @return array<int,array<string,mixed>> */
    public function expiringTrials(): array
    {
        $subs = BusinessSubscription::with('business.owner')
            ->where('status', 'trial')->whereBetween('ends_at', [today(), today()->addDays(7)])
            ->orderBy('ends_at')->limit(20)->get();

        return $subs->filter(fn ($s) => $s->business)->map(function ($s) {
            $b = $s->business;
            $orders = Order::withoutGlobalScopes()->where('business_id', $b->id)->where('created_at', '>=', now()->subDays(14))->count();

            return [
                'business_id' => $b->id, 'name' => $b->name, 'owner' => $b->owner?->name,
                'phone' => $b->phone ?: $b->owner?->phone, 'ends_at' => $s->ends_at->toDateString(),
                'days_left' => (int) today()->diffInDays($s->ends_at, false),
                'orders_14d' => $orders,
            ];
        })->values()->all();
    }

    private function template(array $c): string
    {
        $nama = $c['owner'] ?: 'Kak';
        $sisa = $c['days_left'] <= 0 ? 'berakhir hari ini' : "berakhir {$c['days_left']} hari lagi";
        $pakai = $c['orders_14d'] > 0
            ? "Senang melihat {$c['name']} sudah mencatat {$c['orders_14d']} transaksi di Yoka. "
            : "Kami lihat {$c['name']} belum banyak mencoba kasir Yoka. ";

        return "Halo {$nama}, ini tim Yoka POS. {$pakai}Masa trial Anda {$sisa}. Ada yang bisa kami bantu supaya setup-nya sesuai kebutuhan outlet? Balas pesan ini kapan saja.";
    }

    private function createDraft(PlatformAiAgent $agent, int $businessId, string $message): array
    {
        $message = trim(mb_substr($message, 0, 600));
        $c = collect($this->expiringTrials())->firstWhere('business_id', $businessId);
        if (! $c) {
            return ['ok' => false, 'error' => 'Bisnis tidak termasuk trial yang berakhir <= 7 hari.'];
        }
        if (count($this->drafted) >= self::MAX_DRAFTS) {
            return ['ok' => false, 'error' => 'Batas draf per jalan tercapai.'];
        }
        if (! $c['phone'] || ! PlatformWhatsApp::normalize($c['phone'])) {
            return ['ok' => false, 'error' => 'Bisnis tidak punya nomor WhatsApp valid.'];
        }
        $dup = PlatformAiTask::where('type', 'whatsapp_draft')->where('created_at', '>=', now()->subDays(7))
            ->where('status', '!=', 'rejected')->where('payload->business_id', $businessId)->exists();
        if ($dup || in_array($businessId, $this->drafted, true)) {
            return ['ok' => false, 'error' => 'Sudah ada draf untuk bisnis ini dalam 7 hari.'];
        }

        $agent->tasks()->create([
            'type' => 'whatsapp_draft', 'title' => "Follow-up trial · {$c['name']}",
            'summary' => $message, 'recommendation' => "Trial berakhir {$c['ends_at']}; {$c['orders_14d']} transaksi dalam 14 hari.",
            'risk' => 'medium', 'requires_approval' => true, 'status' => 'proposed',
            'payload' => ['business_id' => $businessId, 'phone' => $c['phone'], 'channel' => 'whatsapp'],
        ]);
        $this->drafted[] = $businessId;

        return ['ok' => true, 'business' => $c['name']];
    }

    private function budgetAllows(): bool
    {
        $key = 'platform-ai:'.now()->format('Y-m-d');
        if (Cache::increment($key) > 50) {
            return false;
        }
        Cache::put($key, Cache::get($key), now()->endOfDay());

        return true;
    }

    private function tools(): array
    {
        return [
            ['type' => 'function', 'function' => ['name' => 'list_expiring_trials', 'description' => 'Daftar bisnis yang trialnya berakhir dalam 7 hari beserta pemakaian 14 hari terakhir.', 'parameters' => ['type' => 'object', 'properties' => new \stdClass]]],
            ['type' => 'function', 'function' => ['name' => 'draft_whatsapp', 'description' => 'Buat DRAF pesan WhatsApp untuk satu bisnis. Tidak terkirim sampai manusia menyetujui. Maks 600 karakter, bahasa Indonesia ramah, tanpa janji diskon atau harga.', 'parameters' => ['type' => 'object', 'properties' => ['business_id' => ['type' => 'integer'], 'message' => ['type' => 'string']], 'required' => ['business_id', 'message']]]],
        ];
    }

    private function runLoop(PlatformAiAgent $agent): bool
    {
        $messages = [
            ['role' => 'system', 'content' => "Kamu adalah {$agent->name} di tim internal Yoka POS. Tujuan: membantu pemilik toko yang trialnya segera berakhir supaya berhasil memakai Yoka. Panggil list_expiring_trials, lalu buat draf WhatsApp personal (sebut nama bisnis dan pemakaian nyata mereka) lewat draft_whatsapp. Jangan mengarang angka, jangan menjanjikan diskon atau harga, jangan menekan. Pesanmu hanya draf; manusia yang menyetujui. Selesai bila semua kandidat sudah dibuat drafnya."],
            ['role' => 'user', 'content' => 'Jalankan follow-up trial hari ini.'],
        ];

        try {
            for ($step = 0; $step < self::MAX_STEPS; $step++) {
                $res = Http::baseUrl(rtrim((string) config('services.yoka_ai.url'), '/'))
                    ->withToken(config('services.yoka_ai.key'))->timeout((int) config('services.yoka_ai.timeout', 25))
                    ->post('/chat/completions', [
                        'model' => config('services.yoka_ai.deep_model') ?: config('services.yoka_ai.model'),
                        'temperature' => 0.3, 'messages' => $messages, 'tools' => $this->tools(), 'tool_choice' => 'auto',
                    ])->throw();
                $msg = data_get($res->json(), 'choices.0.message');
                if (! is_array($msg)) {
                    return false;
                }
                $messages[] = $msg;
                if (empty($msg['tool_calls'])) {
                    return true;
                }
                foreach ($msg['tool_calls'] as $call) {
                    $args = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                    $result = match ($call['function']['name'] ?? '') {
                        'list_expiring_trials' => $this->expiringTrials(),
                        'draft_whatsapp' => $this->createDraft($agent, (int) ($args['business_id'] ?? 0), (string) ($args['message'] ?? '')),
                        default => ['error' => 'Tool tidak dikenal.'],
                    };
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => json_encode($result, JSON_UNESCAPED_UNICODE)];
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Trial agent loop failed', ['error' => $e->getMessage()]);

            return $this->drafted !== [];
        }
    }
}
