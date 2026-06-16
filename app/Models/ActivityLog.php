<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'business_id', 'user_id', 'user_name', 'action',
        'subject_type', 'subject_id', 'subject_label',
        'old_values', 'new_values', 'description',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // Helper: rekam aktivitas
    public static function record(
        string $action,
        string $description,
        mixed  $subject = null,
        array  $oldValues = [],
        array  $newValues = []
    ): void {
        $user = auth()->user();

        static::create([
            'business_id'   => $user?->business_id,
            'user_id'       => $user?->id,
            'user_name'     => $user?->name,
            'action'        => $action,
            'subject_type'  => $subject ? get_class($subject) : null,
            'subject_id'    => $subject?->id,
            'subject_label' => method_exists($subject ?? new \stdClass, 'getLogLabel')
                                ? $subject->getLogLabel() : ($subject?->name ?? null),
            'old_values'    => $oldValues ?: null,
            'new_values'    => $newValues ?: null,
            'description'   => $description,
            'ip_address'    => request()->ip(),
            'user_agent'    => substr(request()->userAgent() ?? '', 0, 200),
        ]);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function getActionBadgeColorAttribute(): string
    {
        return match($this->action) {
            'created'        => 'green',
            'updated'        => 'blue',
            'deleted'        => 'red',
            'void'           => 'red',
            'login'          => 'gray',
            'logout'         => 'gray',
            'shift_open'     => 'emerald',
            'shift_close'    => 'orange',
            'stock_in'       => 'emerald',
            'stock_adjust'   => 'yellow',
            'order_paid'     => 'emerald',
            default          => 'gray',
        };
    }
}
