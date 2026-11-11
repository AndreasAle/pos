<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformActivityLog extends Model
{
    protected $fillable = [
        'platform_admin_id', 'business_id', 'action', 'subject_type',
        'subject_id', 'description', 'metadata', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function admin()
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public static function record(
        string $action,
        string $description,
        mixed $subject = null,
        ?Business $business = null,
        array $metadata = []
    ): self {
        $admin = auth('platform')->user();

        return static::create([
            'platform_admin_id' => $admin?->id,
            'business_id' => $business?->id ?? $subject?->business_id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
