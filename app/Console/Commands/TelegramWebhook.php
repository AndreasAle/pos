<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhook extends Command
{
    protected $signature   = 'telegram:webhook {--delete : Hapus webhook}';
    protected $description = 'Daftarkan (atau hapus) webhook bot Telegram ke APP_URL/telegram/webhook';

    public function handle(TelegramService $tg): int
    {
        if (!TelegramService::enabled()) {
            $this->error('TELEGRAM_BOT_TOKEN belum diisi di .env');
            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $this->line(json_encode($tg->deleteWebhook()));
            return self::SUCCESS;
        }

        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET belum diisi di .env (isi dengan string acak, mis. 32 karakter).');
            return self::FAILURE;
        }

        $me = $tg->me();
        $this->info('Bot: @' . data_get($me, 'result.username', '?') . ' (isi TELEGRAM_BOT_USERNAME dengan nama ini, tanpa @)');

        $url = rtrim(config('app.url'), '/') . '/telegram/webhook';
        $res = $tg->setWebhook($url, $secret);
        $this->line($url);
        $this->line(json_encode($res));

        return data_get($res, 'ok') ? self::SUCCESS : self::FAILURE;
    }
}
