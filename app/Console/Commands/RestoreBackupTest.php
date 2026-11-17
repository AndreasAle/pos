<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restore drill into a disposable database. Refuses to touch the database the
 * application is configured to use, so it cannot overwrite live data.
 */
class RestoreBackupTest extends Command
{
    protected $signature = 'yoka:backup-restore-test {file} {database : Disposable target database, must start with "restoretest"} {--drop : Drop the target afterwards}';

    protected $description = 'Restore an encrypted backup into a disposable database and report row counts';

    public function handle(): int
    {
        $target = (string) $this->argument('database');
        $live = (string) config('database.connections.'.config('database.default').'.database');
        if ($target === $live || ! preg_match('/^[A-Za-z0-9_]*restoretest[A-Za-z0-9_]*$/', $target)) {
            $this->error('Refused: target must be a disposable database whose name contains "restoretest" and differs from the app database.');

            return self::FAILURE;
        }

        $started = microtime(true);
        // Shared hosting users often lack CREATE DATABASE; a DB made in hPanel
        // must still work, so only create when it is missing.
        if (! DB::selectOne('SELECT 1 x FROM information_schema.schemata WHERE schema_name = ?', [$target])) {
            DB::statement("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        DatabaseBackup::fromConfig()->restore((string) $this->argument('file'), $target);
        $secs = round(microtime(true) - $started, 1);

        $rows = [];
        foreach (['migrations', 'businesses', 'users', 'orders', 'products'] as $table) {
            $exists = DB::selectOne('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$target, $table])->c;
            $rows[] = [$table, $exists ? DB::selectOne("SELECT COUNT(*) c FROM `$target`.`$table`")->c : 'missing'];
        }
        $this->table(['table', 'rows'], $rows);
        $this->info("Restore completed in {$secs}s into $target.");

        if ($this->option('drop')) {
            DB::statement("DROP DATABASE `$target`");
            $this->info("Dropped $target.");
        }

        return self::SUCCESS;
    }
}
