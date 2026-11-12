<?php

namespace App\Services;

use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YokaAssistantService
{
    public function __construct(private YokaToolRegistry $tools) {}

    public function ask(User $user, string $question, ?int $conversationId = null): array
    {
        $conversation = $conversationId
            ? AssistantConversation::where('business_id', $user->business_id)->where('user_id', $user->id)->findOrFail($conversationId)
            : AssistantConversation::create(['business_id' => $user->business_id, 'user_id' => $user->id, 'title' => Str::limit($question, 60)]);
        $conversation->messages()->create(['role' => 'user', 'content' => $question]);
        [$tool, $args] = $this->selectTool($question);
        if (config('services.yoka_ai.key')) [$tool, $args] = $this->selectWithLlm($question) ?? [$tool, $args];
        $result = $this->tools->execute($user->business, $tool, $args);
        $answer = config('services.yoka_ai.key')
            ? ($this->explainWithLlm($question, $tool, $result) ?? $this->localAnswer($tool, $result))
            : $this->localAnswer($tool, $result);
        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => $answer, 'tool_name' => $tool, 'meta' => ['arguments' => $args, 'result' => $result]]);
        $conversation->touch();
        return ['conversation_id' => $conversation->id, 'message_id' => $message->id, 'answer' => $answer, 'tool' => $tool, 'data' => $result];
    }

    private function selectTool(string $question): array
    {
        $q = Str::lower($question);
        $tool = match (true) {
            Str::contains($q, ['stok', 'stock', 'bahan', 'habis']) => 'stock_status',
            Str::contains($q, ['produk', 'menu', 'laris', 'margin']) => 'product_performance',
            Str::contains($q, ['jam ramai', 'jam sibuk', 'pukul', 'waktu ramai']) => 'peak_hours',
            Str::contains($q, ['pembayaran', 'qris', 'tunai', 'cash', 'debit']) => 'payment_breakdown',
            Str::contains($q, ['outlet', 'cabang']) => 'outlet_performance',
            Str::contains($q, ['pelanggan', 'customer', 'member']) => 'customer_performance',
            Str::contains($q, ['shift', 'kasir', 'siapa jaga']) => 'open_shifts',
            default => 'sales_summary',
        };
        $period = match (true) {
            Str::contains($q, 'kemarin') => 'yesterday', Str::contains($q, 'minggu lalu') => 'last_week',
            Str::contains($q, 'minggu ini') => 'this_week', Str::contains($q, 'bulan lalu') => 'last_month',
            Str::contains($q, 'bulan ini') => 'this_month', Str::contains($q, ['30 hari', 'sebulan terakhir']) => 'last_30_days',
            default => 'today',
        };
        return [$tool, ['period' => $period]];
    }

    private function selectWithLlm(string $question): ?array
    {
        try {
            $fn = $this->client()->post('/chat/completions', [
                'model' => config('services.yoka_ai.model'), 'temperature' => 0,
                'messages' => [['role' => 'system', 'content' => 'Pilih tepat satu tool untuk menjawab pertanyaan operasional POS. Jangan menjawab sendiri.'], ['role' => 'user', 'content' => $question]],
                'tools' => $this->tools->definitions(), 'tool_choice' => 'required',
            ])->throw()->json('choices.0.message.tool_calls.0.function');
            return $fn && is_string($fn['name'] ?? null) ? [$fn['name'], json_decode($fn['arguments'] ?? '{}', true) ?: []] : null;
        } catch (\Throwable) { return null; }
    }

    private function explainWithLlm(string $question, string $tool, array $result): ?string
    {
        try {
            return $this->client()->post('/chat/completions', [
                'model' => config('services.yoka_ai.model'), 'temperature' => .2,
                'messages' => [
                    ['role' => 'system', 'content' => 'Kamu Yoka, analis bisnis POS Indonesia. Jawab ringkas, ramah, hanya berdasarkan hasil tool. Gunakan Rupiah. Jangan mengarang. Beri satu saran praktis bila relevan.'],
                    ['role' => 'user', 'content' => $question],
                    ['role' => 'system', 'content' => 'Hasil tool '.$tool.': '.json_encode($result, JSON_UNESCAPED_UNICODE)],
                ],
            ])->throw()->json('choices.0.message.content');
        } catch (\Throwable) { return null; }
    }

    private function client() { return Http::baseUrl(rtrim(config('services.yoka_ai.url'), '/'))->withToken(config('services.yoka_ai.key'))->timeout(20); }

    private function localAnswer(string $tool, array $r): string
    {
        $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        return match ($tool) {
            'sales_summary' => ($r['orders'] ?? 0) ? "{$r['label']}: omzet {$rp($r['revenue'])} dari {$r['orders']} transaksi, rata-rata {$rp($r['average_order'])}, dan estimasi laba {$rp($r['profit'])}.".($r['change_percent'] !== null ? ' Dibanding periode sebelumnya '.($r['change_percent'] >= 0 ? 'naik ' : 'turun ').abs($r['change_percent']).'%.' : '') : "Belum ada transaksi {$r['label']}.",
            'product_performance' => empty($r['products']) ? "Belum ada produk terjual {$r['label']}." : "Produk teratas {$r['label']}: ".collect($r['products'])->take(5)->map(fn ($x) => "{$x['name']} ({$x['quantity']} terjual, {$rp($x['revenue'])}, margin {$x['margin_percent']}%)")->join('; ').'.',
            'stock_status' => empty($r['ingredients']) ? 'Data bahan tidak ditemukan.' : collect($r['ingredients'])->filter(fn ($x) => $x['low'] || ($x['estimated_days_left'] !== null && $x['estimated_days_left'] <= 3))->whenEmpty(fn () => collect($r['ingredients'])->take(5))->map(fn ($x) => "{$x['name']}: {$x['stock']} {$x['unit']}".($x['estimated_days_left'] !== null ? " (~{$x['estimated_days_left']} hari)" : ''))->join('; ').'.',
            'peak_hours' => empty($r['hours']) ? "Belum cukup transaksi {$r['label']} untuk membaca jam ramai." : 'Jam paling ramai '.$r['label'].': '.collect($r['hours'])->map(fn ($x) => sprintf('%02d.00 (%d transaksi)', $x['hour'], $x['orders']))->join(', ').'.',
            'payment_breakdown' => empty($r['payments']) ? "Belum ada pembayaran {$r['label']}." : 'Pembayaran '.$r['label'].': '.collect($r['payments'])->map(fn ($x) => ucfirst($x['payment_method']).' '.$rp($x['total']))->join(', ').'.',
            'outlet_performance' => empty($r['outlets']) ? "Belum ada data outlet {$r['label']}." : 'Performa outlet '.$r['label'].': '.collect($r['outlets'])->map(fn ($x) => $x['name'].' '.$rp($x['revenue']))->join(', ').'.',
            'customer_performance' => empty($r['customers']) ? "Belum ada data pelanggan {$r['label']}." : 'Pelanggan teratas '.$r['label'].': '.collect($r['customers'])->take(5)->map(fn ($x) => $x['name'].' '.$rp($x['spending']))->join(', ').'.',
            'open_shifts' => empty($r['shifts']) ? 'Tidak ada shift kasir yang sedang terbuka.' : 'Shift aktif: '.collect($r['shifts'])->map(fn ($x) => $x['cashier'].' di '.$x['outlet'])->join(', ').'.',
            default => 'Maaf, data yang diminta belum dapat dibaca.',
        };
    }
}
