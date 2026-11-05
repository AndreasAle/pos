<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Move every stored date-time from UTC to WIB (UTC+7).
 *
 * The app ran with timezone UTC, so an order paid at 12:29 in the shop was
 * stored and shown as 05:29, and sales before 07:00 WIB landed on the previous
 * day in reports. config/app.php now uses Asia/Jakarta; from then on Laravel
 * writes WIB wall-clock times, so the rows written before must move by the same
 * seven hours or old and new data would disagree.
 *
 * Every DATETIME and TIMESTAMP column in the database is shifted, found through
 * information_schema so no table is missed. DATE columns are left alone: they
 * hold calendar days people chose, not instants.
 */
return new class extends Migration
{
    private const HOURS = 7;

    public function up(): void
    {
        $this->shift(self::HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::HOURS);
    }

    private function shift(int $hours): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Shifting the data while the app still reads it as UTC would put
        // every time seven hours in the future. Refuse rather than corrupt.
        $offset = now()->utcOffset();
        if ($offset !== self::HOURS * 60) {
            throw new RuntimeException(
                'APP timezone is not WIB (offset ' . $offset . ' min). Set APP_TIMEZONE=Asia/Jakarta '
                . '(or remove APP_TIMEZONE from .env), run php artisan config:clear, then migrate again.'
            );
        }

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->whereIn('data_type', ['datetime', 'timestamp'])
            ->where('table_name', '!=', 'migrations')
            ->orderBy('table_name')
            ->get(['table_name as table', 'column_name as column'])
            ->groupBy('table');

        $interval = ($hours >= 0 ? '+ ' : '- ') . 'INTERVAL ' . abs($hours) . ' HOUR';

        foreach ($columns as $table => $cols) {
            // One statement per table: a column with ON UPDATE CURRENT_TIMESTAMP
            // keeps its own (shifted) value only when it is set in the same
            // UPDATE as the others. NULLs and zero dates stay as they are.
            $sets = $cols->map(function ($c) use ($interval) {
                $col = '`' . str_replace('`', '``', $c->column) . '`';
                return "$col = CASE WHEN $col > '1000-01-01' THEN $col $interval ELSE $col END";
            })->implode(', ');

            DB::statement('UPDATE `' . str_replace('`', '``', $table) . '` SET ' . $sets);
        }
    }
};
