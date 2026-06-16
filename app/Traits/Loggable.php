<?php

namespace App\Traits;

use App\Models\ActivityLog;

trait Loggable
{
    // Override this method in your Model (tidak pakai property agar tidak conflict)
    public function getLogExcludeFields(): array
    {
        return ['updated_at', 'created_at', 'remember_token', 'password'];
    }

    public static function bootLoggable(): void
    {
        // Create
        static::created(function ($model) {
            if (!auth()->check()) return;
            ActivityLog::record(
                'created',
                class_basename($model) . ' baru dibuat: ' . ($model->name ?? $model->order_number ?? $model->id),
                $model,
                [],
                $model->getLoggableAttributes()
            );
        });

        // Update
        static::updated(function ($model) {
            if (!auth()->check()) return;
            $dirty = $model->getDirty();
            $old   = [];
            $new   = [];

            foreach ($dirty as $key => $newVal) {
                if (in_array($key, array_merge($model->getLogExcludeFields(), ['updated_at']))) continue;
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $newVal;
            }

            if (empty($new)) return;

            ActivityLog::record(
                'updated',
                class_basename($model) . ' diperbarui: ' . ($model->name ?? $model->order_number ?? $model->id),
                $model,
                $old,
                $new
            );
        });

        // Delete
        static::deleted(function ($model) {
            if (!auth()->check()) return;
            ActivityLog::record(
                'deleted',
                class_basename($model) . ' dihapus: ' . ($model->name ?? $model->order_number ?? $model->id),
                $model,
                $model->getLoggableAttributes()
            );
        });
    }

    public function getLoggableAttributes(): array
    {
        $exclude = array_merge($this->getLogExcludeFields(), ['password', 'remember_token']);
        return array_diff_key($this->getAttributes(), array_flip($exclude));
    }

    public function getLogLabel(): string
    {
        return $this->name ?? $this->order_number ?? '#' . $this->id;
    }
}
