<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiAgent extends Model
{
    protected $fillable = ['key', 'name', 'department', 'mission', 'status', 'autonomy', 'daily_budget', 'permissions', 'settings', 'last_run_at', 'next_run_at'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'settings' => 'array', 'last_run_at' => 'datetime', 'next_run_at' => 'datetime'];
    }

    public function tasks()
    {
        return $this->hasMany(PlatformAiTask::class);
    }
}
