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
        $lastTool = $conversation->messages()->where('role', 'assistant')->whereNotNull('tool_name')->orderByDesc('id')->value('tool_name');
        $conversation->messages()->create(['role' => 'user', 'content' => $question]);
        $history = $conversation->messages()->orderByDesc('id')->limit(12)->get()->reverse()->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->all();

        [$tool, $args] = $this->selectTool($question, $lastTool);
        if (config('services.yoka_ai.key')) [$tool, $args] = $this->selectWithLlm($history) ?? [$tool, $args];
        $result = $this->tools->execute($user->business, $tool, $args);
        $presentation = $this->presentation($tool, $result);
        $answer = config('services.yoka_ai.key')
            ? ($this->explainWithLlm($history, $tool, $result) ?? $this->localAnswer($tool, $result))
            : $this->localAnswer($tool, $result);
        $message = $conversation->messages()->create([
            'role' => 'assistant', 'content' => $answer, 'tool_name' => $tool,
            'meta' => ['arguments' => $args, 'result' => $result, 'presentation' => $presentation],
        ]);
        $conversation->touch();
        return ['conversation_id' => $conversation->id, 'message_id' => $message->id, 'answer' => $answer, 'tool' => $tool, 'data' => $result, 'presentation' => $presentation];
    }

    private function selectTool(string $question, ?string $lastTool): array
    {
        $q = Str::lower($question);
        $hasSubject = Str::contains($q, ['omzet','omset','profit','laba','penjualan','stok','stock','bahan','produk','menu','laris','margin','jam','pembayaran','qris','tunai','cash','debit','outlet','cabang','pelanggan','customer','member','shift','kasir']);
        $tool = match (true) {
            Str::contains($q, ['stok', 'stock', 'bahan', 'habis']) => 'stock_status',
            Str::contains($q, ['produk', 'menu', 'laris', 'margin']) => 'product_performance',
            Str::contains($q, ['jam ramai', 'jam sibuk', 'pukul', 'waktu ramai']) => 'peak_hours',
            Str::contains($q, ['pembayaran', 'qris', 'tunai', 'cash', 'debit']) => 'payment_breakdown',
            Str::contains($q, ['outlet', 'cabang']) => 'outlet_performance',
            Str::contains($q, ['pelanggan', 'customer', 'member']) => 'customer_performance',
            Str::contains($q, ['shift', 'kasir', 'siapa jaga']) => 'open_shifts',
            !$hasSubject && $lastTool => $lastTool,
            default => 'sales_summary',
        };
        $args = ['period' => match (true) {
            Str::contains($q, ['dari awal', 'sejak awal', 'semua transaksi', 'awal transaksi']) => 'all_time',
            Str::contains($q, 'kemarin') => 'yesterday', Str::contains($q, 'minggu lalu') => 'last_week',
            Str::contains($q, 'minggu ini') => 'this_week', Str::contains($q, 'bulan lalu') => 'last_month',
            Str::contains($q, 'bulan ini') => 'this_month', Str::contains($q, ['30 hari', 'sebulan terakhir']) => 'last_30_days',
            default => 'today',
        }];
        if (preg_match('/(?:dari|sejak)\s+tanggal\s+1\b/u', $q)) {
            $args = ['period' => 'custom', 'date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->toDateString()];
        }
        return [$tool, $args];
    }

    private function selectWithLlm(array $history): ?array
    {
        try {
            $messages = array_merge([['role' => 'system', 'content' => implode("\n", [
                'Kamu router data Yoka POS. Pilih tepat satu tool paling relevan.',
                'Hari ini '.now()->toDateString().' zona waktu '.config('app.timezone').'.',
                'Gunakan konteks percakapan. Follow-up seperti "kalau dari tanggal 1?" mempertahankan tool sebelumnya dan hanya mengubah periode.',
                'Gunakan all_time untuk "dari awal transaksi". Gunakan custom dan date_from/date_to untuk tanggal spesifik.',
                'Pertanyaan nilai pembayaran bukan biaya atau fee; tool hanya memiliki total transaksi per metode.',
            ])]], $history);
            $fn = $this->client()->post('/chat/completions', [
                'model' => config('services.yoka_ai.model'), 'temperature' => 0,
                'messages' => $messages, 'tools' => $this->tools->definitions(), 'tool_choice' => 'required',
            ])->throw()->json('choices.0.message.tool_calls.0.function');
            $allowed = collect($this->tools->definitions())->pluck('function.name')->all();
            if (!$fn || !in_array($fn['name'] ?? null, $allowed, true)) return null;
            return [$fn['name'], json_decode($fn['arguments'] ?? '{}', true) ?: []];
        } catch (\Throwable) { return null; }
    }

    private function explainWithLlm(array $history, string $tool, array $result): ?string
    {
        try {
            $messages = array_merge([['role' => 'system', 'content' => implode("\n", [
                'Kamu Yoka, partner analisis bisnis yang cerdas, natural, dan lugas. Gunakan bahasa Indonesia sesuai gaya user.',
                'Jawab pertanyaan terbaru langsung berdasarkan hasil tool dan pahami follow-up dari histori.',
                'Jangan memberi saran generik atau template. Beri insight hanya jika didukung angka.',
                'Jangan mengarang. Jika data tidak tersedia, jelaskan data apa yang belum dimiliki sistem.',
                'estimated_gross_profit adalah estimasi laba kotor produk, bukan laba bersih karena biaya operasional belum dicatat.',
                'Total payment adalah nilai pembayaran, bukan biaya atau fee pembayaran.',
                'Jawab 1–3 paragraf singkat. Visual KPI, grafik, dan tabel disajikan UI.',
            ])]], $history, [['role' => 'system', 'content' => 'Hasil terverifikasi tool '.$tool.': '.json_encode($result, JSON_UNESCAPED_UNICODE)]]);
            return $this->client()->post('/chat/completions', [
                'model' => config('services.yoka_ai.model'), 'temperature' => .35, 'messages' => $messages,
            ])->throw()->json('choices.0.message.content');
        } catch (\Throwable) { return null; }
    }

    private function client()
    {
        return Http::baseUrl(rtrim(config('services.yoka_ai.url'), '/'))->withToken(config('services.yoka_ai.key'))->timeout(25);
    }

    private function presentation(string $tool, array $r): array
    {
        $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        return match ($tool) {
            'sales_summary' => ['title'=>'Ringkasan '.$r['label'], 'metrics'=>[
                ['label'=>'Omzet','value'=>$rp($r['revenue'])], ['label'=>'Transaksi','value'=>number_format($r['orders'],0,',','.')],
                ['label'=>'Rata-rata order','value'=>$rp($r['average_order'])], ['label'=>'Estimasi laba kotor','value'=>$rp($r['estimated_gross_profit'])],
            ], 'chart'=>['label'=>'Omzet harian','items'=>collect($r['daily_series'])->map(fn($x)=>['label'=>substr($x['date'],5),'value'=>$x['revenue'],'formatted'=>$rp($x['revenue'])])->values()->all()]],
            'product_performance' => ['title'=>'Performa produk '.$r['label'],'chart'=>['label'=>'Omzet produk','items'=>collect($r['products'])->take(7)->map(fn($x)=>['label'=>$x['name'],'value'=>$x['revenue'],'formatted'=>$rp($x['revenue'])])->values()->all()], 'table'=>['columns'=>['Produk','Terjual','Omzet','Margin'],'rows'=>collect($r['products'])->take(7)->map(fn($x)=>[$x['name'],number_format($x['quantity'],0,',','.'),$rp($x['revenue']),$x['margin_percent'].'%'])->all()]],
            'payment_breakdown' => ['title'=>'Metode pembayaran '.$r['label'],'chart'=>['label'=>'Nilai pembayaran','items'=>collect($r['payments'])->map(fn($x)=>['label'=>strtoupper($x['payment_method']),'value'=>$x['total'],'formatted'=>$rp($x['total'])])->values()->all()], 'table'=>['columns'=>['Metode','Transaksi','Total'],'rows'=>collect($r['payments'])->map(fn($x)=>[strtoupper($x['payment_method']),number_format($x['orders'],0,',','.'),$rp($x['total'])])->all()]],
            'peak_hours' => ['title'=>'Jam ramai '.$r['label'],'chart'=>['label'=>'Jumlah transaksi','items'=>collect($r['hours'])->map(fn($x)=>['label'=>sprintf('%02d.00',$x['hour']),'value'=>$x['orders'],'formatted'=>$x['orders'].' transaksi'])->values()->all()]],
            'outlet_performance' => ['title'=>'Performa outlet '.$r['label'],'chart'=>['label'=>'Omzet outlet','items'=>collect($r['outlets'])->map(fn($x)=>['label'=>$x['name'],'value'=>$x['revenue'],'formatted'=>$rp($x['revenue'])])->values()->all()], 'table'=>['columns'=>['Outlet','Transaksi','Omzet'],'rows'=>collect($r['outlets'])->map(fn($x)=>[$x['name'],$x['orders'],$rp($x['revenue'])])->all()]],
            'customer_performance' => ['title'=>'Pelanggan teratas '.$r['label'],'table'=>['columns'=>['Pelanggan','Kunjungan','Belanja'],'rows'=>collect($r['customers'])->map(fn($x)=>[$x['name'],$x['visits'].'×',$rp($x['spending'])])->all()]],
            'stock_status' => ['title'=>'Kondisi stok bahan','table'=>['columns'=>['Bahan','Stok','Minimum','Estimasi'],'rows'=>collect($r['ingredients'])->map(fn($x)=>[$x['name'],$x['stock'].' '.$x['unit'],$x['minimum'].' '.$x['unit'],$x['estimated_days_left'] === null ? 'Belum cukup data' : $x['estimated_days_left'].' hari'])->all()]],
            'open_shifts' => ['title'=>'Shift yang sedang aktif','table'=>['columns'=>['Kasir','Outlet','Mulai'],'rows'=>collect($r['shifts'])->map(fn($x)=>[$x['cashier'] ?: '-',$x['outlet'] ?: '-',isset($x['opened_at']) ? date('H:i',strtotime($x['opened_at'])) : '-'])->all()]],
            default => [],
        };
    }

    private function localAnswer(string $tool, array $r): string
    {
        $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        return match ($tool) {
            'sales_summary' => ($r['orders'] ?? 0) ? "{$r['label']}, omzet tercatat {$rp($r['revenue'])} dari {$r['orders']} transaksi. Estimasi laba kotor produk {$rp($r['estimated_gross_profit'])}; ini belum laba bersih karena biaya operasional belum tersedia di Yoka." : "Belum ada transaksi {$r['label']}.",
            'product_performance' => empty($r['products']) ? "Belum ada produk terjual {$r['label']}." : "Produk dengan penjualan terbanyak {$r['label']} adalah {$r['products'][0]['name']} ({$r['products'][0]['quantity']} terjual). Rinciannya ada di bawah.",
            'stock_status' => empty($r['ingredients']) ? 'Data bahan yang diminta tidak ditemukan.' : 'Berikut kondisi stok terbaru dan estimasi ketahanannya berdasarkan pemakaian 14 hari terakhir.',
            'peak_hours' => empty($r['hours']) ? "Belum cukup transaksi {$r['label']} untuk membaca jam ramai." : sprintf('Puncak transaksi %s terjadi sekitar pukul %02d.00 dengan %d transaksi.', $r['label'], $r['hours'][0]['hour'], $r['hours'][0]['orders']),
            'payment_breakdown' => empty($r['payments']) ? "Belum ada pembayaran {$r['label']}." : "Berikut nilai dan jumlah transaksi per metode pembayaran {$r['label']}. Ini nilai pembayaran, bukan biaya atau fee gateway.",
            'outlet_performance' => empty($r['outlets']) ? "Belum ada data outlet {$r['label']}." : "Berikut perbandingan performa outlet {$r['label']}.",
            'customer_performance' => empty($r['customers']) ? "Belum ada transaksi pelanggan {$r['label']}." : "Berikut pelanggan dengan nilai belanja tertinggi {$r['label']}.",
            'open_shifts' => empty($r['shifts']) ? 'Tidak ada shift kasir yang sedang terbuka.' : 'Berikut shift kasir yang masih aktif saat ini.',
            default => 'Data yang diminta belum dapat dibaca.',
        };
    }
}
