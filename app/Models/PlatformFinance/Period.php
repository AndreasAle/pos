<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    protected $table = 'platform_fin_periods';

    protected $fillable = ['period', 'closed_at', 'closed_by', 'checklist', 'warnings_acknowledged', 'reopened_at', 'reopened_by', 'reopen_reason', 'reopen_count'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime', 'reopened_at' => 'datetime', 'checklist' => 'array', 'warnings_acknowledged' => 'boolean'];
    }

    public function isClosed(): bool
    {
        return $this->reopened_at === null;
    }
}
