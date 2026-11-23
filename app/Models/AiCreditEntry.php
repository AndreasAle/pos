<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCreditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['business_id', 'kind', 'free_change', 'paid_change', 'free_after', 'paid_after', 'reference', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'free_change' => 'integer', 'paid_change' => 'integer'];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function change(): int
    {
        return $this->free_change + $this->paid_change;
    }
}
