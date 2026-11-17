<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BackupDatabase extends Command
{
    protected $signature = 'yoka:backup {--verify-only= : Verify an existing backup file instead of creating one}';

    protected $description = 'Create, verify and prune an encrypted database backup';

    public function handle(): int
    {
        try {
            $backup = DatabaseBackup::fromConfig();

            if ($file = $this->option('verify-only')) {
                $bytes = $backup->verify($file);
                $this->info("OK: $file decrypts to $bytes bytes of complete SQL.");

                return self::SUCCESS;
            }

            $started = microtime(true);
            $result = $backup->create(config('backup.path'));
            $bytes = $backup->verify($result['path']);
            $pruned = $backup->prune(config('backup.path'), config('backup.keep_days'));
            $secs = round(microtime(true) - $started, 1);

            Log::info('backup.ok', ['file' => basename($result['path']), 'bytes' => $result['bytes'], 'sql_bytes' => $bytes, 'seconds' => $secs, 'pruned' => count($pruned)]);
            $this->info("OK: {$result['path']} ({$result['bytes']} bytes, sha256 {$result['sha256']}, {$secs}s, pruned ".count($pruned).')');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('backup.failed', ['error' => $e->getMessage()]);
            $this->error('Backup failed: '.$e->getMessage());
            if ($to = config('backup.alert_email')) {
                try {
                    Mail::raw('Yoka backup failed at '.now()->toDateTimeString().': '.$e->getMessage(), fn ($m) => $m->to($to)->subject('[Yoka] Backup GAGAL'));
                } catch (\Throwable $mailError) {
                    Log::error('backup.alert_failed', ['error' => $mailError->getMessage()]);
                }
            }

            return self::FAILURE;
        }
    }
}
