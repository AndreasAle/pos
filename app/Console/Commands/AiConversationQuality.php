<?php

namespace App\Console\Commands;

use App\Services\PlatformAiTelemetry;
use Illuminate\Console\Command;

/** Ringkasan metrik kualitas percakapan Telegram Office (tanpa isi percakapan). */
class AiConversationQuality extends Command
{
    protected $signature = 'yoka:ai-quality {--days=7 : Jumlah hari ke belakang} {--json : Keluarkan JSON}';

    protected $description = 'Tampilkan metrik kualitas percakapan Tim AI di Telegram Office';

    public function handle(PlatformAiTelemetry $telemetry): int
    {
        $summary = $telemetry->qualitySummary(max(1, (int) $this->option('days')));
        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        $this->table(['Metrik', 'Jumlah'], collect($summary['metrics'])->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->line('Biaya model Telegram: $'.$summary['telegram_cost_usd']);
        $this->line('Biaya per interaksi berguna: '.($summary['cost_per_useful_interaction_usd'] === null ? '-' : '$'.$summary['cost_per_useful_interaction_usd']));

        return self::SUCCESS;
    }
}
