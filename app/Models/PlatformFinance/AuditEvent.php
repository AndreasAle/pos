<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Jejak audit keuangan. Hanya bisa ditambah, tidak bisa diubah atau dihapus. */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'platform_fin_audit_events';

    protected $fillable = ['actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'reason'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Jejak audit tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Jejak audit tidak boleh dihapus.'));
    }

    public static function log(string $actorType, ?int $actorId, string $action, Model $subject, ?array $before, ?array $after, ?string $reason = null): self
    {
        return self::create([
            'actor_type' => $actorType, 'actor_id' => $actorId, 'action' => $action,
            'subject_type' => class_basename($subject), 'subject_id' => $subject->getKey(),
            'before' => $before, 'after' => $after, 'reason' => $reason,
        ]);
    }
}
