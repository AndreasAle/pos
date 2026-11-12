<?php

namespace App\Console\Commands;

use App\Services\PlatformHealth;
use Illuminate\Console\Command;

class YokaHealth extends Command
{
    protected $signature = 'yoka:health {--json : Keluaran JSON} {--strict : Exit code 1 bila ada yang gagal}';

    protected $description = 'Periksa kesehatan seluruh sistem (aplikasi, database, cron, email, Telegram, AI, Google, Midtrans, keamanan)';

    public function handle(PlatformHealth $health): int
    {
        $report = $health->run();
        $sum = $health->summary($report);

        if ($this->option('json')) {
            $this->line(json_encode(['summary' => $sum, 'report' => $report], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $icon = ['ok' => '[OK]  ', 'warn' => '[WARN]', 'fail' => '[FAIL]', 'info' => '[info]'];
            foreach ($report as $group) {
                $this->newLine();
                $this->line('<options=bold>'.$group['title'].'</>');
                foreach ($group['checks'] as $c) {
                    $line = ' '.$icon[$c['status']].' '.str_pad($c['name'], 28).' '.$c['detail'];
                    match ($c['status']) { 'fail' => $this->error($line), 'warn' => $this->warn($line), default => $this->line($line) };
                    if (! empty($c['hint']) && in_array($c['status'], ['warn', 'fail'], true)) {
                        $this->line('        -> '.$c['hint']);
                    }
                }
            }
            $this->newLine();
            $this->line("Ringkasan: {$sum['ok']} OK, {$sum['warn']} perhatian, {$sum['fail']} gagal");
        }

        return $this->option('strict') && $sum['fail'] ? self::FAILURE : self::SUCCESS;
    }
}
